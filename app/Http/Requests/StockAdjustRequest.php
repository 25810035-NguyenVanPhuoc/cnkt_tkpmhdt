<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StockAdjustRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity_after' => ['nullable', 'integer', 'min:0'],
            'product_unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'status' => ['nullable', 'in:in_stock,damaged'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $product = Product::find($this->input('product_id'));

            if (! $product) {
                return;
            }

            if ($product->is_serialized) {
                if (empty($this->input('product_unit_id')) || empty($this->input('status'))) {
                    $validator->errors()->add('product_unit_id', 'Sản phẩm quản lý theo serial cần product_unit_id và status (in_stock/damaged).');
                }
            } elseif ($this->input('quantity_after') === null) {
                $validator->errors()->add('quantity_after', 'Sản phẩm quản lý theo số lượng cần nhập quantity_after.');
            }
        });
    }
}
