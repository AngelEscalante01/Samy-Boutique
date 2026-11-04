<?php

namespace App\Services;

use App\Models\LayawayItem;
use App\Models\ProductStockEpisode;
use App\Models\ProductVariant;
use App\Models\Sale;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductStockEpisodes
{
    /** Called only after a server-confirmed sale inside its inventory transaction. */
    public function recordConfirmedSale(Sale $sale, Collection $variantIds): void
    {
        foreach ($variantIds->unique()->sort()->values() as $id) {
            $variant = ProductVariant::query()->whereKey((int) $id)->lockForUpdate()->first();
            if (! $variant || ! $variant->active || (int) $variant->stock !== 0) continue;
            if (LayawayItem::query()->where('product_variant_id', $variant->id)
                ->whereHas('layaway', fn ($query) => $query->where('status', 'open'))->exists()) continue;
            if (ProductStockEpisode::query()->where('product_variant_id', $variant->id)->whereNull('cancelled_at')->exists()) continue;
            ProductStockEpisode::create([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'source_sale_id' => $sale->id,
                'generation' => (string) Str::uuid(),
                'started_at' => now('UTC'),
            ]);
        }
    }

    public function cancelIfRestocked(int $productId): void
    {
        ProductStockEpisode::query()->where('product_id', $productId)->whereNull('cancelled_at')
            ->whereHas('variant', fn ($query) => $query->where('stock', '>', 0))
            ->update(['cancelled_at' => now('UTC'), 'cancel_reason' => 'restocked']);
    }

    public function cancelSale(int $saleId): void
    {
        ProductStockEpisode::query()->where('source_sale_id', $saleId)->whereNull('cancelled_at')
            ->update(['cancelled_at' => now('UTC'), 'cancel_reason' => 'sale_cancelled']);
    }
}
