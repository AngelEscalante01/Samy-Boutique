<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'min:2', 'max:80', Rule::unique('categories', 'name')->ignore($category->id)],
            'sku_prefix' => ['nullable', 'string', 'regex:/^[A-Za-z][A-Za-z0-9]{1,11}$/'],
            'active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe una categoría con ese nombre.',
            'name.min'    => 'El nombre debe tener al menos 2 caracteres.',
        ];
    }
}
