<?php

namespace App\Http\Requests;

use App\Models\Size;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var Size $size */
        $size = $this->route('size');

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('sizes', 'name')->ignore($size->id)],
            'active' => ['required', 'boolean'],
        ];
    }
}
