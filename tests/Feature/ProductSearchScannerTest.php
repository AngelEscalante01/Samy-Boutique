<?php
namespace Tests\Feature;

use App\Models\{Category, Color, Product, ProductVariant, Size, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductSearchScannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_scan_finds_its_parent_in_web_and_android_without_escaping_filters(): void
    {
        $this->withoutVite();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['active' => true]);
        $user->assignRole('gerente');
        $category = Category::create(['name' => 'QA Scanner', 'active' => true]);
        $size = Size::create(['name' => 'QA M', 'active' => true]);
        $color = Color::create(['name' => 'QA Azul', 'active' => true]);
        $product = Product::create(['sku' => 'QA-PARENT', 'name' => 'QA scanner fixture',
            'category_id' => $category->id, 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 40, 'gender' => 'dama', 'sale_price' => 100,
            'status' => 'disponible', 'created_by' => $user->id]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'QAT-000015',
            'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 5,
            'sale_price' => 100, 'active' => true]);
        // Two matching variants still produce one parent row.
        ProductVariant::create(['product_id' => $product->id, 'sku' => 'QAT-000016',
            'size_id' => Size::create(['name' => 'QA L', 'active' => true])->id, 'color_id' => $color->id, 'stock' => 2,
            'sale_price' => 100, 'active' => true]);
        $this->actingAs($user)->get('/products?q=QAT-000015')->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->has('products.data', 1)->where('products.data.0.id', $product->id)->etc());
        Sanctum::actingAs($user);
        $this->getJson('/api/mobile/products?q=QAT-000015')->assertOk()
            ->assertJsonCount(1, 'data.products.data')
            ->assertJsonPath('data.products.data.0.id', $product->id);
        $this->getJson('/api/mobile/products?q=QAT-00001')->assertOk()
            ->assertJsonCount(1, 'data.products.data');
        foreach (['status=vendido', 'gender=caballero', 'category_id=99999'] as $filter) {
            $this->getJson('/api/mobile/products?q=QAT-000015&'.$filter)->assertOk()
                ->assertJsonCount(0, 'data.products.data');
        }
        $this->getJson('/api/mobile/products?q=SAMY-UNKNOWN')->assertOk()
            ->assertJsonCount(0, 'data.products.data');
        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('product_label_jobs', 0);
    }
}