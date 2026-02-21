<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImagesCleanupSoldCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_images_when_product_was_sold_16_days_ago_in_soft_mode(): void
    {
        Storage::fake('public');

        Setting::set('sales.delete_images_after_days', 15);
        Setting::set('sales.delete_images_mode', 'soft');

        [$product, $image] = $this->createSoldProductWithImage(16, true);

        $this->artisan('images:cleanup-sold')->assertExitCode(0);

        $this->assertFalse(Storage::disk('public')->exists($image->path));

        $image->refresh();

        $this->assertNotNull($image->deleted_at);
        $this->assertNull($image->deleted_by);
        $this->assertEquals($product->id, $image->product_id);
    }

    public function test_keeps_images_when_product_was_sold_10_days_ago(): void
    {
        Storage::fake('public');

        Setting::set('sales.delete_images_after_days', 15);
        Setting::set('sales.delete_images_mode', 'soft');

        [, $image] = $this->createSoldProductWithImage(10, true);

        $this->artisan('images:cleanup-sold')->assertExitCode(0);

        $this->assertTrue(Storage::disk('public')->exists($image->path));

        $image->refresh();

        $this->assertNull($image->deleted_at);
    }

    public function test_does_not_fail_when_file_is_missing_and_marks_image_in_soft_mode(): void
    {
        Storage::fake('public');

        Setting::set('sales.delete_images_after_days', 15);
        Setting::set('sales.delete_images_mode', 'soft');

        [, $image] = $this->createSoldProductWithImage(16, false);

        $this->artisan('images:cleanup-sold')->assertExitCode(0);

        $image->refresh();

        $this->assertNotNull($image->deleted_at);
    }

    public function test_hard_mode_removes_the_image_record(): void
    {
        Storage::fake('public');

        Setting::set('sales.delete_images_after_days', 15);
        Setting::set('sales.delete_images_mode', 'hard');

        [, $image] = $this->createSoldProductWithImage(16, true);

        $this->artisan('images:cleanup-sold')->assertExitCode(0);

        $this->assertFalse(Storage::disk('public')->exists($image->path));

        $this->assertDatabaseMissing('product_images', [
            'id' => $image->id,
        ]);
    }

    private function createSoldProductWithImage(int $daysAgo, bool $createPhysicalFile): array
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Cat '.Str::uuid(), 'active' => true]);
        $size = Size::create(['name' => 'Talla '.Str::uuid(), 'active' => true]);
        $color = Color::create(['name' => 'Color '.Str::uuid(), 'hex' => '#111111', 'active' => true]);

        $product = Product::create([
            'sku' => 'SKU-'.Str::upper(Str::random(10)),
            'name' => 'Producto Test',
            'description' => null,
            'category_id' => $category->id,
            'gender' => 'unisex',
            'size_id' => $size->id,
            'color_id' => $color->id,
            'purchase_price' => 100,
            'sale_price' => 150,
            'status' => 'vendido',
            'created_by' => $user->id,
            'sold_at' => now()->subDays($daysAgo),
        ]);

        $path = 'products/tests/'.Str::uuid().'.jpg';

        if ($createPhysicalFile) {
            Storage::disk('public')->put($path, 'fake-image-content');
        }

        $image = ProductImage::create([
            'product_id' => $product->id,
            'path' => $path,
            'sort' => 0,
        ]);

        return [$product, $image];
    }
}
