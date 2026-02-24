<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],

            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1'],

            'items.*.discount_type' => ['nullable', 'string', 'in:amount,percent'],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0', 'required_with:items.*.discount_type'],

            'global_discount_type' => ['nullable', 'string', 'in:amount,percent'],
            'global_discount_value' => ['nullable', 'numeric', 'min:0', 'required_with:global_discount_type'],

            'coupon_code' => ['nullable', 'string', 'max:60'],
        ];
    }
}
