<?php

namespace App\Services;

use App\Enums\PromotionAppliesTo;
use App\Enums\PromotionType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPromotion;
use App\Models\Promotion;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public function resolveByCode(string $code, ?Customer $customer): Promotion
    {
        $promotion = Promotion::where('code', $code)->first();

        if (! $promotion) {
            throw ValidationException::withMessages(['promotion_code' => 'Mã khuyến mãi không tồn tại.']);
        }

        $this->assertEligible($promotion, $customer);

        return $promotion;
    }

    private function assertEligible(Promotion $promotion, ?Customer $customer): void
    {
        $error = fn (string $message) => throw ValidationException::withMessages(['promotion_code' => $message]);

        if (! $promotion->is_active) {
            $error('Khuyến mãi đã ngừng áp dụng.');
        }

        $now = now();
        if ($promotion->starts_at && $now->lt($promotion->starts_at)) {
            $error('Khuyến mãi chưa bắt đầu.');
        }
        if ($promotion->ends_at && $now->gt($promotion->ends_at)) {
            $error('Khuyến mãi đã hết hạn.');
        }
        if ($promotion->usage_limit !== null && $promotion->usage_count >= $promotion->usage_limit) {
            $error('Khuyến mãi đã hết lượt sử dụng.');
        }

        if ($promotion->customers()->exists()) {
            if (! $customer || ! $promotion->customers()->where('customers.id', $customer->id)->exists()) {
                $error('Khuyến mãi này chỉ áp dụng cho khách hàng được chỉ định.');
            }
        }
    }

    /**
     * $items: Collection các dòng đơn hàng dạng
     * ['product_id' => int, 'category_id' => int, 'quantity' => int, 'unit_price' => int, 'line_total' => int].
     * $orderSubtotal: tạm tính TOÀN đơn (trước khuyến mãi) để so với min_order_amount.
     */
    public function computeDiscount(Promotion $promotion, Collection $items, int $orderSubtotal): int
    {
        if ($promotion->min_order_amount && $orderSubtotal < $promotion->min_order_amount) {
            throw ValidationException::withMessages([
                'promotion_code' => 'Đơn hàng chưa đạt giá trị tối thiểu để áp dụng khuyến mãi.',
            ]);
        }

        $matched = $items->filter(fn (array $item) => match ($promotion->applies_to) {
            PromotionAppliesTo::All => true,
            PromotionAppliesTo::Category => $item['category_id'] === $promotion->target_id,
            PromotionAppliesTo::Product => $item['product_id'] === $promotion->target_id,
        });

        if ($matched->isEmpty()) {
            throw ValidationException::withMessages([
                'promotion_code' => 'Không có sản phẩm nào trong đơn phù hợp với khuyến mãi này.',
            ]);
        }

        $matchedSubtotal = (int) $matched->sum('line_total');
        $matchedQuantity = (int) $matched->sum('quantity');

        return match ($promotion->type) {
            PromotionType::Percentage => (int) min($matchedSubtotal, intdiv($matchedSubtotal * $promotion->value, 100)),
            PromotionType::FixedAmount => (int) min($matchedSubtotal, $promotion->value),
            PromotionType::BuyXGetY => $this->computeBuyXGetY($promotion, $matched, $matchedQuantity, $matchedSubtotal),
        };
    }

    private function computeBuyXGetY(Promotion $promotion, Collection $matched, int $matchedQuantity, int $matchedSubtotal): int
    {
        if (! $promotion->buy_quantity || ! $promotion->get_quantity) {
            return 0;
        }

        $sets = intdiv($matchedQuantity, $promotion->buy_quantity);
        $freeQuantity = min($sets * $promotion->get_quantity, $matchedQuantity);

        if ($freeQuantity <= 0) {
            return 0;
        }

        $cheapestUnitPrice = (int) $matched->min('unit_price');

        return (int) min($matchedSubtotal, $freeQuantity * $cheapestUnitPrice);
    }

    public function applyToOrder(Order $order, Promotion $promotion, int $discountAmount): void
    {
        OrderPromotion::create([
            'order_id' => $order->id,
            'promotion_id' => $promotion->id,
            'discount_amount' => $discountAmount,
        ]);

        $promotion->increment('usage_count');

        if ($order->customer_id) {
            $promotion->customers()->updateExistingPivot($order->customer_id, ['used_at' => now()]);
        }
    }
}
