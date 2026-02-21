<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Se controla por middleware/permisos en rutas.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:80', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],

            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'gender' => ['required', 'string', 'in:dama,caballero,unisex'],
            'size_id' => ['required', 'integer', 'exists:sizes,id'],
            'color_id' => ['required', 'integer', 'exists:colors,id'],

            'purchase_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],

            'status' => ['required', 'string', 'in:disponible,apartado,vendido,cancelado'],
            'sold_at' => ['nullable', 'date'],

            // Imágenes
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'images.max' => 'Máximo 10 imágenes por producto.',
            'images.*.max' => 'Cada imagen debe pesar máximo 4MB.',
            'images.*.mimes' => 'Las imágenes deben ser JPG, PNG o WEBP.',
        ];
    }
}
