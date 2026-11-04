<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use App\Models\User;
use App\Services\CatalogCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_product_without_photo_stays_sellable_but_hidden_until_published_with_photo(): void
    {
        Storage::fake('public');
        config(['media.product_disk' => 'public']);
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $category = Category::create(['name' => 'Vestidos', 'active' => true]);
        DB::transaction(fn () => app(CatalogCodeService::class)->setPrefix($category, 'VES'));
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Negro', 'active' => true]);

        $this->actingAs($manager)->post('/products', [
            'name' => 'Vestido nuevo', 'category_id' => $category->id, 'gender' => 'dama',
            'variants' => [['size_id' => $size->id, 'color_id' => $color->id, 'stock' => 1, 'sale_price' => 250]],
        ])->assertRedirect();
        $product = Product::firstOrFail();
        $this->assertFalse($product->catalog_published);
        $this->get('/catalogo/p/'.$product->sku)->assertNotFound();

        $this->post('/products/'.$product->id, [
            '_method' => 'put', 'sku' => $product->sku, 'name' => $product->name,
            'category_id' => $category->id, 'gender' => 'dama', 'catalog_published' => true,
            'variants' => [['id' => $product->variants()->first()->id, 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 1, 'sale_price' => 250]],
            'images' => [UploadedFile::fake()->image('vestido.jpg', 800, 1000)],
        ])->assertRedirect();
        $this->assertTrue($product->fresh()->catalog_published);
        $this->get('/catalogo')->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Public/Catalog/Index')
            ->where('products.meta.total', 1)
            ->where('products.meta.from', 1)
            ->where('products.meta.to', 1)
            ->etc());
        $this->get('/catalogo/p/'.$product->sku)->assertOk();
    }
}
