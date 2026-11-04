<?php

namespace Tests\Feature;

use App\Models\{Category, Color, Product, ProductImage, Size, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Storage};
use Tests\TestCase;

/** Explicit opt-in only. Never reads the application's active database or public files. */
class R2ProductImageMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_command_copies_preserves_sources_resumes_and_verifies_public_urls(): void
    {
        $path = getenv('SAMY_R2_TEST_ENV');
        if (! $path) {
            $this->markTestSkipped('Set SAMY_R2_TEST_ENV to explicitly enable real isolated R2 testing.');
        }
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $env = parse_ini_file($path, false, INI_SCANNER_RAW);
        $this->assertSame('samy-public-test', $env['R2_PUBLIC_BUCKET']);
        $this->assertSame('samy-private-test', $env['R2_PRIVATE_BUCKET']);
        $this->assertMatchesRegularExpression('/^https:\/\/[a-f0-9]{32}\.r2\.cloudflarestorage\.com$/', $env['R2_ENDPOINT']);
        $this->assertMatchesRegularExpression('/^https:\/\/pub-[a-f0-9]+\.r2\.dev$/', $env['R2_PUBLIC_URL']);
        config(['filesystems.disks.r2_public' => [
            'driver' => 's3', 'key' => $env['R2_ACCESS_KEY_ID'], 'secret' => $env['R2_SECRET_ACCESS_KEY'],
            'region' => 'auto', 'bucket' => 'samy-public-test', 'endpoint' => $env['R2_ENDPOINT'],
            'url' => $env['R2_PUBLIC_URL'], 'throw' => true,
            'http' => ['connect_timeout' => 10, 'timeout' => 30], 'retries' => 1,
        ], 'media.migration_enabled' => true]);
        Storage::forgetDisk('r2_public');
        Storage::fake('public');
        $user = User::factory()->create();
        $category = Category::create(['name' => 'R2 fixture', 'active' => true]);
        $size = Size::create(['name' => 'R2 fixture', 'active' => true]);
        $color = Color::create(['name' => 'R2 fixture', 'active' => true]);
        $product = Product::create(['sku' => 'R2-TEST', 'name' => 'Synthetic R2 test',
            'category_id' => $category->id, 'size_id' => $size->id, 'color_id' => $color->id,
            'gender' => 'unisex', 'purchase_price' => 100, 'sale_price' => 150,
            'status' => 'disponible', 'created_by' => $user->id]);
        $missing = ProductImage::create(['product_id' => $product->id, 'path' => 'products/missing.png', 'disk' => 'public']);
        $images = [];
        $keys = [];
        // Random pixels ensure unique object keys for each run, including parallel runs.
        foreach (range(1, 2) as $index) {
            $gd = imagecreatetruecolor(16, 16);
            imagefill($gd, 0, 0, imagecolorallocate($gd, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
            ob_start();
            imagepng($gd);
            $bytes = ob_get_clean().random_bytes(16);
            imagedestroy($gd);
            $source = 'products/r2-test-'.$index.'.png';
            Storage::disk('public')->put($source, $bytes);
            $image = ProductImage::create(['product_id' => $product->id, 'path' => $source, 'disk' => 'public', 'sort' => $index]);
            $images[] = [$image, $source, $bytes];
            $keys[] = "samy/products/{$product->id}/migrated/{$image->id}/".hash('sha256', $bytes);
        }
        try {
            $this->artisan('images:migrate-r2', ['--execute' => true, '--limit' => 1])->assertFailed();
            $this->assertSame('public', $missing->fresh()->disk);
            $this->artisan('images:migrate-r2', ['--execute' => true, '--after-id' => $missing->id])->assertSuccessful();
            foreach ($images as [$image, $source, $bytes]) {
                $migrated = $image->fresh();
                $this->assertSame('r2_public', $migrated->disk);
                $this->assertSame($source, $migrated->source_path);
                $this->assertSame($bytes, Storage::disk('public')->get($source));
                $this->assertSame(hash('sha256', $bytes), $migrated->sha256);
                $this->assertSame(strlen($bytes), $migrated->bytes);
                $response = Http::timeout(30)->get($migrated->url);
                $this->assertSame(200, $response->status());
                $this->assertSame($bytes, $response->body());
                $this->artisan('images:migrate-r2', ['--execute' => true, '--id' => [$image->id]])->assertSuccessful();
                $this->assertSame($migrated->getAttributes(), $image->fresh()->getAttributes());
                $this->artisan('images:migrate-r2', ['--verify-existing' => true, '--id' => [$image->id]])->assertSuccessful();
            }
            Storage::disk('r2_public')->delete($keys[0]);
            $this->artisan('images:migrate-r2', ['--verify-existing' => true, '--id' => [$images[0][0]->id]])->assertFailed();
            $this->assertSame('r2_public', $images[0][0]->fresh()->disk);
            $this->assertSame($images[0][2], Storage::disk('public')->get($images[0][1]));
        } finally {
            foreach ($keys as $key) {
                Storage::disk('r2_public')->delete($key);
                $this->assertFalse(Storage::disk('r2_public')->exists($key));
            }
        }
    }
}
