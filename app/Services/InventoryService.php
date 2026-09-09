<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Inventory\BulkInventoryStrategy;
use App\Services\Inventory\InventoryStrategy;
use App\Services\Inventory\SerializedInventoryStrategy;

class InventoryService
{
    public function __construct(
        private readonly BulkInventoryStrategy $bulkStrategy,
        private readonly SerializedInventoryStrategy $serializedStrategy,
    ) {}

    private function strategyFor(Product $product): InventoryStrategy
    {
        return $product->is_serialized ? $this->serializedStrategy : $this->bulkStrategy;
    }

    public function reserveForOrderItem(Product $product, Warehouse $warehouse, int $quantity, array $unitIds, array $context): ?int
    {
        return $this->strategyFor($product)->reserve($product, $warehouse, $quantity, $unitIds, $context);
    }

    public function restoreForOrderItem(OrderItem $item, Warehouse $warehouse, array $context): void
    {
        $this->strategyFor($item->product)->restore($item->product, $warehouse, $item->quantity, $item->product_unit_id, $context);
    }

    public function markSold(OrderItem $item): void
    {
        $this->strategyFor($item->product)->markSold($item->product_unit_id);
    }

    public function stockIn(Product $product, Warehouse $warehouse, ?int $quantity, array $imeiSerials, array $context): void
    {
        $this->strategyFor($product)->stockIn($product, $warehouse, $quantity ?? count($imeiSerials), $imeiSerials, $context);
    }

    public function adjust(Product $product, Warehouse $warehouse, array $payload, array $context): array
    {
        return $this->strategyFor($product)->adjust($product, $warehouse, $payload, $context);
    }

    public function transfer(Product $product, Warehouse $from, Warehouse $to, ?int $quantity, array $unitIds, array $context): void
    {
        $this->strategyFor($product)->transfer($product, $from, $to, $quantity ?? count($unitIds), $unitIds, $context);
    }
}
