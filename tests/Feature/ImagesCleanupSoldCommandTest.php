<?php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\ProductImageStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImagesCleanupSoldCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_sold_date_never_authorizes_deletion_even_with_hard_setting(): void
    {
        [$product, $image] = $this->fixture();
        \App\Models\Setting::set('sales.delete_images_mode', 'hard');
        $product->update(['sold_at' => now()->subDays(100), 'sold_out_at' => now()->subDays(100), 'status' => 'vendido']);
        $this->artisan('images:cleanup-sold')->assertSuccessful();
        Storage::disk('public')->assertExists($image->path);
        $this->assertNull($image->fresh()->deleted_at);
    }

    public function test_migration_verifies_bytes_preserves_source_and_is_resumable(): void
    {
        [$product, $image] = $this->fixture();
        $sourcePath = $image->path;
        $source = Storage::disk('public')->get($sourcePath);
        $service = app(ProductImageStorage::class);
        $moved = $service->migrate($image->id);
        $this->assertSame('r2_public', $moved->disk);
        $this->assertSame(hash('sha256', $source), $moved->sha256);
        $this->assertSame(strlen($source), $moved->bytes);
        $this->assertSame($source, Storage::disk('r2_public')->get($moved->path));
        Storage::disk('public')->assertExists($sourcePath);
        $this->assertSame($sourcePath, $moved->source_path);
        $this->assertSame($moved->path, $service->migrate($image->id)->path);
        $this->assertCount(1, Storage::disk('r2_public')->allFiles());
    }

    public function test_missing_source_does_not_change_reference(): void
    {
        [, $image] = $this->fixture();
        Storage::disk('public')->delete($image->path);
        config(['media.migration_enabled' => true]);
        $this->artisan('images:migrate-r2 --execute')->assertFailed();
        $this->assertSame('public', $image->fresh()->disk);
        $this->assertNull($image->fresh()->storage_verified_at);
    }

    public function test_inventory_default_is_read_only_and_execute_requires_opt_in(): void
    {
        [, $image] = $this->fixture();
        $this->artisan('images:migrate-r2')->assertSuccessful();
        $this->artisan('images:migrate-r2 --execute')->assertFailed();
        $this->assertSame('public', $image->fresh()->disk);
        $this->assertCount(0, Storage::disk('r2_public')->allFiles());
    }

    public function test_new_upload_uses_configured_disk_and_retired_url_is_empty(): void
    {
        [$product] = $this->fixture();
        config(['media.product_disk' => 'r2_public']);
        app(\App\Actions\Products\StoreProductImagesAction::class)->execute($product, [UploadedFile::fake()->image('photo.png')]);
        $image = ProductImage::latest('id')->firstOrFail();
        $this->assertSame('r2_public', $image->disk);
        $this->assertNotNull($image->generation);
        $this->assertNotNull($image->storage_verified_at);
        Storage::disk('r2_public')->assertExists($image->path);
        $resource = (new \App\Http\Resources\ProductResource($product->fresh()->load(['images', 'variants', 'category', 'size', 'color'])))->resolve();
        $serialized = collect($resource['images'])->firstWhere('id', $image->id);
        $this->assertSame($image->url, $serialized['image_url']);
        $this->assertSame($image->generation, $serialized['generation']);
        $image->update(['deleted_at' => now()]);
        $this->assertSame('', $image->fresh()->url);
    }

    public function test_manual_deletion_retries_after_remote_success_and_database_failure(): void
    {
        [, $image] = $this->fixture();
        $image->forceFill(['deleted_at' => now(), 'deletion_state' => 'pending'])->save();
        $job = new \App\Jobs\DeleteRequestedProductImage($image->id, \App\Jobs\DeleteRequestedProductImage::identity($image));
        $failOnce = true;
        ProductImage::updated(function ($model) use (&$failOnce) {
            if ($failOnce && $model->deletion_state === 'deleted') {
                $failOnce = false;
                throw new \RuntimeException('Injected database confirmation failure');
            }
        });
        try {
            $job->handle();
            $this->fail('Expected confirmation failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected database confirmation failure', $exception->getMessage());
        }
        Storage::disk('public')->assertMissing($image->path);
        $this->assertSame('pending', $image->fresh()->deletion_state);
        $this->assertNotNull($image->fresh()->deleted_at);
        $job->handle();
        $this->assertSame('deleted', $image->fresh()->deletion_state);
    }

    public function test_stale_job_and_active_shared_reference_preserve_object(): void
    {
        [$product, $image] = $this->fixture();
        $image->forceFill(['deleted_at' => now(), 'deletion_state' => 'pending'])->save();
        (new \App\Jobs\DeleteRequestedProductImage($image->id, 'stale'))->handle();
        Storage::disk('public')->assertExists($image->path);
        ProductImage::create(['product_id' => $product->id, 'path' => $image->path, 'sort' => 2, 'disk' => 'public']);
        (new \App\Jobs\DeleteRequestedProductImage($image->id, \App\Jobs\DeleteRequestedProductImage::identity($image)))->handle();
        Storage::disk('public')->assertExists($image->path);
        $this->assertSame('retained_shared', $image->fresh()->deletion_state);
    }

    public function test_manual_retirement_retries_after_the_last_shared_reference_is_removed(): void
    {
        [$product, $image] = $this->fixture();
        $other = ProductImage::create(['product_id' => $product->id, 'path' => $image->path,
            'sort' => 2, 'disk' => 'public']);
        $image->forceFill(['deleted_at' => now(), 'deletion_state' => 'pending'])->save();
        $job = new \App\Jobs\DeleteRequestedProductImage($image->id, \App\Jobs\DeleteRequestedProductImage::identity($image));
        $job->handle();
        $this->assertSame('retained_shared', $image->fresh()->deletion_state);
        $this->artisan('images:retry-shared')->assertSuccessful();
        Storage::disk('public')->assertExists($image->path);
        $this->assertSame('retained_shared', $image->fresh()->deletion_state);

        $other->delete();
        $this->artisan('images:retry-shared')->assertSuccessful();
        $this->assertSame('pending', $image->fresh()->deletion_state);
        $job->handle();
        $this->assertSame('deleted', $image->fresh()->deletion_state);
        Storage::disk('public')->assertMissing($image->path);
    }

    public function test_manual_deletion_preserves_objects_referenced_by_other_media_scopes(): void
    {
        [$product, $image] = $this->fixture();
        \App\Models\CatalogSlide::create([
            'desktop_disk' => 'public', 'desktop_path' => $image->path,
            'generation' => (string) \Illuminate\Support\Str::uuid(),
        ]);
        $image->forceFill(['deleted_at' => now(), 'deletion_state' => 'pending'])->save();
        (new \App\Jobs\DeleteRequestedProductImage($image->id, \App\Jobs\DeleteRequestedProductImage::identity($image)))->handle();
        Storage::disk('public')->assertExists($image->path);
        $this->assertSame('retained_shared', $image->fresh()->deletion_state);

        $anotherPath = 'products/another.png';
        Storage::disk('public')->put($anotherPath, 'synthetic-original-bytes');
        $another = ProductImage::create(['product_id' => $product->id, 'path' => $anotherPath, 'sort' => 2, 'disk' => 'public']);
        $generation = (string) \Illuminate\Support\Str::uuid();
        $another->forceFill(['generation' => $generation, 'deleted_at' => now(), 'deletion_state' => 'pending'])->save();
        $derivativePath = 'products/shared-brand.webp';
        Storage::disk('public')->put($derivativePath, 'synthetic-webp-bytes');
        $another->derivatives()->create([
            'kind' => 'thumbnail', 'disk' => 'public', 'path' => $derivativePath,
            'generation' => $generation, 'sha256' => hash('sha256', 'synthetic-webp-bytes'),
            'bytes' => strlen('synthetic-webp-bytes'), 'content_type' => 'image/webp',
        ]);
        config(['media.brand_logo_disk' => 'public', 'media.brand_logo_path' => $derivativePath]);
        (new \App\Jobs\DeleteRequestedProductImage($another->id, \App\Jobs\DeleteRequestedProductImage::identity($another)))->handle();
        Storage::disk('public')->assertExists($another->path);
        Storage::disk('public')->assertExists($derivativePath);
        $this->assertSame('retained_shared', $another->fresh()->deletion_state);
    }

    public function test_production_environment_rejects_migration_even_when_enabled(): void
    {
        [, $image] = $this->fixture();
        config(['media.migration_enabled' => true]);
        $this->app->instance('env', 'production');
        $this->artisan('images:migrate-r2 --execute')->assertFailed();
        $this->assertSame('public', $image->fresh()->disk);
        Storage::disk('r2_public')->assertMissing($image->path);
    }

    public function test_cursor_skips_failed_source_and_explicit_ids_can_retry_it(): void
    {
        [$product, $image] = $this->fixture();
        $missing = ProductImage::create(['product_id' => $product->id, 'path' => 'products/missing.png', 'disk' => 'public']);
        config(['media.migration_enabled' => true]);
        $this->artisan('images:migrate-r2', ['--execute' => true, '--id' => [$missing->id]])->assertFailed();
        $this->assertSame('public', $image->fresh()->disk);
        Storage::disk('public')->put($missing->path, Storage::disk('public')->get($image->path));
        $this->artisan('images:migrate-r2', ['--execute' => true, '--after-id' => $image->id])->assertSuccessful();
        $this->assertSame('r2_public', $missing->fresh()->disk);
        $this->assertSame('public', $image->fresh()->disk);
        $this->artisan('images:migrate-r2', ['--execute' => true, '--id' => [$image->id]])->assertSuccessful();
        $this->assertSame('r2_public', $image->fresh()->disk);
    }

    public function test_real_s3_disk_with_non_test_bucket_is_rejected_before_transfer(): void
    {
        [, $image] = $this->fixture();
        config(['media.migration_enabled' => true, 'filesystems.disks.r2_public' => [
            'driver' => 's3', 'key' => 'dummy', 'secret' => 'dummy', 'region' => 'auto',
            'bucket' => 'samy-production', 'endpoint' => 'https://example.invalid', 'throw' => true,
        ]]);
        Storage::forgetDisk('r2_public');
        $this->artisan('images:migrate-r2 --execute')->assertFailed();
        $this->assertSame('public', $image->fresh()->disk);
        $this->assertNull($image->fresh()->storage_verified_at);
    }

    public function test_verify_existing_detects_corruption_without_replacing_references(): void
    {
        [, $image] = $this->fixture();
        $migrated = app(ProductImageStorage::class)->migrate($image->id);
        $before = $migrated->getAttributes();
        $this->artisan('images:migrate-r2 --verify-existing')->assertSuccessful();
        Storage::disk('r2_public')->put($migrated->path, 'corrupt');
        $this->artisan('images:migrate-r2 --verify-existing')->assertFailed();
        $this->assertSame($before, $image->fresh()->getAttributes());
        Storage::disk('public')->assertExists($migrated->source_path);
    }

    public function test_invalid_batch_arguments_do_not_change_references(): void
    {
        [, $image] = $this->fixture();
        config(['media.migration_enabled' => true]);
        foreach (['--limit=0', '--after-id=-1', '--id=abc', '--id=0', '--verify-existing'] as $argument) {
            $this->artisan('images:migrate-r2 --execute '.$argument)->assertFailed();
        }
        $this->assertSame('public', $image->fresh()->disk);
    }

    public function test_offline_snapshot_preserves_r2_url_and_generation(): void
    {
        [$product, $image] = $this->fixture();
        Storage::set('r2_public', Storage::build([
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/r2_public'),
            'url' => 'https://media.example.test',
        ]));
        $image->update(['disk' => 'r2_public', 'path' => 'samy/products/test-photo',
            'generation' => '7c45cf95-c12b-4ccb-b100-4daf93ae4afa']);
        $response = app(\App\Http\Controllers\OfflineSnapshotController::class)->snapshot()->getData(true);
        $item = collect($response['products'])->firstWhere('id', $product->id);
        $this->assertSame('https://media.example.test/samy/products/test-photo', $item['image_url']);
        $this->assertSame($image->generation, $item['image_generation']);
        $image->update(['deleted_at' => now()]);
        $response = app(\App\Http\Controllers\OfflineSnapshotController::class)->snapshot()->getData(true);
        $this->assertNull(collect($response['products'])->firstWhere('id', $product->id)['image_url']);
    }

    private function fixture(): array
    {
        Storage::fake('public');
        Storage::fake('r2_public');
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Fixture', 'active' => true]);
        $size = \App\Models\Size::create(['name' => 'Fixture', 'active' => true]);
        $color = \App\Models\Color::create(['name' => 'Fixture', 'active' => true, 'hex' => '#111111']);
        $product = Product::create(['sku' => 'FIX-1', 'name' => 'Fixture', 'category_id' => $category->id, 'size_id' => $size->id, 'color_id' => $color->id, 'gender' => 'unisex', 'purchase_price' => 100, 'sale_price' => 150, 'status' => 'disponible', 'created_by' => $user->id]);
        $file = UploadedFile::fake()->image('legacy.png');
        $path = $file->storeAs('products', 'legacy.png', 'public');
        $image = ProductImage::create(['product_id' => $product->id, 'path' => $path, 'sort' => 1, 'disk' => 'public']);
        return [$product, $image];
    }
}
