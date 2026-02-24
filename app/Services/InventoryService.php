<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function scopeSellableProducts(Builder $query): Builder
    {
        return $query->whereHas('variants', function (Builder $variantQuery) {
            $variantQuery
                ->where('active', true)
                ->where('stock', '>', 0);
        });
    }

    public function effectiveSalePrice(ProductVariant $variant): float
    {
        $product = $variant->relationLoaded('product') ? $variant->product : $variant->product()->first();

        if (! $product) {
            return (float) ($variant->sale_price ?? 0);
        }

        return (float) ($variant->sale_price ?? $product->sale_price_base ?? $product->sale_price ?? 0);
    }

    public function decrementVariantStock(ProductVariant $variant, int $qty): void
    {
        if ($qty < 1) {
            throw ValidationException::withMessages([
                'items' => ['La cantidad debe ser mayor a cero.'],
            ]);
        }

        if (! $variant->active || $variant->stock < $qty) {
            throw ValidationException::withMessages([
                'items' => ["Stock insuficiente para la variante {$variant->id}."],
            ]);
        }

        $variant->stock = (int) $variant->stock - $qty;
        $variant->save();

        $this->updateSoldOutAt($variant->product_id);
    }

    public function incrementVariantStock(ProductVariant $variant, int $qty): void
    {
        if ($qty < 1) {
            return;
        }

        $variant->stock = (int) $variant->stock + $qty;
        $variant->save();

        $this->updateSoldOutAt($variant->product_id);
    }

    public function updateSoldOutAt(int $productId): void
    {
        $totalStock = (int) ProductVariant::query()
            ->where('product_id', $productId)
            ->where('active', true)
            ->sum('stock');

        $product = Product::query()->find($productId);
        if (! $product) {
            return;
        }

        if ($totalStock <= 0) {
            if ($product->sold_out_at === null) {
                $product->update([
                    'sold_out_at' => now(),
                    'status' => 'vendido',
                    'sold_at' => $product->sold_at ?? now(),
                ]);
            }

            return;
        }

        if ($product->sold_out_at !== null || $product->status !== 'disponible') {
            $product->update([
                'sold_out_at' => null,
                'status' => 'disponible',
            ]);
        }
    }

    public function updateManySoldOutAt(Collection $productIds): void
    {
        $productIds->unique()->each(fn (int $productId) => $this->updateSoldOutAt($productId));
    }

    public function refreshProductSoldOut(int $productId): void
    {
        $this->updateSoldOutAt($productId);
    }

    public function refreshManyProductsSoldOut(Collection $productIds): void
    {
        $this->updateManySoldOutAt($productIds);
    }
}
