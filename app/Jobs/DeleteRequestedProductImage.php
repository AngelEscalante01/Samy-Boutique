<?php

namespace App\Jobs;

use App\Models\ProductImage;
use App\Services\ProductImageReferenceGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Only explicit manual removals. This job is NOT the sale-retention purger. */
class DeleteRequestedProductImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public int $imageId, public string $objectIdentity) {}

    public function backoff(): array { return [60, 300]; }

    public static function identity(ProductImage $image): string
    {
        return hash('sha256', json_encode([$image->disk ?: 'public', $image->path, $image->generation], JSON_THROW_ON_ERROR));
    }

    public function handle(): void
    {
        DB::transaction(function () {
            $image = ProductImage::query()->with('derivatives')->lockForUpdate()->find($this->imageId);
            if (! $image || ! $image->deleted_at || $image->deletion_state !== 'pending'
                || ! hash_equals($this->objectIdentity, self::identity($image))) {
                return;
            }
            $guard = app(ProductImageReferenceGuard::class);
            $diskName = $image->disk ?: 'public';
            $reference = $guard->reasonForImage($image);
            foreach ($image->derivatives as $derivative) {
                if ($derivative->generation !== $image->generation || $derivative->disk !== $diskName) {
                    throw new \RuntimeException('Derivative identity mismatch.');
                }
            }
            if ($reference !== null) {
                $image->forceFill(['deletion_state' => 'retained_shared'])->save();
                return;
            }
            $disk = Storage::disk($diskName);
            foreach ($image->derivatives as $derivative) {
                if ($disk->exists($derivative->path) && ! $disk->delete($derivative->path)) {
                    throw new \RuntimeException('Derivative deletion failed; durable intent remains pending.');
                }
            }
            // The tombstone was committed BEFORE this job. If remote delete
            // succeeds but this transaction fails, the pending intent survives.
            if ($disk->exists($image->path) && ! $disk->delete($image->path)) {
                throw new \RuntimeException('Object deletion failed; durable intent remains pending.');
            }
            $image->forceFill(['deletion_state' => 'deleted', 'object_deleted_at' => now()])->save();
        });
    }
}
