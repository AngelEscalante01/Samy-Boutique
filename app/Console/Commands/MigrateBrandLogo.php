<?php

namespace App\Console\Commands;

use App\Services\ProductImageStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateBrandLogo extends Command
{
    protected $signature = 'media:migrate-brand-logo {--execute : Upload the immutable logo to isolated R2/MinIO test storage}';

    protected $description = 'Inventory or copy the existing Samy logo to public object storage without switching the live reference';

    public function handle(ProductImageStorage $storage): int
    {
        $source = public_path('logo-source.png');
        if (! is_file($source) || ! is_readable($source)) {
            $this->error('El logo original no está disponible.');

            return self::FAILURE;
        }
        $sha = hash_file('sha256', $source);
        $bytes = filesize($source);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source);
        if ($bytes === false || $bytes < 1 || $mime !== 'image/png') {
            $this->error('El logo debe ser un PNG no vacío.');

            return self::FAILURE;
        }
        $key = "samy/brand/logo/{$sha}.png";
        $this->line(json_encode(['disk' => 'r2_public', 'key' => $key, 'sha256' => $sha, 'bytes' => $bytes]));
        if (! $this->option('execute')) {
            $this->line('DRY-RUN ONLY: no object was written.');

            return self::SUCCESS;
        }
        $bucket = config('filesystems.disks.r2_public.bucket');
        if (app()->environment('migration')) {
            try {
                app(\App\Services\ProductionMediaMigrationGuard::class)->validate();
            } catch (\Throwable $error) {
                $this->error($error->getMessage());
                return self::FAILURE;
            }
        } elseif (! app()->environment(['local', 'testing']) || ! in_array($bucket, ['samy-public-test', 'samy-public-local'], true)) {
            $this->error('La carga del logo solo se permite en los buckets públicos aislados de pruebas.');

            return self::FAILURE;
        }
        try {
            $stream = fopen($source, 'rb');
            try {
                if (! Storage::disk('r2_public')->put($key, $stream, ['ContentType' => 'image/png'])) {
                    throw new \RuntimeException('No se pudo cargar el logo.');
                }
            } finally {
                fclose($stream);
            }
            $storage->verify('r2_public', $key, $sha, $bytes);
            $this->info('Logo cargado y verificado. Configura BRAND_LOGO_DISK=r2_public y BRAND_LOGO_PATH con la clave mostrada para usarlo.');

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
