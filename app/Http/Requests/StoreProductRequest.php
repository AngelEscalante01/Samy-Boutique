<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ProductImageAssociationRules;
    public function authorize(): bool
    {
        // Se controla por middleware/permisos en rutas.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            ...$this->imageAssociationRules(),
            'sku' => ['prohibited'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],

            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'gender' => ['required', 'string', 'in:dama,caballero,unisex'],
            'sale_price_base' => ['nullable', 'numeric', 'min:0'],

            'variants' => ['required', 'array', 'min:1'],
            'variants.*.size_id' => ['required', 'integer', 'exists:sizes,id'],
            'variants.*.color_id' => ['required', 'integer', 'exists:colors,id'],
            'variants.*.stock' => ['required', 'integer', 'min:1'],
            'variants.*.purchase_price' => [Rule::prohibitedIf(! $this->user()?->can('products.view_purchase_price')), 'nullable', 'numeric', 'min:0'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0'],

            'purchase_price' => [Rule::prohibitedIf(! $this->user()?->can('products.view_purchase_price')), 'nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],

            'status' => ['nullable', 'string', 'in:disponible,apartado,vendido,cancelado'],
            'sold_at' => ['nullable', 'date'],

            'occasion_ids' => ['nullable', 'array'],
            'occasion_ids.*' => ['integer', 'distinct', 'exists:occasions,id'],

            // Imágenes
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'variants.required' => 'Debes agregar al menos una variante.',
            'variants.min' => 'Debes agregar al menos una variante.',
            'images.max' => 'Máximo 10 imágenes por producto.',
            'images.*.max' => 'Cada imagen debe pesar máximo 4MB.',
            'images.*.mimes' => 'Las imágenes deben ser JPG, PNG o WEBP.',
        ];
    }
}
