<?php

namespace App\Http\Resources\Api;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sellableVariants = $this->whenLoaded('variants', function () {
            return $this->variants
                ->filter(fn ($variant) => (bool) $variant->active && (int) $variant->stock > 0)
                ->map(function ($variant) {
                    $salePrice = (float) ($variant->sale_price ?? $this->sale_price_base ?? $this->sale_price ?? 0);
                    $originalPrice = (float) ($this->sale_price_base ?? $this->sale_price ?? $salePrice);

                    return [
                        'id' => (int) $variant->id,
                        'sku' => $variant->sku,
                        'stock' => (int) $variant->stock,
                        'sale_price' => round($salePrice, 2),
                        'original_price' => round($originalPrice, 2),
                        'has_discount' => round($originalPrice, 2) > round($salePrice, 2),
                        'size' => $variant->size ? [
                            'id' => (int) $variant->size->id,
                            'name' => $variant->size->name,
                        ] : null,
                        'color' => $variant->color ? [
                            'id' => (int) $variant->color->id,
                            'name' => $variant->color->name,
                            'hex' => $variant->color->hex,
                        ] : null,
                    ];
                })
                ->values();
        });

        $variantsCollection = collect($sellableVariants ?? []);
        $defaultVariant = $variantsCollection->first();
        $stockAvailable = (int) $variantsCollection->sum('stock');

        $mainImagePath = null;
        if ($this->relationLoaded('images') && $this->images->isNotEmpty()) {
            $mainImagePath = ProductImage::normalizePath((string) $this->images->first()->path);
        }

        $fallbackSalePrice = (float) ($this->sale_price_base ?? $this->sale_price ?? 0);

        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'status' => $this->status,
            'category' => $this->category ? [
                'id' => (int) $this->category->id,
                'name' => $this->category->name,
            ] : null,
            'sale_price' => $defaultVariant['sale_price'] ?? round($fallbackSalePrice, 2),
            'original_price' => $defaultVariant['original_price'] ?? round($fallbackSalePrice, 2),
            'has_discount' => $defaultVariant['has_discount'] ?? false,
            'stock_available' => $stockAvailable,
            'main_image' => [
                'path' => $mainImagePath,
                'url' => $mainImagePath ? asset('storage/'.$mainImagePath) : null,
            ],
            'default_variant' => $defaultVariant,
            'sellable_variants' => $variantsCollection->all(),
            'search_index' => [
                'name' => $this->name,
                'sku' => $this->sku,
                'variant_skus' => $variantsCollection->pluck('sku')->filter()->values()->all(),
            ],
        ];
    }
}
