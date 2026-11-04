<?php

namespace App\Http\Resources;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Recomendación: ocultar purchase_price aquí (transformer), NO en el modelo.
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canSeePurchasePrice = $user?->can('products.view_purchase_price') ?? false;

        $variants = $this->whenLoaded('variants', function () use ($canSeePurchasePrice) {
            return $this->variants
                ->map(function ($variant) use ($canSeePurchasePrice) {
                    $salePriceEffective = (float) ($variant->sale_price ?? $this->sale_price_base ?? $this->sale_price ?? 0);

                    return [
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'stock' => (int) $variant->stock,
                        'active' => (bool) $variant->active,
                        'sale_price_effective' => number_format($salePriceEffective, 2, '.', ''),
                        'sale_price' => $variant->sale_price,
                        'purchase_price' => $canSeePurchasePrice ? $variant->purchase_price : null,
                        'size' => [
                            'id' => $variant->size?->id,
                            'name' => $variant->size?->name,
                        ],
                        'color' => [
                            'id' => $variant->color?->id,
                            'name' => $variant->color?->name,
                            'hex' => $variant->color?->hex,
                        ],
                    ];
                })
                ->values();
        });

        $availableSizes = collect($variants ?? [])
            ->filter(fn ($variant) => (bool) ($variant['active'] ?? false) && (int) ($variant['stock'] ?? 0) > 0)
            ->pluck('size')
            ->filter(fn ($size) => ! empty($size['id']))
            ->unique('id')
            ->values();

        $availableColors = collect($variants ?? [])
            ->filter(fn ($variant) => (bool) ($variant['active'] ?? false) && (int) ($variant['stock'] ?? 0) > 0)
            ->pluck('color')
            ->filter(fn ($color) => ! empty($color['id']))
            ->unique('id')
            ->values();

        $stockSummary = collect($variants ?? [])
            ->filter(fn ($variant) => (bool) ($variant['active'] ?? false) && (int) ($variant['stock'] ?? 0) > 0)
            ->sum(fn ($variant) => (int) ($variant['stock'] ?? 0));

        return [
            'id' => $this->id,
            'updated_at' => $this->updated_at?->toISOString(),
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,

            'gender' => $this->gender,
            'status' => $this->status,
            'catalog_published' => (bool) $this->catalog_published,
            'sold_at' => $this->sold_at,

            'sale_price' => $this->sale_price,
            'sale_price_base' => $this->sale_price_base,
            'sold_out_at' => $this->sold_out_at,
            // Solo gerente (o quien tenga el permiso) verá el costo.
            'purchase_price' => $this->when($canSeePurchasePrice, $this->purchase_price),

            'category' => [
                'id' => $this->whenLoaded('category', fn () => $this->category->id),
                'name' => $this->whenLoaded('category', fn () => $this->category->name),
            ],
            'size' => [
                'id' => $this->whenLoaded('size', fn () => $this->size->id),
                'name' => $this->whenLoaded('size', fn () => $this->size->name),
            ],
            'color' => [
                'id' => $this->whenLoaded('color', fn () => $this->color->id),
                'name' => $this->whenLoaded('color', fn () => $this->color->name),
                'hex' => $this->whenLoaded('color', fn () => $this->color->hex),
            ],
            'images' => $this->whenLoaded('images', function () use ($request) {
                $images = $this->images;
                if ($request->routeIs('public.catalog.*')) {
                    $availableIds = $this->variants->filter(fn ($variant) => $variant->active && $variant->stock > 0)->pluck('id')->all();
                    $images = $images->filter(fn ($image) => $image->visibleForVariants($availableIds));
                }

                return $images->map(fn ($img) => [
                'id' => $img->id,
                'product_variant_id' => $img->product_variant_id,
                'variant_ids' => $img->assignedVariantIds(),
                'path' => ProductImage::normalizePath($img->path),
                'image_url' => $img->url,
                'url' => $img->url,
                'optimized_url' => $img->derivatives->firstWhere('kind', 'optimized')?->url ?: $img->url,
                'thumbnail_url' => $img->derivatives->firstWhere('kind', 'thumbnail')?->url ?: $img->url,
                'generation' => $img->generation,
                'sort' => $img->sort,
                ])->values();
            }),
            'occasions' => $this->whenLoaded('occasions', fn () => $this->occasions->map(fn ($occasion) => [
                'id' => $occasion->id,
                'name' => $occasion->name,
            ])),
            'variants' => $variants,
            'availableSizes' => $availableSizes,
            'availableColors' => $availableColors,
            'availability' => [
                'total_stock' => (int) $stockSummary,
                'has_stock' => (int) $stockSummary > 0,
                'is_low_stock' => (int) $stockSummary > 0 && (int) $stockSummary <= 3,
            ],
        ];
    }
}
