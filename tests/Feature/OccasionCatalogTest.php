<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Color;
use App\Models\Occasion;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OccasionCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_names_are_normalized_and_many_to_many_filter_uses_any_selected_occasion(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $this->actingAs($manager)->post('/catalogs/occasions', ['name' => '  XV   Años ', 'sort_order' => 1, 'active' => true])->assertRedirect();
        $this->assertDatabaseHas('occasions', ['normalized_name' => 'xv-anos']);
        $this->post('/catalogs/occasions', ['name' => 'XV anos', 'sort_order' => 2, 'active' => true])->assertSessionHasErrors('name');

        $first = Occasion::firstOrFail();
        $second = Occasion::create(['name' => 'Bodas', 'normalized_name' => 'bodas', 'sort_order' => 2, 'active' => true]);
        $third = Occasion::create(['name' => 'Vestido de noche', 'normalized_name' => 'vestido-de-noche', 'sort_order' => 3, 'active' => true]);
        $category = Category::create(['name' => 'Vestidos', 'active' => true]);
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Negro', 'active' => true]);
        $product = Product::create(['sku' => 'OCC-1', 'name' => 'Vestido ocasión', 'category_id' => $category->id, 'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 1, 'sale_price' => 200, 'status' => 'disponible', 'created_by' => $manager->id]);
        ProductVariant::create(['sku' => 'OCC-1-M', 'product_id' => $product->id, 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 2, 'sale_price' => 200, 'active' => true]);
        $product->occasions()->sync([$first->id, $second->id, $third->id]);

        foreach ([$first, $second, $third] as $occasion) {
            $this->get('/catalogo?occasion_ids[0]='.$occasion->id)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Public/Catalog/Index')->has('products.data', 1)->where('products.data.0.sku', 'OCC-1')->etc());
        }
        $this->get('/catalogo?occasion_ids[0]=99999')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Catalog/Index')->has('products.data', 0)->etc());
    }
}
