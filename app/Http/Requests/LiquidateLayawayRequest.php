<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LiquidateLayawayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'payments' => ['nullable', 'array', 'max:10'],
            'payments.*.method' => ['required_with:payments.*.amount', 'string', 'in:cash,card,transfer,other'],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0.01'],
            'payments.*.reference' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $layaway = $this->route('layaway');
            $remaining = (float) ($layaway?->balance ?? 0);

            $payments = collect($this->input('payments', []))
                ->filter(fn ($payment) => (float) ($payment['amount'] ?? 0) > 0);

            if ($remaining > 0 && $payments->isEmpty()) {
                $validator->errors()->add('payments', 'Debes capturar al menos un pago para liquidar el saldo pendiente.');
            }
        });
    }
}
