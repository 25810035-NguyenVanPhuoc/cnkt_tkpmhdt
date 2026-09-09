<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'sku' => ['sometimes', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($productId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId)],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'cost_price' => ['sometimes', 'integer', 'min:0'],
            'sale_price' => ['sometimes', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_serialized' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
