<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Services\ProductImageDerivatives;
use App\Services\ProductImageStorage;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackfillProductImageDerivatives extends Command
{
    protected $signature = 'images:backfill-derivatives {--execute : Create and verify WebP variants} {--refresh-orientation : Repair legacy JPEG orientation without deleting originals or old objects} {--id=*} {--after-id=0} {--limit=100}';
    protected $description = 'Inventory missing product thumbnails and optimized images; opt-in backfill in isolated local/test storage';

    public function handle(ProductImageDerivatives $derivatives, ProductImageStorage $storage): int
    {
        if ($this->option('execute') && app()->environment('migration')) {
            try {
                app(\App\Services\ProductionMediaMigrationGuard::class)->validate();
            } catch (\Throwable $error) {
                $this->error($error->getMessage());
                return self::FAILURE;
            }
        } elseif ($this->option('execute') && ! app()->environment(['local', 'testing'])) {
            $this->error('Backfill requires local/testing or a validated migration manifest.');
            return self::FAILURE;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        $after = filter_var($this->option('after-id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($limit === false || $after === false) {
            $this->error('Invalid limit or after-id.');
            return self::FAILURE;
        }
        $errors = 0;
        $ids = $this->option('id');
        foreach ($ids as $id) {
            if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $this->error('Image IDs must be positive integers.');
                return self::FAILURE;
            }
        }
        $refreshOrientation = (bool) $this->option('refresh-orientation');
        $images = ProductImage::query()->whereNull('deleted_at')->where('id', '>', $after)
            ->when($ids, fn ($query) => $query->whereIn('id', $ids))
            ->with('derivatives')->orderBy('id')->limit($limit)->get();
        foreach ($images as $image) {
            $missing = array_values(array_diff(['optimized', 'thumbnail'], $image->derivatives->pluck('kind')->all()));
            $this->line(json_encode(['image_id' => $image->id, 'disk' => $image->disk ?: 'public', 'missing' => $missing]));
            if (! $this->option('execute') || (! $missing && ! $refreshOrientation)) continue;
            $temp = tempnam(sys_get_temp_dir(), 'samy-backfill-');
            try {
                if (($image->disk ?: 'public') === 'r2_public') $storage->assertTestDestination();
                DB::transaction(function () use ($image, $missing, $temp, $derivatives, $refreshOrientation) {
                    $locked = ProductImage::query()->lockForUpdate()->with('derivatives')->findOrFail($image->id);
                    if ($locked->deleted_at || $locked->generation !== $image->generation || $locked->path !== $image->path) return;
                    $todo = array_values(array_diff($missing, $locked->derivatives->pluck('kind')->all()));
                    if (! $todo && ! $refreshOrientation) return;
                    $stream = Storage::disk($locked->disk ?: 'public')->readStream($locked->path);
                    if (! is_resource($stream)) throw new \RuntimeException('Original image is unreadable.');
                    try {
                        $file = fopen($temp, 'w+b');
                        try { stream_copy_to_stream($stream, $file); } finally { fclose($file); }
                    } finally { fclose($stream); }
                    $orientation = $derivatives->orientation($temp);
                    $alreadyOriented = collect(['optimized', 'thumbnail'])->every(function ($kind) use ($locked, $orientation) {
                        $version = $locked->derivatives->firstWhere('kind', $kind);
                        return $version && str_ends_with($version->path, '-'.$kind.'-exif'.$orientation.'.webp');
                    });
                    if ($refreshOrientation && $orientation !== 1 && ! $alreadyOriented) {
                        // Keep old objects. SQL rollback restores their references
                        // if a new copy cannot be generated/read back correctly.
                        $locked->derivatives()->delete();
                        $locked->forceFill(['generation' => (string) Str::uuid()])->save();
                        $todo = ['optimized', 'thumbnail'];
                    }
                    if (! $todo) return;
                    $upload = new UploadedFile($temp, basename($locked->path), $locked->content_type, null, true);
                    $derivatives->create($locked, $upload, $todo);
                }, 3);
            } catch (\Throwable $error) {
                $errors++;
                $this->error("image_id={$image->id}: {$error->getMessage()}");
            } finally {
                @unlink($temp);
            }
        }
        $this->line(json_encode(['batch_count' => $images->count(), 'last_image_id' => $images->last()?->id ?? $after, 'errors' => $errors]));
        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
