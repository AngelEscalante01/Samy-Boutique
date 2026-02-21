<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:80', 'unique:colors,name'],
            'hex'    => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'hex.regex' => 'El color debe ser un valor HEX válido (ej: #FF00AA).',
        ];
    }
}
