<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLayawayVigenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'vigencia_dias' => ['required', 'integer', 'min:1'],
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
