<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddApiLayawayPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $method = $this->input('method', $this->input('payment_method', 'cash'));
        $amount = $this->input('amount', $this->input('monto'));
        $reference = $this->input('reference', $this->input('referencia'));
        $paidAt = $this->input('paid_at', $this->input('fecha'));
        $observacion = $this->input('observacion', $this->input('observaciones'));

        $payload = [
            'method' => $method,
        ];

        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        if ($reference !== null) {
            $payload['reference'] = $reference;
        }

        if ($paidAt !== null) {
            $payload['paid_at'] = $paidAt;
        }

        if ($observacion !== null) {
            $payload['observacion'] = $observacion;
        }

        if (! $this->has('auto_liquidate')) {
            $payload['auto_liquidate'] = true;
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'string', 'in:cash,card,transfer,other'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:120'],
            'paid_at' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'auto_liquidate' => ['nullable', 'boolean'],
        ];
    }
}
