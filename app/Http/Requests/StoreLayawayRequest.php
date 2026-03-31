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
            'vigencia_dias' => ['required', 'integer', 'min:1'],

            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1'],

            // Pago inicial opcional
            'payments' => ['sometimes', 'array', 'max:10'],
            'payments.*.method' => ['required_with:payments', 'string', 'in:cash,card,transfer,other'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
            'payments.*.reference' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'vigencia_dias.required' => 'La vigencia es obligatoria.',
            'vigencia_dias.integer' => 'La vigencia debe ser un numero entero de dias.',
            'vigencia_dias.min' => 'La vigencia debe ser mayor a 0 dias.',
        ];
    }
}
