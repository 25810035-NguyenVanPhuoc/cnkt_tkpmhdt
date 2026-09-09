<?php

namespace App\Models;

use App\Enums\ProductUnitStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id', 'warehouse_id', 'purchase_order_item_id', 'order_item_id',
    'imei_serial', 'status',
])]
class ProductUnit extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ProductUnitStatus::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
