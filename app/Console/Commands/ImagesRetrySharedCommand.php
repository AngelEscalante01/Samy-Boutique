<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Services\ProductImageReferenceGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImagesRetrySharedCommand extends Command
{
    protected $signature = 'images:retry-shared';
    protected $description = 'Recheck manually retired images kept because another live record used the object';

    public function handle(ProductImageReferenceGuard $guard): int
    {
        $ready = 0;
        ProductImage::query()->whereNotNull('deleted_at')->where('deletion_state', 'retained_shared')
            ->orderBy('id')->chunkById(100, function ($images) use ($guard, &$ready) {
                foreach ($images as $candidate) {
                    DB::transaction(function () use ($candidate, $guard, &$ready) {
                        $image = ProductImage::query()->with('derivatives')->lockForUpdate()->find($candidate->id);
                        if (! $image || ! $image->deleted_at || $image->deletion_state !== 'retained_shared') return;
                        if ($guard->reasonForImage($image) !== null) return;
                        $image->forceFill(['deletion_state' => 'pending'])->save();
                        $ready++;
                    });
                }
            });
        $this->info("{$ready} objetos manualmente retirados pueden reintentarse.");

        return self::SUCCESS;
    }
}
