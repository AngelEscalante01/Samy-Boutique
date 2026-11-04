<?php

namespace App\Http\Requests\Concerns;

trait ProductImageAssociationRules
{
    protected function imageAssociationRules(bool $editing = false): array
    {
        return [
            'variants.*.client_key' => ['nullable', 'string', 'max:80', 'distinct', 'regex:/^(?:v_[1-9][0-9]*|n_[A-Za-z0-9_-]+)$/D'],
            'variants.*.expected_stock' => ['nullable', 'integer', 'min:0'],
            'image_variant_keys' => ['sometimes', 'array', 'max:10'],
            'image_variant_keys.*' => ['required', 'array', 'min:1', 'max:100'],
            'image_variant_keys.*.*' => ['required', 'string', 'max:80'],
            ...($editing ? [
                'existing_image_variant_keys' => ['sometimes', 'array', 'max:100'],
                'existing_image_variant_keys.*' => ['required', 'array', 'min:1', 'max:100'],
                'existing_image_variant_keys.*.*' => ['required', 'string', 'max:80'],
                'image_order' => ['sometimes', 'array', 'max:100'],
                'image_order.*' => ['required', 'integer', 'distinct'],
            ] : []),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) return;
            $product = $this->route('product');
            foreach ($this->input('variants', []) as $index => $variant) {
                if (empty($variant['client_key'])) continue;
                if ((! empty($variant['id']) && $variant['client_key'] !== 'v_'.$variant['id'])
                    || (empty($variant['id']) && ! str_starts_with($variant['client_key'], 'n_'))) {
                    $validator->errors()->add("variants.$index.client_key", 'Recarga las variantes antes de guardar.');
                }
            }
            $keys = collect($this->input('variants', []))->pluck('client_key')->filter()->all();
            if (is_object($product)) {
                $keys = [...$keys, ...$product->variants()->pluck('id')->map(fn ($id) => 'v_'.$id)->all()];
            }
            foreach (['image_variant_keys', 'existing_image_variant_keys'] as $field) {
                foreach ($this->input($field, []) as $index => $assigned) {
                    if ($assigned !== ['all'] && (in_array('all', $assigned, true) || array_diff($assigned, $keys) !== [])) {
                        $validator->errors()->add("$field.$index", 'Elige foto general o variantes de este producto.');
                    }
                }
            }
            if ($this->has('image_variant_keys') && array_keys($this->input('image_variant_keys')) !== array_keys($this->file('images', []))) {
                $validator->errors()->add('images', 'Revisa la asignación de todas las fotos nuevas.');
            }
        });
    }
}
