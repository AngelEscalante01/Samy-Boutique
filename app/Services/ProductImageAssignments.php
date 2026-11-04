<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Validation\ValidationException;

/** Maps browser draft keys to saved variant IDs without allocating extra SKUs. */
class ProductImageAssignments
{
    public function resolve(array $keys, array $variantMap): array
    {
        if ($keys === ['all']) return [];
        if ($keys === [] || in_array('all', $keys, true)) {
            throw ValidationException::withMessages(['images' => 'Elige foto general o una o varias variantes para cada foto.']);
        }
        $ids = [];
        foreach ($keys as $key) {
            if (! isset($variantMap[$key])) {
                throw ValidationException::withMessages(['images' => 'Una variante de las fotos ya no está en el producto. Revisa su asignación.']);
            }
            $ids[] = (int) $variantMap[$key];
        }

        return array_values(array_unique($ids));
    }

    public function uploads(array $data, array $variantMap): array
    {
        if (! array_key_exists('image_variant_keys', $data)) return $data['image_variant_ids'] ?? [];

        return array_map(fn ($keys) => $this->resolve($keys, $variantMap), $data['image_variant_keys']);
    }

    public function updateExisting(Product $product, array $data, array $variantMap): void
    {
        foreach ($data['existing_image_variant_keys'] ?? [] as $imageId => $keys) {
            $image = $product->images()->whereKey($imageId)->lockForUpdate()->first();
            if (! $image) {
                throw ValidationException::withMessages(['images' => 'Una foto cambió o no pertenece a este producto. Recarga antes de guardar.']);
            }
            $this->assign($image, $this->resolve($keys, $variantMap));
        }
        if (array_key_exists('image_order', $data)) {
            $images = $product->images()->orderBy('id')->lockForUpdate()->get();
            $ids = array_map('intval', $data['image_order']);
            if ($images->pluck('id')->sort()->values()->all() !== collect($ids)->sort()->values()->all()) {
                throw ValidationException::withMessages(['image_order' => 'La galería cambió. Recarga antes de ordenar las fotos.']);
            }
            foreach ($ids as $index => $id) $images->firstWhere('id', $id)->update(['sort' => $index]);
        }
    }

    public function assign(ProductImage $image, array $ids): void
    {
        // Keep the first association readable by existing Android clients.
        $image->update(['product_variant_id' => $ids[0] ?? null, 'variant_ids' => array_values($ids)]);
    }
}
