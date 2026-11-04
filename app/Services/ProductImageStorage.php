<?php

namespace App\Services;

use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProductImageStorage
{
    /** Copy and verify before switching references. Never remove the source. */
    public function migrate(int $id): ProductImage
    {
        $this->assertTestDestination();
        return DB::transaction(function () use ($id) {
            $image = ProductImage::query()->lockForUpdate()->findOrFail($id);
            if ($image->deleted_at !== null || $image->disk === 'r2_public') {
                return $image;
            }
            if (($image->disk ?: 'public') !== 'public') {
                throw new RuntimeException('Only legacy public product images can be migrated.');
            }
            $source = Storage::disk('public');
            $path = $image->path;
            $stream = $source->readStream($path);
            if (! is_resource($stream)) {
                throw new RuntimeException('Source image cannot be read.');
            }
            // Upload exactly the bytes we hashed, even if the source file changes
            // while a batch is running. php://temp spills large files to disk.
            $snapshot = fopen('php://temp', 'w+b');
            if (! is_resource($snapshot)) {
                fclose($stream);
                throw new RuntimeException('Cannot create source snapshot.');
            }
            try {
                $bytes = stream_copy_to_stream($stream, $snapshot);
            } finally {
                fclose($stream);
            }
            if ($bytes === false || $bytes === 0) {
                fclose($snapshot);
                throw new RuntimeException('Source image is empty or unreadable.');
            }
            rewind($snapshot);
            $hash = hash_init('sha256');
            hash_update_stream($hash, $snapshot);
            $sha = hash_final($hash);
            rewind($snapshot);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer(fread($snapshot, 262144));
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
                fclose($snapshot);
                throw new RuntimeException('Source is not a supported raster image.');
            }
            // A repeat after an interrupted copy addresses the same object.
            $key = "samy/products/{$image->product_id}/migrated/{$image->id}/{$sha}";
            $target = Storage::disk('r2_public');
            rewind($snapshot);
            try {
                if (! $target->put($key, $snapshot, ['ContentType' => $mime])) {
                    throw new RuntimeException('Object upload failed.');
                }
            } finally {
                if (is_resource($snapshot)) {
                    fclose($snapshot);
                }
            }
            $this->verify('r2_public', $key, $sha, $bytes);
            $image->forceFill([
                'source_disk' => 'public', 'source_path' => $path,
                'disk' => 'r2_public', 'path' => $key,
                'generation' => $image->generation ?: (string) Str::uuid(),
                'sha256' => $sha, 'bytes' => $bytes, 'content_type' => $mime,
                'storage_verified_at' => now(),
            ])->save();
            return $image;
        });
    }

    public function verifyMigrated(int $id): ProductImage
    {
        $this->assertTestDestination();
        return DB::transaction(function () use ($id) {
            $image = ProductImage::query()->lockForUpdate()->findOrFail($id);
            if ($image->deleted_at !== null || $image->disk !== 'r2_public'
                || ! $image->sha256 || $image->bytes === null) {
                throw new RuntimeException('Image has no active verified R2 reference.');
            }
            $this->verify('r2_public', $image->path, $image->sha256, $image->bytes);
            return $image;
        });
    }

    public function assertTestDestination(): void
    {
        if (app()->environment('migration')) {
            app(ProductionMediaMigrationGuard::class)->validate();
            return;
        }
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Image migration is restricted to local/testing environments.');
        }
        $disk = Storage::disk('r2_public');
        if ($disk->getAdapter() instanceof \League\Flysystem\AwsS3V3\AwsS3V3Adapter
            && config('filesystems.disks.r2_public.bucket') !== 'samy-public-test') {
            throw new RuntimeException('Image migration requires the samy-public-test bucket.');
        }
    }

    public function verify(string $disk, string $key, string $sha256, int $bytes): void
    {
        $stream = Storage::disk($disk)->readStream($key);
        if (! is_resource($stream)) {
            throw new RuntimeException('Uploaded object cannot be read back.');
        }
        try {
            $hash = hash_init('sha256');
            $read = hash_update_stream($hash, $stream);
            if ($read !== $bytes || ! hash_equals($sha256, hash_final($hash))) {
                throw new RuntimeException('Uploaded object failed size/SHA-256 verification.');
            }
        } finally {
            fclose($stream);
        }
    }
}
