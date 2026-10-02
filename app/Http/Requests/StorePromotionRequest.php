<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:promotions,code'],
            'type' => ['required', Rule::in(['percentage', 'fixed_amount', 'buy_x_get_y'])],
            'value' => ['nullable', 'integer', 'min:0'],
            'buy_quantity' => ['nullable', 'integer', 'min:1'],
            'get_quantity' => ['nullable', 'integer', 'min:1'],
            'min_order_amount' => ['nullable', 'integer', 'min:0'],
            'applies_to' => ['required', Rule::in(['all', 'category', 'product'])],
            'target_id' => ['nullable', 'integer'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = $this->input('type');
            $appliesTo = $this->input('applies_to');

            if (in_array($type, ['percentage', 'fixed_amount'], true) && $this->input('value') === null) {
                $validator->errors()->add('value', 'Mức giảm (value) là bắt buộc với loại khuyến mãi percentage/fixed_amount.');
            }

            if ($type === 'buy_x_get_y' && (! $this->input('buy_quantity') || ! $this->input('get_quantity'))) {
                $validator->errors()->add('buy_quantity', 'buy_quantity và get_quantity là bắt buộc với loại khuyến mãi buy_x_get_y.');
            }

            if (in_array($appliesTo, ['category', 'product'], true) && ! $this->input('target_id')) {
                $validator->errors()->add('target_id', 'target_id là bắt buộc khi applies_to là category hoặc product.');
            }
        });
    }
}
