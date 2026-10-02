<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function createDraft(array $data, User $actor): PurchaseOrder
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return DB::transaction(fn () => $this->doCreateDraft($data, $actor));
            } catch (QueryException $e) {
                if ($attempt === 3 || ! str_contains($e->getMessage(), 'purchase_orders_code_unique')) {
                    throw $e;
                }
            }
        }
    }

    private function doCreateDraft(array $data, User $actor): PurchaseOrder
    {
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);

        $purchaseOrder = PurchaseOrder::create([
            'code' => 'PO'.now()->format('ymd').Str::upper(Str::random(4)),
            'supplier_id' => $data['supplier_id'],
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrderStatus::Draft,
            'order_date' => $data['order_date'] ?? now()->toDateString(),
            'expected_date' => $data['expected_date'] ?? null,
            'created_by' => $actor->id,
            'total_amount' => 0,
        ]);

        $total = 0;

        foreach ($data['items'] as $itemData) {
            $product = Product::findOrFail($itemData['product_id']);
            $quantity = (int) $itemData['quantity_ordered'];
            $unitCost = (int) $itemData['unit_cost'];

            PurchaseOrderItem::create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id' => $product->id,
                'quantity_ordered' => $quantity,
                'quantity_received' => 0,
                'unit_cost' => $unitCost,
            ]);

            $total += $quantity * $unitCost;
        }

        $purchaseOrder->update(['total_amount' => $total]);

        return $purchaseOrder->fresh(['items.product', 'supplier', 'warehouse']);
    }

    public function markOrdered(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ đơn nhập ở trạng thái draft mới gửi cho nhà cung cấp được.',
            ]);
        }

        $purchaseOrder->update(['status' => PurchaseOrderStatus::Ordered]);

        return $purchaseOrder->fresh(['items.product', 'supplier', 'warehouse']);
    }

    /**
     * Nhận hàng cho 1 hoặc nhiều dòng của đơn nhập. $receivedItems dạng
     * [['purchase_order_item_id' => int, 'quantity' => int, 'imei_serials' => string[]]].
     * Mỗi lần nhận cập nhật quantity_received, cộng tồn kho qua InventoryService
     * (ghi stock_movements loại 'in', reference_type = 'purchase_order'), và tự
     * chuyển status sang partially_received hoặc received tuỳ còn thiếu hàng
     * hay đã đủ cho TẤT CẢ các dòng.
     */
    public function receive(PurchaseOrder $purchaseOrder, array $receivedItems, User $actor): PurchaseOrder
    {
        if (! $purchaseOrder->status->canReceive()) {
            throw ValidationException::withMessages([
                'status' => "Đơn nhập ở trạng thái {$purchaseOrder->status->value} không thể nhận hàng.",
            ]);
        }

        return DB::transaction(function () use ($purchaseOrder, $receivedItems, $actor) {
            $context = [
                'reference_type' => 'purchase_order',
                'reference_id' => $purchaseOrder->id,
                'note' => null,
                'created_by' => $actor->id,
            ];

            foreach ($receivedItems as $received) {
                /** @var PurchaseOrderItem $item */
                $item = PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)
                    ->findOrFail($received['purchase_order_item_id']);

                $quantity = (int) $received['quantity'];

                if ($quantity <= 0) {
                    continue;
                }

                if ($item->quantity_received + $quantity > $item->quantity_ordered) {
                    throw ValidationException::withMessages([
                        'items' => "Số lượng nhận vượt quá số lượng đặt cho sản phẩm {$item->product->sku}.",
                    ]);
                }

                $this->inventory->stockIn(
                    $item->product,
                    $purchaseOrder->warehouse,
                    $quantity,
                    $received['imei_serials'] ?? [],
                    $context,
                );

                $item->increment('quantity_received', $quantity);
            }

            $stillPending = $purchaseOrder->items()->whereColumn('quantity_received', '<', 'quantity_ordered')->exists();

            $purchaseOrder->update([
                'status' => $stillPending ? PurchaseOrderStatus::PartiallyReceived : PurchaseOrderStatus::Received,
            ]);

            return $purchaseOrder->fresh(['items.product', 'supplier', 'warehouse']);
        });
    }

    public function cancel(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if (! $purchaseOrder->status->isCancellable()) {
            throw ValidationException::withMessages([
                'status' => "Đơn nhập ở trạng thái {$purchaseOrder->status->value} không thể huỷ.",
            ]);
        }

        $purchaseOrder->update(['status' => PurchaseOrderStatus::Cancelled]);

        return $purchaseOrder->fresh(['items.product', 'supplier', 'warehouse']);
    }
}
