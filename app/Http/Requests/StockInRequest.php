<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StockInRequest extends FormRequest
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
            'quantity' => ['nullable', 'integer', 'min:1'],
            'imei_serials' => ['nullable', 'array', 'min:1'],
            'imei_serials.*' => ['string', 'distinct', 'unique:product_units,imei_serial'],
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
                if (empty($this->input('imei_serials'))) {
                    $validator->errors()->add('imei_serials', 'Sản phẩm quản lý theo serial/IMEI cần nhập danh sách imei_serials.');
                }
            } elseif (empty($this->input('quantity'))) {
                $validator->errors()->add('quantity', 'Sản phẩm quản lý theo số lượng cần nhập quantity.');
            }
        });
    }
}
