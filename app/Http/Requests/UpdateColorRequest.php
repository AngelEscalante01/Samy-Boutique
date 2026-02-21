<?php

namespace App\Http\Requests;

use App\Models\Color;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var Color $color */
        $color = $this->route('color');

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('colors', 'name')->ignore($color->id)],
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
