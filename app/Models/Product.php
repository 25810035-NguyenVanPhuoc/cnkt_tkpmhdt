<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sku', 'name', 'slug', 'category_id', 'cost_price', 'sale_price',
    'description', 'is_serialized', 'is_active',
])]
class Product extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cost_price' => 'integer',
            'sale_price' => 'integer',
            'is_serialized' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
