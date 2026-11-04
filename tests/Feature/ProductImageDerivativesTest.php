<?php

namespace Tests\Feature;

use App\Actions\Products\StoreProductImagesAction;
use App\Jobs\DeleteRequestedProductImage;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ProductImageDerivativesTest extends TestCase
{
    use RefreshDatabase;

    public static function orientations(): array
    {
        return [
            [1, 40, 20, [255, 0, 0]], [2, 40, 20, [0, 255, 0]],
            [3, 40, 20, [255, 255, 0]], [4, 40, 20, [0, 0, 255]],
            [5, 20, 40, [255, 0, 0]], [6, 20, 40, [0, 0, 255]],
            [7, 20, 40, [255, 255, 0]], [8, 20, 40, [0, 255, 0]],
        ];
    }

    #[DataProvider('orientations')]
    public function test_jpeg_orientation_is_applied_to_webp_without_changing_original(int $orientation, int $width, int $height, array $expectedColor): void
    {
        Storage::fake('public');
        config(['media.product_disk' => 'public']);
        $product = $this->fixtureProduct();
        $bytes = $this->orientedJpeg($orientation);
        app(StoreProductImagesAction::class)->execute($product, [UploadedFile::fake()->createWithContent('orientation.jpg', $bytes)]);
        $image = $product->images()->with('derivatives')->firstOrFail();
        $this->assertSame($bytes, Storage::disk('public')->get($image->path));
        foreach ($image->derivatives as $derivative) {
            $converted = imagecreatefromstring(Storage::disk('public')->get($derivative->path));
            $this->assertSame([$width, $height], [imagesx($converted), imagesy($converted)]);
            $color = imagecolorsforindex($converted, imagecolorat($converted, 3, 3));
            foreach (['red', 'green', 'blue'] as $index => $channel) {
                $this->assertLessThan(55, abs($color[$channel] - $expectedColor[$index]));
            }
            imagedestroy($converted);
        }
    }

    public function test_orientation_repair_preserves_old_objects_and_is_idempotent(): void
    {
        Storage::fake('public');
        config(['media.product_disk' => 'public']);
        $product = $this->fixtureProduct();
        $bytes = $this->orientedJpeg(6);
        app(StoreProductImagesAction::class)->execute($product, [UploadedFile::fake()->createWithContent('orientation.jpg', $bytes)]);
        $image = $product->images()->with('derivatives')->firstOrFail();
        // Simulate legacy metadata written by the previous generator.
        $oldPaths = [];
        foreach ($image->derivatives as $derivative) {
            $old = str_replace('-exif6.webp', '.webp', $derivative->path);
            Storage::disk('public')->move($derivative->path, $old);
            $derivative->update(['path' => $old]);
            $oldPaths[] = $old;
        }
        $oldGeneration = $image->generation;
        $options = ['--execute' => true, '--refresh-orientation' => true, '--id' => [$image->id]];
        $this->artisan('images:backfill-derivatives', $options)->assertExitCode(0);
        $repaired = $image->fresh('derivatives');
        $this->assertNotSame($oldGeneration, $repaired->generation);
        $this->assertCount(2, $repaired->derivatives);
        foreach ($oldPaths as $path) Storage::disk('public')->assertExists($path);
        $this->assertSame($bytes, Storage::disk('public')->get($image->path));
        $paths = $repaired->derivatives->pluck('path')->all();
        $count = count(Storage::disk('public')->allFiles());
        $this->artisan('images:backfill-derivatives', $options)->assertExitCode(0);
        $this->assertSame($repaired->generation, $image->fresh()->generation);
        $this->assertSame($paths, $image->fresh('derivatives')->derivatives->pluck('path')->all());
        $this->assertSame($count, count(Storage::disk('public')->allFiles()));
    }

    private function orientedJpeg(int $orientation): string
    {
        $image = imagecreatetruecolor(40, 20);
        imagefilledrectangle($image, 0, 0, 19, 9, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 20, 0, 39, 9, imagecolorallocate($image, 0, 255, 0));
        imagefilledrectangle($image, 0, 10, 19, 19, imagecolorallocate($image, 0, 0, 255));
        imagefilledrectangle($image, 20, 10, 39, 19, imagecolorallocate($image, 255, 255, 0));
        ob_start();
        imagejpeg($image, null, 100);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $exif = "Exif\0\0II\x2a\0\x08\0\0\0\x01\0\x12\x01\x03\0\x01\0\0\0".pack('v', $orientation)."\0\0\0\0\0\0";
        return substr($bytes, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($bytes, 2);
    }

    private function fixtureProduct(): Product
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'QA orientación', 'active' => true]);
        $size = Size::create(['name' => 'QA M', 'active' => true]);
        $color = Color::create(['name' => 'QA Azul', 'active' => true]);
        return Product::create(['sku' => 'QA-EXIF-1', 'name' => 'QA Imagen sintética', 'category_id' => $category->id,
            'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 1,
            'sale_price' => 2, 'status' => 'disponible', 'created_by' => $user->id]);
    }

    public function test_upload_creates_verified_versions_and_deletion_removes_all_three_objects(): void
    {
        Storage::fake('public');
        config(['media.product_disk' => 'public']);
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Vestidos', 'active' => true]);
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Negro', 'active' => true]);
        $product = Product::create(['sku' => 'IMAGE-1', 'name' => 'Vestido', 'category_id' => $category->id, 'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 1, 'sale_price' => 2, 'status' => 'disponible', 'created_by' => $user->id]);

        app(StoreProductImagesAction::class)->execute($product, [UploadedFile::fake()->image('photo.jpg', 1200, 800)]);
        $image = $product->images()->firstOrFail();
        $this->assertCount(2, $image->derivatives);
        $this->assertSame($image->generation, $image->derivatives->first()->generation);
        Storage::disk('public')->assertExists($image->path);
        foreach ($image->derivatives as $derivative) Storage::disk('public')->assertExists($derivative->path);

        $image->forceFill(['deleted_at' => now(), 'deletion_state' => 'pending'])->save();
        (new DeleteRequestedProductImage($image->id, DeleteRequestedProductImage::identity($image)))->handle();
        Storage::disk('public')->assertMissing($image->path);
        foreach ($image->derivatives as $derivative) Storage::disk('public')->assertMissing($derivative->path);
        $this->assertSame('deleted', $image->fresh()->deletion_state);
    }
}
