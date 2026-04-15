<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApiSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $paymentMethod = $this->input('payment_method', $this->input('metodo_pago'));
        $paymentAmount = $this->input('payment_amount', $this->input('monto_pago'));
        $paymentReference = $this->input('payment_reference', $this->input('referencia_pago'));
        $dineroRecibido = $this->input('dinero_recibido', $this->input('monto_recibido'));

        $payload = [];

        if ($dineroRecibido !== null) {
            $payload['dinero_recibido'] = $dineroRecibido;
        }

        if ($paymentMethod !== null && ! $this->has('payments')) {
            $payload['payments'] = [[
                'method' => $paymentMethod,
                'amount' => $paymentAmount,
                'reference' => $paymentReference,
            ]];
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],

            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],

            'items.*.discount_type' => ['nullable', 'string', 'in:amount,percent'],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0', 'required_with:items.*.discount_type'],

            'global_discount_type' => ['nullable', 'string', 'in:amount,percent'],
            'global_discount_value' => ['nullable', 'numeric', 'min:0', 'required_with:global_discount_type'],

            'coupon_code' => ['nullable', 'string', 'max:60'],

            'payments' => ['required', 'array', 'min:1', 'max:10'],
            'payments.*.method' => ['required', 'string', 'in:cash,card,transfer,other'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.reference' => ['nullable', 'string', 'max:120'],

            'payment_method' => ['nullable', 'string', 'in:cash,card,transfer,other'],
            'payment_amount' => ['nullable', 'numeric', 'min:0.01'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'metodo_pago' => ['nullable', 'string', 'in:cash,card,transfer,other'],
            'monto_pago' => ['nullable', 'numeric', 'min:0.01'],
            'referencia_pago' => ['nullable', 'string', 'max:120'],

            'dinero_recibido' => ['required', 'numeric', 'min:0.01'],
            'monto_recibido' => ['nullable', 'numeric', 'min:0.01'],

            'observaciones' => ['nullable', 'string', 'max:500'],
            'sucursal_id' => ['nullable', 'integer'],
            'sucursal_nombre' => ['nullable', 'string', 'max:120'],
        ];
    }
}
