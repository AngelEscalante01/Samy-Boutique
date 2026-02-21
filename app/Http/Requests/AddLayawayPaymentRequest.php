<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddLayawayPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'string', 'in:cash,card,transfer,other'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
