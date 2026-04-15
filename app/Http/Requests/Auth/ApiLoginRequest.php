<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ApiLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc,dns', 'max:190'],
            'password' => ['required', 'string', 'min:6', 'max:190'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
