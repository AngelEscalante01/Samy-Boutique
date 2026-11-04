<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductPhotosRequest extends FormRequest
{
    use Concerns\ProductImageAssociationRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            ...$this->imageAssociationRules(),
            'expected_updated_at' => ['required', 'date'],
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'image_variant_ids' => ['nullable', 'array'],
            'image_variant_ids.*' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $this->route('product')->id)],
        ];
    }
}
