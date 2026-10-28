<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:to_warehouse_id'],
            'to_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'product_unit_ids' => ['nullable', 'array', 'min:1'],
            'product_unit_ids.*' => ['integer', 'exists:product_units,id'],
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
                if (empty($this->input('product_unit_ids'))) {
                    $validator->errors()->add('product_unit_ids', 'Sản phẩm quản lý theo serial cần danh sách product_unit_ids.');
                }
            } elseif (empty($this->input('quantity'))) {
                $validator->errors()->add('quantity', 'Sản phẩm quản lý theo số lượng cần nhập quantity.');
            }
        });
    }
}
