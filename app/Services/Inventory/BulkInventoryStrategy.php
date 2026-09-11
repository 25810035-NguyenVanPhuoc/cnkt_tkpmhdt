<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Validation\ValidationException;

/**
 * Chiến lược tồn kho cho sản phẩm quản lý theo SỐ LƯỢNG (is_serialized = false),
 * dùng bảng product_stock.
 */
class BulkInventoryStrategy implements InventoryStrategy
{
    public function reserve(Product $product, Warehouse $warehouse, int $quantity, array $unitIds, array $context): ?int
    {
        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->lockForUpdate()
            ->first();

        if (! $stock || $stock->quantity < $quantity) {
            throw ValidationException::withMessages([
                'items' => "Không đủ tồn kho cho sản phẩm {$product->sku} tại kho đã chọn.",
            ]);
        }

        $stock->decrement('quantity', $quantity);

        $this->recordMovement($product, $warehouse, -$quantity, null, $context, StockMovementType::Out);

        return null;
    }

    public function restore(Product $product, Warehouse $warehouse, int $quantity, ?int $productUnitId, array $context): void
    {
        $stock = ProductStock::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            ['quantity' => 0],
        );
        $stock->increment('quantity', $quantity);

        $this->recordMovement($product, $warehouse, $quantity, null, $context, StockMovementType::In);
    }

    public function markSold(?int $productUnitId): void
    {
        // Sản phẩm bulk không có unit riêng để chốt bán — số lượng đã trừ ngay lúc tạo đơn.
    }

    public function stockIn(Product $product, Warehouse $warehouse, int $quantity, array $imeiSerials, array $context): void
    {
        $stock = ProductStock::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            ['quantity' => 0],
        );
        $stock->increment('quantity', $quantity);

        $this->recordMovement($product, $warehouse, $quantity, null, $context, StockMovementType::In);
    }

    public function adjust(Product $product, Warehouse $warehouse, array $payload, array $context): array
    {
        $stock = ProductStock::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            ['quantity' => 0],
        );
        $stock = ProductStock::where('id', $stock->id)->lockForUpdate()->first();

        $before = $stock->quantity;
        $after = (int) $payload['quantity_after'];
        $delta = $after - $before;

        $stock->update(['quantity' => $after]);

        $this->recordMovement($product, $warehouse, $delta, null, $context, StockMovementType::Adjustment);

        return ['before' => $before, 'after' => $after, 'delta' => $delta];
    }

    public function transfer(Product $product, Warehouse $from, Warehouse $to, int $quantity, array $unitIds, array $context): void
    {
        $source = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $from->id)
            ->lockForUpdate()
            ->first();

        if (! $source || $source->quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Không đủ tồn kho tại kho nguồn để chuyển sản phẩm {$product->sku}.",
            ]);
        }

        $source->decrement('quantity', $quantity);

        $destination = ProductStock::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $to->id],
            ['quantity' => 0],
        );
        $destination->increment('quantity', $quantity);

        $note = $context['note'] ?? "Chuyển kho: {$from->name} → {$to->name}";

        $this->recordMovement($product, $from, -$quantity, null, [...$context, 'note' => $note], StockMovementType::Transfer);
        $this->recordMovement($product, $to, $quantity, null, [...$context, 'note' => $note], StockMovementType::Transfer);
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
