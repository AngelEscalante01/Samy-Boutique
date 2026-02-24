<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],

            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            // Descuento manual por item
            'items.*.discount_type' => ['nullable', 'string', 'in:amount,percent'],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0', 'required_with:items.*.discount_type'],

            // Descuento global manual
            'global_discount_type' => ['nullable', 'string', 'in:amount,percent'],
            'global_discount_value' => ['nullable', 'numeric', 'min:0', 'required_with:global_discount_type'],

            // Cupón
            'coupon_code' => ['nullable', 'string', 'max:60'],

            // Pagos
            'payments' => ['required', 'array', 'min:1', 'max:10'],
            'payments.*.method' => ['required', 'string', 'in:cash,card,transfer,other'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
