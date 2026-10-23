<?php

namespace App\Services\Inventory;

use App\Enums\ProductUnitStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Validation\ValidationException;

/**
 * Chiến lược tồn kho cho sản phẩm quản lý theo TỪNG ĐƠN VỊ / SERIAL-IMEI
 * (is_serialized = true), dùng bảng product_units. Mỗi lệnh gọi reserve()
 * xử lý đúng 1 unit (khớp với việc OrderService tạo 1 OrderItem/unit).
 *
 * Đồng thời đồng bộ tổng số lượng "in_stock" vào bảng product_stock (cùng
 * bảng mà BulkInventoryStrategy dùng), để StockController::index (tab "Tồn
 * kho theo số lượng") hiển thị đúng cho CẢ sản phẩm serial lẫn sản phẩm
 * thường trong cùng một danh sách, không cần tab Serial/IMEI riêng.
 */
class SerializedInventoryStrategy implements InventoryStrategy
{
    public function reserve(Product $product, Warehouse $warehouse, int $quantity, array $unitIds, array $context): ?int
    {
        $unitId = $unitIds[0] ?? null;

        $unit = $unitId ? ProductUnit::where('id', $unitId)
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('status', ProductUnitStatus::InStock)
            ->lockForUpdate()
            ->first() : null;

        if (! $unit) {
            throw ValidationException::withMessages([
                'items' => "Không đủ tồn kho hoặc IMEI/serial không hợp lệ cho sản phẩm {$product->sku}.",
            ]);
        }

        $unit->update(['status' => ProductUnitStatus::Reserved]);
        $this->adjustStockCount($product, $warehouse, -1);

        $this->recordMovement($product, $warehouse, -1, $unit->id, $context, StockMovementType::Out);

        return $unit->id;
    }

    public function restore(Product $product, Warehouse $warehouse, int $quantity, ?int $productUnitId, array $context): void
    {
        if (! $productUnitId) {
            return;
        }

        ProductUnit::whereKey($productUnitId)->update([
            'status' => ProductUnitStatus::InStock,
            'order_item_id' => null,
        ]);
        $this->adjustStockCount($product, $warehouse, 1);

        $this->recordMovement($product, $warehouse, 1, $productUnitId, $context, StockMovementType::In);
    }

    public function markSold(?int $productUnitId): void
    {
        if ($productUnitId) {
            ProductUnit::whereKey($productUnitId)->update(['status' => ProductUnitStatus::Sold]);
        }
    }

    public function stockIn(Product $product, Warehouse $warehouse, int $quantity, array $imeiSerials, array $context): void
    {
        foreach ($imeiSerials as $serial) {
            $unit = ProductUnit::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'imei_serial' => $serial,
                'status' => ProductUnitStatus::InStock,
            ]);

            $this->recordMovement($product, $warehouse, 1, $unit->id, $context, StockMovementType::In);
        }

        if (count($imeiSerials) > 0) {
            $this->adjustStockCount($product, $warehouse, count($imeiSerials));
        }
    }

    public function adjust(Product $product, Warehouse $warehouse, array $payload, array $context): array
    {
        $unit = ProductUnit::where('id', $payload['product_unit_id'])
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->lockForUpdate()
            ->first();

        if (! $unit) {
            throw ValidationException::withMessages(['product_unit_id' => 'Không tìm thấy đơn vị sản phẩm (serial) tại kho đã chọn.']);
        }

        $allowed = [ProductUnitStatus::InStock->value, ProductUnitStatus::Damaged->value];
        if (! in_array($unit->status->value, $allowed, true) || ! in_array($payload['status'], $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'Chỉ có thể điều chỉnh qua lại giữa trạng thái in_stock và damaged.']);
        }

        $before = $unit->status->value;
        $unit->update(['status' => $payload['status']]);

        // in_stock -> damaged: mat 1 don vi kha dung; damaged -> in_stock: nguoc lai.
        if ($before === ProductUnitStatus::InStock->value && $payload['status'] === ProductUnitStatus::Damaged->value) {
            $this->adjustStockCount($product, $warehouse, -1);
        } elseif ($before === ProductUnitStatus::Damaged->value && $payload['status'] === ProductUnitStatus::InStock->value) {
            $this->adjustStockCount($product, $warehouse, 1);
        }

        $this->recordMovement($product, $warehouse, 0, $unit->id, [...$context, 'note' => ($context['note'] ?? null) ?: "Đổi trạng thái: {$before} → {$payload['status']}"], StockMovementType::Adjustment);

        return ['before' => $before, 'after' => $payload['status']];
    }

    public function transfer(Product $product, Warehouse $from, Warehouse $to, int $quantity, array $unitIds, array $context): void
    {
        $units = ProductUnit::whereIn('id', $unitIds)
            ->where('product_id', $product->id)
            ->where('warehouse_id', $from->id)
            ->where('status', ProductUnitStatus::InStock)
            ->lockForUpdate()
            ->get();

        if ($units->count() !== count($unitIds)) {
            throw ValidationException::withMessages([
                'product_unit_ids' => 'Một số đơn vị sản phẩm không thuộc kho nguồn hoặc không sẵn sàng để chuyển.',
            ]);
        }

        $note = $context['note'] ?? "Chuyển kho: {$from->name} → {$to->name}";

        foreach ($units as $unit) {
            $unit->update(['warehouse_id' => $to->id]);

            $this->recordMovement($product, $from, -1, $unit->id, [...$context, 'note' => $note], StockMovementType::Transfer);
            $this->recordMovement($product, $to, 1, $unit->id, [...$context, 'note' => $note], StockMovementType::Transfer);
        }

        if ($units->count() > 0) {
            $this->adjustStockCount($product, $from, -$units->count());
            $this->adjustStockCount($product, $to, $units->count());
        }
    }

    /**
     * Dong bo tong so luong "in_stock" vao product_stock.quantity - cung
     * ban ghi ma BulkInventoryStrategy dung, de tab "Ton kho theo so luong"
     * hien dung cho ca san pham serial.
     */
    private function adjustStockCount(Product $product, Warehouse $warehouse, int $delta): void
    {
        $stock = ProductStock::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            ['quantity' => 0],
        );

        if ($delta > 0) {
            $stock->increment('quantity', $delta);
        } elseif ($delta < 0) {
            $stock->decrement('quantity', min(abs($delta), $stock->quantity));
        }
    }

    private function recordMovement(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        ?int $productUnitId,
        array $context,
        StockMovementType $type,
    ): void {
        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'product_unit_id' => $productUnitId,
            'type' => $type,
            'quantity' => $quantity,
            'reference_type' => $context['reference_type'] ?? null,
            'reference_id' => $context['reference_id'] ?? null,
            'note' => $context['note'] ?? null,
            'created_by' => $context['created_by'] ?? null,
        ]);
    }
}
