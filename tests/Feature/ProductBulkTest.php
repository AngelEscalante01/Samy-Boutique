<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductBulkTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_table_filters_recently_created_products_and_shows_newest_first(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $category = Category::create(['name' => 'Vestidos', 'active' => true]);
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Negro', 'active' => true]);
        foreach ([['Blusa antigua', 45], ['Vestido intermedio', 20], ['Vestido reciente', 1]] as $index => [$name, $days]) {
            $product = Product::create(['sku' => 'RECENT-'.$index, 'name' => $name, 'category_id' => $category->id, 'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 100, 'sale_price' => 250, 'status' => 'disponible', 'created_by' => $manager->id]);
            $product->forceFill(['created_at' => now()->subDays($days)])->saveQuietly();
        }

        $this->actingAs($manager)->get('/products/bulk')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products', 3)
                ->where('products.0.name', 'Vestido reciente')
                ->where('products.2.name', 'Blusa antigua')->etc());
        $this->get('/products/bulk?recent_days=7')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products', 1)
                ->where('products.0.name', 'Vestido reciente')
                ->where('filters.recent_days', '7')->etc());
        $this->get('/products/bulk?recent_days=30')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products', 2)->etc());
        $this->get('/products/bulk?recent_days=90&q=Blusa')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products', 1)->where('products.0.name', 'Blusa antigua')->etc());
        $this->get('/products/bulk?recent_days=5')->assertSessionHasErrors('recent_days');
    }

    public function test_bulk_editor_loads_existing_products_and_rejects_stale_save(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $category = Category::create(['name' => 'Vestidos', 'active' => true]);
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Negro', 'active' => true]);
        $product = Product::create(['sku' => 'BULK-1', 'name' => 'Vestido', 'category_id' => $category->id, 'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 100, 'sale_price' => 250, 'status' => 'disponible', 'created_by' => $manager->id]);
        $variant = ProductVariant::create(['sku' => 'BULK-1-M', 'product_id' => $product->id, 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 4, 'purchase_price' => 100, 'sale_price' => 250, 'active' => true]);

        $this->actingAs($manager)->get('/products/bulk?ids[0]='.$product->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Products/Bulk')->has('products', 1)->where('products.0.sku', 'BULK-1')->etc());
        $this->get('/products/bulk')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Products/Bulk')->has('products', 1)->where('pagination.total', 1)->etc());

        $payload = [
            'sku' => $product->sku, 'expected_updated_at' => '2000-01-01T00:00:00Z',
            'name' => 'Vestido actualizado', 'category_id' => $category->id, 'gender' => 'dama',
            'sale_price_base' => 250, 'status' => 'disponible', 'occasion_ids' => [],
            'variants' => [['id' => $variant->id, 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 4, 'sale_price' => 250, 'purchase_price' => 100]],
        ];
        $this->put('/products/'.$product->id, $payload)->assertSessionHasErrors('expected_updated_at');
        $this->assertSame('Vestido', $product->fresh()->name);
        $payload['expected_updated_at'] = $product->fresh()->updated_at->toISOString();
        $this->put('/products/'.$product->id, $payload)->assertRedirect();
        $this->assertSame('Vestido actualizado', $product->fresh()->name);
    }

    public function test_bulk_save_uploads_image_and_returns_fresh_product_for_another_save(): void
    {
        Storage::fake('public');
        config()->set('media.product_disk', 'public');
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $category = Category::create(['name' => 'Vestidos', 'active' => true]);
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Negro', 'active' => true]);
        $product = Product::create(['sku' => 'BULK-IMG', 'name' => 'Vestido floral', 'category_id' => $category->id, 'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 100, 'sale_price' => 250, 'status' => 'disponible', 'created_by' => $manager->id]);
        $variant = ProductVariant::create(['sku' => 'BULK-IMG-M', 'product_id' => $product->id, 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 4, 'purchase_price' => 100, 'sale_price' => 250, 'active' => true]);
        $product->forceFill(['updated_at' => now()->subMinute()])->saveQuietly();

        $payload = [
            '_method' => 'put', 'sku' => $product->sku,
            'expected_updated_at' => $product->fresh()->updated_at->toISOString(),
            'name' => 'Vestido floral', 'category_id' => $category->id, 'gender' => 'dama',
            'status' => 'disponible', 'catalog_published' => '1',
            'variants' => [['id' => $variant->id, 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 4, 'sale_price' => 250, 'purchase_price' => 100]],
            'images' => [UploadedFile::fake()->image('vestido.jpg', 800, 1000)],
            'image_variant_ids' => [$variant->id],
        ];
        $response = $this->actingAs($manager)->post('/products/'.$product->id, $payload, ['Accept' => 'application/json'])->assertOk();
        $response->assertJsonPath('data.catalog_published', true)->assertJsonCount(1, 'data.images');
        $image = ProductImage::query()->where('product_id', $product->id)->sole();
        $this->assertSame($variant->id, $image->product_variant_id);
        Storage::disk('public')->assertExists($image->path);

        unset($payload['images'], $payload['image_variant_ids']);
        $payload['expected_updated_at'] = $response->json('data.updated_at');
        $payload['name'] = 'Vestido floral actualizado';
        $this->post('/products/'.$product->id, $payload, ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.name', 'Vestido floral actualizado')
            ->assertJsonCount(1, 'data.images');
    }

    public function test_photo_table_only_adds_images_and_rejects_stale_or_foreign_variants(): void
    {
        Storage::fake('public');
        config()->set('media.product_disk', 'public');
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $category = Category::create(['name' => 'Vestidos', 'active' => true]);
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Beige', 'active' => true]);
        $product = Product::create(['sku' => 'PHOTO-1', 'name' => 'Vestido beige', 'category_id' => $category->id, 'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 100, 'sale_price' => 250, 'status' => 'disponible', 'catalog_published' => false, 'created_by' => $manager->id]);
        $variant = ProductVariant::create(['sku' => 'PHOTO-1-M', 'product_id' => $product->id, 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 4, 'purchase_price' => 100, 'sale_price' => 250, 'active' => true]);
        $other = Product::create(['sku' => 'PHOTO-2', 'name' => 'Otro vestido', 'category_id' => $category->id, 'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 100, 'sale_price' => 250, 'status' => 'disponible', 'created_by' => $manager->id]);
        $foreignVariant = ProductVariant::create(['sku' => 'PHOTO-2-M', 'product_id' => $other->id, 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 4, 'purchase_price' => 100, 'sale_price' => 250, 'active' => true]);
        $product->forceFill(['updated_at' => now()->subMinute()])->saveQuietly();

        $this->actingAs($manager)->postJson('/products/'.$product->id.'/bulk-images', [
            'expected_updated_at' => '2000-01-01T00:00:00Z',
            'images' => [UploadedFile::fake()->image('stale.jpg')],
        ])->assertUnprocessable()->assertJsonValidationErrors('expected_updated_at');
        $this->assertDatabaseMissing('product_images', ['product_id' => $product->id]);

        $this->postJson('/products/'.$product->id.'/bulk-images', [
            'expected_updated_at' => $product->fresh()->updated_at->toISOString(),
            'images' => [UploadedFile::fake()->image('foreign.jpg')],
            'image_variant_ids' => [$foreignVariant->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('image_variant_ids.0');

        $response = $this->postJson('/products/'.$product->id.'/bulk-images', [
            'expected_updated_at' => $product->fresh()->updated_at->toISOString(),
            'images' => [UploadedFile::fake()->image('vestido.jpg')],
            'image_variant_ids' => [$variant->id],
            'name' => 'Cambio accidental',
            'sale_price_base' => 1,
        ])->assertOk()->assertJsonCount(1, 'data.images');
        $this->assertSame('Vestido beige', $product->fresh()->name);
        $this->assertSame(250.0, (float) $product->fresh()->sale_price);
        $this->assertFalse((bool) $product->fresh()->catalog_published);
        $image = ProductImage::query()->where('product_id', $product->id)->sole();
        $this->assertSame($variant->id, $image->product_variant_id);
        Storage::disk('public')->assertExists($image->path);

        $this->postJson('/products/'.$product->id.'/bulk-images', [
            'expected_updated_at' => $response->json('data.updated_at'),
            'images' => [UploadedFile::fake()->image('segunda.jpg')],
        ])->assertOk()->assertJsonCount(2, 'data.images');
    }
}
