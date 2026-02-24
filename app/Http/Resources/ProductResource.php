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

        $variants = $this->whenLoaded('variants', function () {
            return $this->variants
                ->map(function ($variant) {
                    $salePriceEffective = (float) ($variant->sale_price ?? $this->sale_price_base ?? $this->sale_price ?? 0);

                    return [
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'stock' => (int) $variant->stock,
                        'active' => (bool) $variant->active,
                        'sale_price_effective' => number_format($salePriceEffective, 2, '.', ''),
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
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,

            'gender' => $this->gender,
            'status' => $this->status,
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
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'path' => ProductImage::normalizePath($img->path),
                'image_url' => asset('storage/'.ProductImage::normalizePath($img->path)),
                'url' => asset('storage/'.ProductImage::normalizePath($img->path)),
                'sort' => $img->sort,
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
