<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLayawayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'fecha_vencimiento' => ['nullable', 'date_format:Y-m-d'],
            'vigencia_dias' => ['nullable', 'integer', 'min:1'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'global_discount_type' => ['nullable', 'string', 'in:percent,amount'],
            'global_discount_value' => ['nullable', 'numeric', 'min:0'],

            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1'],

            // Pago inicial opcional
            'payments' => ['sometimes', 'array', 'max:10'],
            'payments.*.method' => ['required_with:payments', 'string', 'in:cash,card,transfer,other'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
            'payments.*.reference' => ['nullable', 'string', 'max:120'],
            'payments.*.payer_name' => ['nullable', 'string', 'max:180'],
        ];
    }
}
