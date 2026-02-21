<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImagesCleanupSoldCommand extends Command
{
    protected $signature = 'images:cleanup-sold';

    protected $description = 'Limpia imágenes de productos vendidos después de X días según configuración';

    public function handle(): int
    {
        $days = (int) Setting::get('sales.delete_images_after_days', 15);

        if ($days < 0) {
            $days = 15;
        }

        $mode = strtolower((string) Setting::get('sales.delete_images_mode', 'soft'));
        if (! in_array($mode, ['soft', 'hard'], true)) {
            $mode = 'soft';
        }

        $cutoff = now()->subDays($days);
        $disk = Storage::disk('public');

        $imagesProcessed = 0;
        $productsProcessed = [];
        $filesDeleted = 0;
        $filesMissing = 0;
        $errors = 0;

        ProductImage::query()
            ->whereNull('deleted_at')
            ->whereHas('product', function (Builder $query) use ($cutoff) {
                $query->where('status', 'vendido')
                    ->whereNotNull('sold_at')
                    ->where('sold_at', '<=', $cutoff);
            })
            ->with(['product:id'])
            ->orderBy('id')
            ->chunkById(200, function ($images) use (
                $disk,
                $mode,
                &$imagesProcessed,
                &$productsProcessed,
                &$filesDeleted,
                &$filesMissing,
                &$errors
            ) {
                foreach ($images as $image) {
                    try {
                        $path = (string) $image->path;

                        if ($path !== '' && $disk->exists($path)) {
                            $disk->delete($path);
                            $filesDeleted++;
                        } else {
                            $filesMissing++;
                        }

                        if ($mode === 'hard') {
                            $image->delete();
                        } else {
                            $image->forceFill([
                                'deleted_at' => now(),
                                'deleted_by' => null,
                            ])->save();
                        }

                        $imagesProcessed++;
                        $productsProcessed[$image->product_id] = true;
                    } catch (\Throwable $exception) {
                        $errors++;

                        Log::warning('images:cleanup-sold - error procesando imagen', [
                            'product_image_id' => $image->id,
                            'product_id' => $image->product_id,
                            'path' => $image->path,
                            'mode' => $mode,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        $productsCount = count($productsProcessed);

        Log::info('images:cleanup-sold ejecutado', [
            'mode' => $mode,
            'days' => $days,
            'cutoff' => $cutoff->toDateTimeString(),
            'images_processed' => $imagesProcessed,
            'products_processed' => $productsCount,
            'files_deleted' => $filesDeleted,
            'files_missing' => $filesMissing,
            'errors' => $errors,
        ]);

        $this->info("Cleanup completado. mode={$mode}, days={$days}, images={$imagesProcessed}, products={$productsCount}, files_deleted={$filesDeleted}, files_missing={$filesMissing}, errors={$errors}");

        return self::SUCCESS;
    }
}
