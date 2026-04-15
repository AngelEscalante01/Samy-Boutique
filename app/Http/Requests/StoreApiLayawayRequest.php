<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApiLayawayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('vigencia_dias') && ! $this->has('fecha_vencimiento')) {
            $this->merge(['vigencia_dias' => 30]);
        }

        $initialAmount = $this->input('anticipo_inicial', $this->input('abono_inicial'));
        $initialMethod = $this->input('metodo_anticipo', $this->input('metodo_abono_inicial', 'cash'));
        $initialReference = $this->input('referencia_anticipo');

        if ($initialAmount !== null && ! $this->has('payments')) {
            $this->merge([
                'payments' => [[
                    'method' => $initialMethod,
                    'amount' => $initialAmount,
                    'reference' => $initialReference,
                ]],
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],

            'vigencia_dias' => ['nullable', 'integer', 'min:1'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:today'],

            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1'],

            'payments' => ['sometimes', 'array', 'max:10'],
            'payments.*.method' => ['required_with:payments', 'string', 'in:cash,card,transfer,other'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
            'payments.*.reference' => ['nullable', 'string', 'max:120'],

            'anticipo_inicial' => ['nullable', 'numeric', 'min:0.01'],
            'abono_inicial' => ['nullable', 'numeric', 'min:0.01'],
            'metodo_anticipo' => ['nullable', 'string', 'in:cash,card,transfer,other'],
            'metodo_abono_inicial' => ['nullable', 'string', 'in:cash,card,transfer,other'],
            'referencia_anticipo' => ['nullable', 'string', 'max:120'],

            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }
}
