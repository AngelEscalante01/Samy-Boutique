<?php

namespace App\Http\Resources\Api;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $includeInactive = filter_var($request->query('include_inactive', false), FILTER_VALIDATE_BOOL);
        $productUpdatedAt = $this->updated_at ?? $this->created_at;

        $allVariants = $this->relationLoaded('variants')
            ? $this->variants
            : collect();

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
                        'active' => (bool) $variant->active,
                        'sale_price' => round($salePrice, 2),
                        'original_price' => round($originalPrice, 2),
                        'has_discount' => round($originalPrice, 2) > round($salePrice, 2),
                        'updated_at' => ($variant->updated_at ?? $variant->created_at)?->toISOString(),
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
        $hasSellableVariants = $variantsCollection->isNotEmpty();
        $isStatusActive = $this->status === 'disponible';
        $isSellable = $isStatusActive && $hasSellableVariants;
        $isInactive = ! $isSellable;
        $inactiveReason = null;

        if ($isInactive) {
            $inactiveReason = ! $isStatusActive
                ? 'status_'.$this->status
                : 'no_sellable_variants';
        }

        $mainImagePath = null;
        if ($this->relationLoaded('images') && $this->images->isNotEmpty()) {
            $visibleImage = $this->images->first(fn ($image) => $image->deleted_at === null) ?? $this->images->first();
            $mainImagePath = ProductImage::normalizePath((string) ($visibleImage?->path ?? ''));
        }

        $fallbackSalePrice = (float) ($this->sale_price_base ?? $this->sale_price ?? 0);
        $latestVariantUpdate = $allVariants->isNotEmpty()
            ? $allVariants
                ->map(fn ($variant) => $variant->updated_at ?? $variant->created_at)
                ->filter()
                ->max()
            : null;
        $latestImageUpdate = $this->relationLoaded('images')
            ? $this->images
                ->map(fn ($image) => $image->updated_at ?? $image->created_at)
                ->filter()
                ->max()
            : null;
        $latestImageDeletion = $this->relationLoaded('images')
            ? $this->images->max('deleted_at')
            : null;
        $syncUpdatedAt = collect([
            $productUpdatedAt,
            $latestVariantUpdate,
            $latestImageUpdate,
            $latestImageDeletion,
        ])->filter()->max();

        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'status' => $this->status,
            'is_active' => ! $isInactive,
            'is_sellable' => $isSellable,
            'is_deleted' => false,
            'deleted_at' => null,
            'sync_action' => $isInactive ? 'remove' : 'upsert',
            'inactive_reason' => $isInactive ? $inactiveReason : null,
            'gender' => $this->gender,
            'category_id' => $this->category_id ? (int) $this->category_id : null,
            'category' => $this->category ? [
                'id' => (int) $this->category->id,
                'name' => $this->category->name,
                'active' => (bool) ($this->category->active ?? true),
            ] : null,
            'updated_at' => $productUpdatedAt?->toISOString(),
            'sync_updated_at' => $syncUpdatedAt?->toISOString(),
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
            'variants' => $includeInactive ? $allVariants->map(function ($variant) {
                $salePrice = (float) ($variant->sale_price ?? $this->sale_price_base ?? $this->sale_price ?? 0);
                $originalPrice = (float) ($this->sale_price_base ?? $this->sale_price ?? $salePrice);

                return [
                    'id' => (int) $variant->id,
                    'sku' => $variant->sku,
                    'stock' => (int) $variant->stock,
                    'active' => (bool) $variant->active,
                    'sale_price' => round($salePrice, 2),
                    'original_price' => round($originalPrice, 2),
                    'updated_at' => ($variant->updated_at ?? $variant->created_at)?->toISOString(),
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
            })->values()->all() : null,
            'search_index' => [
                'name' => $this->name,
                'sku' => $this->sku,
                'variant_skus' => $variantsCollection->pluck('sku')->filter()->values()->all(),
            ],
        ];
    }
}
