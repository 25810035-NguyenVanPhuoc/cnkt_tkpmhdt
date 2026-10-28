<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductUnitStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductUnit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly PromotionService $promotions,
    ) {}

    public function createOrder(array $data, User $actor): Order
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $order = DB::transaction(fn () => $this->doCreateOrder($data, $actor));

                ActivityLogger::instance()->logOrderCreated(
                    $order->code,
                    $actor,
                    $order->customer,
                    $order->grand_total,
                    $data['source'] ?? 'admin',
                );

                return $order;
            } catch (QueryException $e) {
                if ($attempt === 3 || ! str_contains($e->getMessage(), 'orders_code_unique')) {
                    throw $e;
                }
            }
        }
    }

    private function doCreateOrder(array $data, User $actor): Order
    {
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $customer = $this->resolveCustomer($data);

        $order = Order::create([
            'code' => 'DH'.now()->format('ymd').Str::upper(Str::random(4)),
            'customer_id' => $customer?->id,
            'user_id' => $actor->id,
            'warehouse_id' => $warehouse->id,
            'status' => OrderStatus::Pending,
            'subtotal' => 0,
            'discount_total' => 0,
            'shipping_fee' => $data['shipping_fee'] ?? 0,
            'grand_total' => 0,
            'paid_amount' => 0,
            'order_date' => now(),
            'note' => $data['note'] ?? null,
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => OrderStatus::Pending->value,
            'changed_by' => $actor->id,
            'note' => null,
        ]);

        $subtotal = 0;
        $discountTotal = 0;
        $promotionItems = collect();

        foreach ($data['items'] as $itemData) {
            $product = $this->resolveActiveProduct($itemData['product_id']);

            $unitPrice = $product->sale_price;
            $quantity = (int) $itemData['quantity'];
            $lineDiscount = (int) ($itemData['discount_amount'] ?? 0);
            $unitIds = $itemData['product_unit_ids'] ?? [];

            // QUAN TRONG: don hang moi tao luon o trang thai Pending - CHUA
            // tru/khoa ton kho o buoc nay. Chi ghi nhan don vi (IMEI) da
            // CHON (vd NV quet IMEI thuc te tai quay, hoac storefront tu
            // chon san), nhung KHONG doi status ProductUnit va KHONG tru
            // product_stock.quantity. Viec khoa ton kho that su (tru kho)
            // chi xay ra khi don chuyen Pending -> Confirmed (xem
            // changeStatus() ben duoi) - dung y muon: "chua xac nhan giao
            // hang thi chua tru kho, admin duyet thi moi tru".
            if ($product->is_serialized) {
                $count = count($unitIds);

                $available = ProductUnit::where('product_id', $product->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('status', ProductUnitStatus::InStock)
                    ->whereIn('id', $unitIds)
                    ->count();

                if ($available < $count) {
                    throw ValidationException::withMessages([
                        'items' => "Sản phẩm {$product->sku} không đủ tồn kho hoặc IMEI/serial không hợp lệ.",
                    ]);
                }

                $perUnitDiscount = intdiv($lineDiscount, $count);
                $remainder = $lineDiscount % $count;

                foreach (array_values($unitIds) as $index => $unitId) {
                    $unitDiscount = $perUnitDiscount + ($index === $count - 1 ? $remainder : 0);

                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_unit_id' => $unitId,
                        'quantity' => 1,
                        'unit_price' => $unitPrice,
                        'discount_amount' => $unitDiscount,
                        'line_total' => $unitPrice - $unitDiscount,
                    ]);

                    $product->productUnits()->whereKey($unitId)->update(['order_item_id' => $orderItem->id]);
                }
            } else {
                $stock = ProductStock::where('product_id', $product->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->first();

                if (! $stock || $stock->quantity < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Sản phẩm {$product->sku} không đủ tồn kho.",
                    ]);
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_unit_id' => null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $lineDiscount,
                    'line_total' => ($unitPrice * $quantity) - $lineDiscount,
                ]);
            }

            $subtotal += $unitPrice * $quantity;
            $discountTotal += $lineDiscount;

            $promotionItems->push([
                'product_id' => $product->id,
                'category_id' => $product->category_id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => ($unitPrice * $quantity) - $lineDiscount,
            ]);
        }

        if (! empty($data['promotion_code'])) {
            $promotion = $this->promotions->resolveByCode($data['promotion_code'], $customer);
            $promoDiscount = $this->promotions->computeDiscount($promotion, $promotionItems, $subtotal);
            $discountTotal += $promoDiscount;
        }

        $order->update([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'grand_total' => $subtotal - $discountTotal + $order->shipping_fee,
        ]);

        if (isset($promotion)) {
            $this->promotions->applyToOrder($order, $promotion, $promoDiscount);
        }

        return $order->load(['items.product', 'items.productUnit', 'customer', 'warehouse', 'statusHistories']);
    }

    private function resolveActiveProduct(int $productId): Product
    {
        /** @var Product $product */
        $product = Product::findOrFail($productId);

        if (! $product->is_active) {
            throw ValidationException::withMessages([
                'items' => "Sản phẩm {$product->sku} đã ngừng kinh doanh.",
            ]);
        }

        return $product;
    }

    /**
     * Tinh thu subtotal/discount_total/grand_total khi ap ma khuyen mai -
     * KHONG tao Order/OrderItem, KHONG tang usage_count (chi doc, dung cho
     * nut "Ap dung" o trang thanh toan xem truoc truoc khi dat hang that).
     *
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    public function previewPromotion(array $items, string $promotionCode, ?Customer $customer): array
    {
        $subtotal = 0;
        $promotionItems = collect();

        foreach ($items as $itemData) {
            $product = $this->resolveActiveProduct((int) $itemData['product_id']);
            $quantity = (int) $itemData['quantity'];
            $unitPrice = $product->sale_price;
            $lineTotal = $unitPrice * $quantity;

            $subtotal += $lineTotal;

            $promotionItems->push([
                'product_id' => $product->id,
                'category_id' => $product->category_id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ]);
        }

        $promotion = $this->promotions->resolveByCode($promotionCode, $customer);
        $discountTotal = $this->promotions->computeDiscount($promotion, $promotionItems, $subtotal);

        return [
            'promotion_code' => $promotion->code,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'shipping_fee' => 0,
            'grand_total' => $subtotal - $discountTotal,
        ];
    }

    private function resolveCustomer(array $data): ?Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::findOrFail($data['customer_id']);
        }

        if (! empty($data['customer_phone'])) {
            $customer = Customer::firstOrCreate(
                ['phone' => $data['customer_phone']],
                ['name' => $data['customer_name'] ?? 'Khách vãng lai'],
            );

            // firstOrCreate() chi dat 'name' luc TAO MOI - neu khach da ton
            // tai theo so dien thoai nay va lan nay go ten khac, cap nhat
            // lai ten moi nhat (phone la khoa dinh danh, ten co the thay
            // doi/duoc nhap chinh xac hon ở lan sau).
            if (! empty($data['customer_name']) && $customer->name !== $data['customer_name']) {
                $customer->update(['name' => $data['customer_name']]);
            }

            return $customer;
        }

        return null;
    }

    public function changeStatus(Order $order, OrderStatus $to, ?string $note, User $actor): Order
    {
        $from = $order->status;

        if (! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => "Không thể chuyển đơn hàng từ trạng thái {$from->value} sang {$to->value}.",
            ]);
        }

        if ($to === OrderStatus::Delivering && empty(trim((string) $order->shipping_address))) {
            throw ValidationException::withMessages([
                'shipping_address' => 'Đơn hàng chưa có địa chỉ giao hàng - vui lòng bổ sung địa chỉ trước khi chuyển sang trạng thái "Đang giao".',
            ]);
        }

        return DB::transaction(function () use ($order, $from, $to, $note, $actor) {
            // Admin "duyet"/xac nhan don (Pending -> Confirmed) la thoi diem
            // DUY NHAT thuc su khoa/tru ton kho - truoc do (luc tao don,
            // dang Pending) chi la "giu cho", chua anh huong ton kho that.
            if ($from === OrderStatus::Pending && $to === OrderStatus::Confirmed) {
                $context = [
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'note' => $note,
                    'created_by' => $actor->id,
                ];

                foreach ($order->items as $item) {
                    if ($item->product->is_serialized) {
                        if (! $item->product_unit_id) {
                            throw ValidationException::withMessages([
                                'items' => "Sản phẩm {$item->product->sku} chưa chọn đơn vị (serial) để xác nhận.",
                            ]);
                        }

                        $this->inventory->reserveForOrderItem($item->product, $order->warehouse, 1, [$item->product_unit_id], $context);
                    } else {
                        $this->inventory->reserveForOrderItem($item->product, $order->warehouse, $item->quantity, [], $context);
                    }
                }
            }

            $order->update(['status' => $to]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'changed_by' => $actor->id,
                'note' => $note,
            ]);

            if ($to === OrderStatus::Completed) {
                foreach ($order->items as $item) {
                    $this->inventory->markSold($item);
                }
            }

            return $order->refresh()->load(['items.product', 'items.productUnit', 'customer', 'warehouse', 'statusHistories']);
        });
    }

    public function cancel(Order $order, ?string $note, User $actor): Order
    {
        if (! $order->status->isCancellable()) {
            throw ValidationException::withMessages([
                'status' => "Đơn hàng ở trạng thái {$order->status->value} không thể huỷ.",
            ]);
        }

        return DB::transaction(function () use ($order, $note, $actor) {
            $from = $order->status;

            $order->update(['status' => OrderStatus::Cancelled]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $from->value,
                'to_status' => OrderStatus::Cancelled->value,
                'changed_by' => $actor->id,
                'note' => $note,
            ]);

            // Chi hoan lai ton kho neu don DA tung bi tru that (tuc da qua
            // buoc Pending -> Confirmed). Huy ngay tu luc con Pending thi
            // chua co gi bi tru ca, hoan se lam SAI lech ton kho (cong du).
            if ($from !== OrderStatus::Pending) {
                $context = [
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'note' => $note,
                    'created_by' => $actor->id,
                ];

                foreach ($order->items as $item) {
                    $this->inventory->restoreForOrderItem($item, $order->warehouse, $context);
                }
            }

            return $order->refresh()->load(['items.product', 'items.productUnit', 'customer', 'warehouse', 'statusHistories']);
        });
    }

    public function recordPayment(Order $order, int $amount, string $paymentMethod): Order
    {
        if ($order->paid_amount + $amount > $order->grand_total) {
            throw ValidationException::withMessages([
                'amount' => 'Số tiền thanh toán vượt quá tổng tiền đơn hàng.',
            ]);
        }

        $order->update([
            'paid_amount' => $order->paid_amount + $amount,
            'payment_method' => $paymentMethod,
        ]);

        return $order->refresh()->load(['items.product', 'items.productUnit', 'customer', 'warehouse', 'statusHistories']);
    }
}
