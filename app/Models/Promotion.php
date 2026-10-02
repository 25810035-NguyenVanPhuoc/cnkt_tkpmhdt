<?php

namespace App\Models;

use App\Enums\PromotionAppliesTo;
use App\Enums\PromotionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'code', 'type', 'value', 'buy_quantity', 'get_quantity',
    'min_order_amount', 'applies_to', 'target_id',
    'starts_at', 'ends_at', 'usage_limit', 'usage_count', 'is_active',
])]
class Promotion extends Model
{
    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'applies_to' => PromotionAppliesTo::class,
            'value' => 'integer',
            'buy_quantity' => 'integer',
            'get_quantity' => 'integer',
            'min_order_amount' => 'integer',
            'target_id' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'usage_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'promotion_customer')
            ->withPivot(['assigned_at', 'used_at']);
    }

    public function orderPromotions(): HasMany
    {
        return $this->hasMany(OrderPromotion::class);
    }
}
