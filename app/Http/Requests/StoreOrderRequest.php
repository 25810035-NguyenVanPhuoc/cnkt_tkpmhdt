<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_phone' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'shipping_fee' => ['sometimes', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount_amount' => ['sometimes', 'integer', 'min:0'],
            'items.*.product_unit_ids' => ['nullable', 'array'],
            'items.*.product_unit_ids.*' => ['integer', 'exists:product_units,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('items', []) as $index => $item) {
                $product = Product::find($item['product_id'] ?? null);

                if (! $product) {
                    continue;
                }

                if ($product->is_serialized) {
                    $unitIds = $item['product_unit_ids'] ?? [];
                    if (count($unitIds) !== (int) ($item['quantity'] ?? 0)) {
                        $validator->errors()->add(
                            "items.$index.product_unit_ids",
                            'Số lượng product_unit_ids phải khớp với quantity cho sản phẩm quản lý theo serial.'
                        );
                    }
                }
            }
        });
    }
}
