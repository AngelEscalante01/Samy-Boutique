<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ProductImageAssociationRules;
    public function authorize(): bool
    {
        // Se controla por middleware/permisos en rutas.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : $product;

        return [
            ...$this->imageAssociationRules(editing: true),
            'sku' => ['nullable', 'string', 'max:80', Rule::in([(string) $product->sku])],
            'expected_updated_at' => ['nullable', 'date'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],

            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'gender' => ['required', 'string', 'in:dama,caballero,unisex'],
            'sale_price_base' => ['nullable', 'numeric', 'min:0'],

            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('product_variants', 'id')->where('product_id', $productId)],
            'variants.*.size_id' => ['required', 'integer', 'exists:sizes,id'],
            'variants.*.color_id' => ['required', 'integer', 'exists:colors,id'],
            'variants.*.stock' => ['required', 'integer', 'min:0'],
            'variants.*.purchase_price' => [Rule::prohibitedIf(! $this->user()?->can('products.view_purchase_price')), 'nullable', 'numeric', 'min:0'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0'],

            'purchase_price' => [Rule::prohibitedIf(! $this->user()?->can('products.view_purchase_price')), 'nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],

            'status' => ['nullable', 'string', 'in:disponible,apartado,vendido,cancelado'],
            'catalog_published' => ['nullable', 'boolean'],
            'sold_at' => ['nullable', 'date'],

            'occasion_ids' => ['nullable', 'array'],
            'occasion_ids.*' => ['integer', 'distinct', 'exists:occasions,id'],

            // Imágenes adicionales (no obliga a volver a subir)
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'image_variant_ids' => ['nullable', 'array'],
            'image_variant_ids.*' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $productId)],
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
