<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'max:255'],
        ];
    }
}
