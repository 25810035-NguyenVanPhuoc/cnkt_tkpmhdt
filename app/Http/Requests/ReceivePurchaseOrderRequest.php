<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrderItem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.imei_serials' => ['nullable', 'array'],
            'items.*.imei_serials.*' => ['string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('items', []) as $index => $item) {
                $poItem = PurchaseOrderItem::with('product')->find($item['purchase_order_item_id'] ?? null);

                if (! $poItem || ! $poItem->product->is_serialized) {
                    continue;
                }

                $serials = $item['imei_serials'] ?? [];
                if (count($serials) !== (int) ($item['quantity'] ?? 0)) {
                    $validator->errors()->add(
                        "items.$index.imei_serials",
                        'Số lượng IMEI/serial phải khớp với quantity cho sản phẩm quản lý theo serial.'
                    );
                }
            }
        });
    }
}
