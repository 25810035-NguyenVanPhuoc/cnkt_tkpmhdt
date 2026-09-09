<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\Warehouse;

/**
 * Strategy Pattern: mỗi cách quản lý tồn kho (theo số lượng / theo serial-IMEI)
 * implement cùng 1 interface này. InventoryService chọn strategy phù hợp tại
 * runtime dựa trên Product::is_serialized, các Service gọi (OrderService,
 * StockController) không cần biết chi tiết bên trong khác nhau thế nào.
 */
interface InventoryStrategy
{
    /**
     * Giữ/trừ tồn kho cho 1 order item (gọi 1 lần cho mỗi OrderItem sắp tạo).
     * Trả về product_unit_id đã giữ (serial) hoặc null (bulk, không có unit).
     *
     * @param  array<int>  $unitIds
     * @param  array{reference_type:string,reference_id:?int,note:?string,created_by:?int}  $context
     */
    public function reserve(Product $product, Warehouse $warehouse, int $quantity, array $unitIds, array $context): ?int;

    /** Hoàn kho khi huỷ đơn. */
    public function restore(Product $product, Warehouse $warehouse, int $quantity, ?int $productUnitId, array $context): void;

    /** Chốt bán khi đơn hoàn tất (completed) — không phát sinh biến động số lượng mới. */
    public function markSold(?int $productUnitId): void;

    /**
     * Nhập kho thủ công (không qua đơn nhập hàng).
     *
     * @param  array<string>  $imeiSerials
     * @param  array{reference_type:string,reference_id:?int,note:?string,created_by:?int}  $context
     */
    public function stockIn(Product $product, Warehouse $warehouse, int $quantity, array $imeiSerials, array $context): void;

    /**
     * Kiểm kê / điều chỉnh kho.
     *
     * @param  array{quantity_after?:int,product_unit_id?:int,status?:string}  $payload
     * @param  array{reference_type:string,reference_id:?int,note:?string,created_by:?int}  $context
     * @return array<string,mixed>
     */
    public function adjust(Product $product, Warehouse $warehouse, array $payload, array $context): array;

    /**
     * Chuyển kho giữa 2 warehouse.
     *
     * @param  array<int>  $unitIds
     * @param  array{reference_type:string,reference_id:?int,note:?string,created_by:?int}  $context
     */
    public function transfer(Product $product, Warehouse $from, Warehouse $to, int $quantity, array $unitIds, array $context): void;
}
