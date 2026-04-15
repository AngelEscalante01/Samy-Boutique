<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApiCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('active')) {
            $this->merge(['active' => true]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:customers,phone'],
            'active' => ['required', 'boolean'],
        ];
    }
}
