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
            'sku' => ['nullable', 'string', 'max:80', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],

            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'gender' => ['required', 'string', 'in:dama,caballero,unisex'],
            'sale_price_base' => ['nullable', 'numeric', 'min:0'],

            'variants' => ['required', 'array', 'min:1'],
            'variants.*.size_id' => ['required', 'integer', 'exists:sizes,id'],
            'variants.*.color_id' => ['required', 'integer', 'exists:colors,id'],
            'variants.*.stock' => ['required', 'integer', 'min:1'],
            'variants.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0'],

            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],

            'status' => ['nullable', 'string', 'in:disponible,apartado,vendido,cancelado'],
            'sold_at' => ['nullable', 'date'],

            // Imágenes
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'variants.required' => 'Debes agregar al menos una variante.',
            'variants.min' => 'Debes agregar al menos una variante.',
            'images.max' => 'Máximo 10 imágenes por producto.',
            'images.*.max' => 'Cada imagen debe pesar máximo 20MB.',
            'images.*.mimes' => 'Las imágenes deben ser JPG, PNG o WEBP.',
        ];
    }
}
