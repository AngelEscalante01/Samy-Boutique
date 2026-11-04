<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Services\ProductImageStorage;
use Illuminate\Console\Command;

class MigrateProductImages extends Command
{
    protected $signature = 'images:migrate-r2
        {--execute : Copy and verify; default is inventory only}
        {--verify-existing : Read back migrated objects without changing references}
        {--id=* : Restrict to specific image IDs}
        {--after-id=0 : Resume after this image ID, including after a failed batch}
        {--limit=100 : Maximum images in this resumable batch}';
    protected $description = 'Inventory or copy legacy product images to R2, preserving source files';

    public function handle(ProductImageStorage $storage): int
    {
        if ($this->option('execute') && $this->option('verify-existing')) {
            $this->error('Choose execute or verify-existing, not both.');
            return self::FAILURE;
        }
        if ($this->option('execute') && ! config('media.migration_enabled')) {
            $this->error('MEDIA_MIGRATION_ENABLED must be enabled in the isolated destination.');
            return self::FAILURE;
        }
        if ($this->option('execute') || $this->option('verify-existing')) {
            try {
                $storage->assertTestDestination();
            } catch (\Throwable $e) {
                $this->error('Migration requires an isolated test destination or a validated production copy manifest.');
                return self::FAILURE;
            }
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) {
            $this->error('limit must be between 1 and 1000.');
            return self::FAILURE;
        }
        $after = filter_var($this->option('after-id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $ids = $this->option('id');
        foreach ($ids as $id) {
            if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $this->error('id must contain positive integer image IDs.');
                return self::FAILURE;
            }
        }
        if ($after === false) {
            $this->error('after-id must be a nonnegative integer.');
            return self::FAILURE;
        }
        $errors = 0;
        $query = ProductImage::query()->whereNull('deleted_at')
            ->where('disk', $this->option('verify-existing') ? 'r2_public' : 'public')
            ->where('id', '>', $after)->orderBy('id');
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }
        $images = $query->limit($limit)->get();
        foreach ($images as $image) {
            try {
                if ($this->option('execute')) {
                    $image = $storage->migrate($image->id);
                } elseif ($this->option('verify-existing')) {
                    $image = $storage->verifyMigrated($image->id);
                }
                $this->line(json_encode([
                    'image_id' => $image->id, 'product_id' => $image->product_id,
                    'status' => ($this->option('execute') || $this->option('verify-existing')) ? 'verified' : 'inventory_only',
                    'disk' => $image->disk, 'bytes' => $image->bytes, 'sha256' => $image->sha256,
                ], JSON_THROW_ON_ERROR));
            } catch (\Throwable $e) {
                $errors++;
                $this->error("image_id={$image->id}: copy/verification failed; database reference unchanged.");
            }
        }
        $this->line(json_encode(['batch_count' => $images->count(), 'errors' => $errors,
            'last_image_id' => $images->last()?->id ?? $after,
            'retry_failed_ids' => 'Use --id for failed images; --after-id advances past failures.'], JSON_THROW_ON_ERROR));
        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
