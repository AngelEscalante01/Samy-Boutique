<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelSaleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'cancel_reason' => is_string($this->cancel_reason) ? trim($this->cancel_reason) : $this->cancel_reason,
            'return_condition' => $this->cancel_type === 'devolucion'
                ? $this->return_condition
                : null,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'cancel_reason' => ['required', 'string', 'min:5'],
            'cancel_type' => ['required', 'string', 'in:ajuste,devolucion'],
            'inventory_action' => ['required', 'string', 'in:no_regresar,regresar_disponible,marcar_danado'],
            'return_condition' => ['nullable', 'string', 'in:buena,danada', 'required_if:cancel_type,devolucion'],
        ];
    }

    public function messages(): array
    {
        return [
            'cancel_reason.required' => 'El motivo de cancelación es obligatorio.',
            'cancel_reason.min' => 'El motivo debe tener al menos 5 caracteres.',
            'cancel_type.required' => 'Debes seleccionar el tipo de cancelación.',
            'cancel_type.in' => 'El tipo de cancelación no es válido.',
            'inventory_action.required' => 'Debes seleccionar la acción de inventario.',
            'inventory_action.in' => 'La acción de inventario no es válida.',
            'return_condition.required_if' => 'Debes indicar la condición del producto para una devolución.',
            'return_condition.in' => 'La condición del producto no es válida.',
        ];
    }
}
