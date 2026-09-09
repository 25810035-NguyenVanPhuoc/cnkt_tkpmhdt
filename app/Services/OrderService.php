<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function createOrder(array $data, User $actor): Order
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return DB::transaction(fn () => $this->doCreateOrder($data, $actor));
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

        $context = [
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'note' => null,
            'created_by' => $actor->id,
        ];

        $subtotal = 0;
        $discountTotal = 0;

        foreach ($data['items'] as $itemData) {
            /** @var Product $product */
            $product = Product::findOrFail($itemData['product_id']);

            if (! $product->is_active) {
                throw ValidationException::withMessages([
                    'items' => "Sản phẩm {$product->sku} đã ngừng kinh doanh.",
                ]);
            }

            $unitPrice = $product->sale_price;
            $quantity = (int) $itemData['quantity'];
            $lineDiscount = (int) ($itemData['discount_amount'] ?? 0);
            $unitIds = $itemData['product_unit_ids'] ?? [];

            if ($product->is_serialized) {
                $count = count($unitIds);
                $perUnitDiscount = intdiv($lineDiscount, $count);
                $remainder = $lineDiscount % $count;

                foreach (array_values($unitIds) as $index => $unitId) {
                    $unitDiscount = $perUnitDiscount + ($index === $count - 1 ? $remainder : 0);

                    $reservedUnitId = $this->inventory->reserveForOrderItem($product, $warehouse, 1, [$unitId], $context);

                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_unit_id' => $reservedUnitId,
                        'quantity' => 1,
                        'unit_price' => $unitPrice,
                        'discount_amount' => $unitDiscount,
                        'line_total' => $unitPrice - $unitDiscount,
                    ]);

                    $product->productUnits()->whereKey($reservedUnitId)->update(['order_item_id' => $orderItem->id]);
                }
            } else {
                $this->inventory->reserveForOrderItem($product, $warehouse, $quantity, [], $context);

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
        }

        $order->update([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'grand_total' => $subtotal - $discountTotal + $order->shipping_fee,
        ]);

        return $order->load(['items.product', 'items.productUnit', 'customer', 'warehouse', 'statusHistories']);
    }

    private function resolveCustomer(array $data): ?Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::findOrFail($data['customer_id']);
        }

        if (! empty($data['customer_phone'])) {
            return Customer::firstOrCreate(
                ['phone' => $data['customer_phone']],
                ['name' => $data['customer_name'] ?? 'Khách vãng lai'],
            );
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

        return DB::transaction(function () use ($order, $from, $to, $note, $actor) {
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

            $context = [
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'note' => $note,
                'created_by' => $actor->id,
            ];

            foreach ($order->items as $item) {
                $this->inventory->restoreForOrderItem($item, $order->warehouse, $context);
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
