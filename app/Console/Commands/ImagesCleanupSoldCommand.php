<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Models\ProductStockEpisode;
use App\Models\ProductVariant;
use App\Models\LayawayItem;
use App\Services\ProductImageReferenceGuard;
use Illuminate\Console\Command;

class ImagesCleanupSoldCommand extends Command
{
    protected $signature = 'images:cleanup-sold {--dry-run : Inventory only; never deletes objects}';
    protected $description = 'Read-only retention preflight; legacy dates are not proof of sale depletion';

    public function handle(): int
    {
        // No remote deletion while offline assignments cannot be reconciled.
        $this->warn('DRY-RUN ONLY: no objects are deleted; offline assignment reconciliation remains unverified.');
        ProductImage::query()->whereNull('deleted_at')->with(['product:id,sold_out_at', 'derivatives'])
            ->orderBy('id')->chunkById(200, function ($images) {
                foreach ($images as $image) {
                    $variants = ProductVariant::query()->where('product_id', $image->product_id)->where('active', true);
                    if ($image->product_variant_id) $variants->whereKey($image->product_variant_id);
                    $variants = $variants->get(['id', 'stock']);
                    $reason = null;
                    $dates = [];
                    if ($variants->isEmpty()) $reason = 'no_active_variant';
                    foreach ($variants as $variant) {
                        if ($variant->stock > 0) { $reason = 'stock_available'; break; }
                        if (LayawayItem::query()->where('product_variant_id', $variant->id)
                            ->whereHas('layaway', fn ($query) => $query->where('status', 'open'))->exists()) {
                            $reason = 'active_layaway'; break;
                        }
                        $episode = ProductStockEpisode::query()->where('product_variant_id', $variant->id)
                            ->whereNull('cancelled_at')->with('sale:id,status')->latest('id')->first();
                        if (! $episode || $episode->sale?->status !== 'completed') {
                            $reason = 'no_confirmed_sale_episode'; break;
                        }
                        if ($image->created_at && $image->created_at->gt($episode->started_at)) {
                            $reason = 'image_added_after_episode'; break;
                        }
                        $dates[] = $episode->started_at->copy()->addHours(15 * 24);
                    }
                    $eligibleAt = $dates ? max($dates) : null;
                    if (! $reason && $eligibleAt?->isFuture()) $reason = 'waiting_15_full_days';
                    if (! $reason) {
                        $guard = app(ProductImageReferenceGuard::class);
                        $reason = $guard->reasonForImage($image);
                    }
                    if (! $reason) $reason = 'offline_reconciliation_unverified';
                    $this->line(json_encode([
                        'image_id' => $image->id,
                        'product_id' => $image->product_id,
                        'variant_id' => $image->product_variant_id,
                        'bytes' => (int) $image->bytes + (int) $image->derivatives->sum('bytes'),
                        'eligible' => false,
                        'reason' => $reason,
                        'candidate_at_utc' => $eligibleAt?->utc()->toIso8601String(),
                        'legacy_sold_out_at' => $image->product?->sold_out_at?->toIso8601String(),
                    ], JSON_THROW_ON_ERROR));
                }
            });
        return self::SUCCESS;
    }
}
