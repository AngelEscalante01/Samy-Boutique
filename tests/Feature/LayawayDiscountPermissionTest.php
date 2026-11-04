<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use App\Services\LayawayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayawayDiscountPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_apply_basic_discount_but_cannot_create_or_edit_a_high_discount_layaway(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $cashier = User::factory()->create(['active' => true]);
        $cashier->assignRole('cajero');
        $category = Category::create(['name' => 'Vestidos', 'active' => true]);
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Negro', 'active' => true]);
        $product = Product::create([
            'sku' => 'PERM-LAY', 'name' => 'Vestido', 'category_id' => $category->id,
            'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id,
            'purchase_price' => 100, 'sale_price' => 250, 'status' => 'disponible', 'created_by' => $manager->id,
        ]);
        $variant = ProductVariant::create([
            'sku' => 'PERM-LAY-M', 'product_id' => $product->id,
            'size_id' => $size->id, 'color_id' => $color->id,
            'stock' => 10, 'sale_price' => 250, 'active' => true,
        ]);
        $items = [['variant_id' => $variant->id, 'qty' => 2]];

        $this->actingAs($cashier)->post(route('layaways.store'), [
            'items' => $items, 'global_discount_type' => 'percent', 'global_discount_value' => 20,
        ])->assertSessionHasErrors('global_discount_value');
        $this->assertDatabaseCount('layaways', 0);
        $this->assertSame(10, $variant->fresh()->stock);

        $this->post(route('layaways.store'), [
            'items' => $items, 'global_discount_type' => 'percent', 'global_discount_value' => 10,
        ])->assertRedirect();
        $layaway = \App\Models\Layaway::query()->sole();
        $this->assertSame('50.00', $layaway->manual_discount_total);

        $adjustment = [
            'version' => $layaway->version, 'items' => $items,
            'global_discount_type' => 'percent', 'global_discount_value' => 20,
            'reason' => 'Intento de descuento alto',
        ];
        $this->postJson(route('layaways.adjustment.preview', $layaway), $adjustment)
            ->assertUnprocessable()->assertJsonValidationErrors('global_discount_value');
        $this->put(route('layaways.adjustment.update', $layaway), $adjustment)
            ->assertSessionHasErrors('global_discount_value');
        $this->assertSame('50.00', $layaway->fresh()->manual_discount_total);
        $this->assertSame(1, $layaway->fresh()->version);

        $this->actingAs($manager)->put(route('layaways.adjustment.update', $layaway), $adjustment)
            ->assertRedirect();
        $this->assertSame('100.00', $layaway->fresh()->manual_discount_total);
    }

    public function test_cashier_can_preserve_an_existing_manager_approved_discount_without_changing_it(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $cashier = User::factory()->create(['active' => true]);
        $cashier->assignRole('cajero');
        $category = Category::create(['name' => 'Vestidos', 'active' => true]);
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Negro', 'active' => true]);
        $product = Product::create([
            'sku' => 'PERM-PRESERVE', 'name' => 'Vestido', 'category_id' => $category->id,
            'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id,
            'purchase_price' => 100, 'sale_price' => 250, 'status' => 'disponible', 'created_by' => $manager->id,
        ]);
        $variant = ProductVariant::create([
            'sku' => 'PERM-PRESERVE-M', 'product_id' => $product->id,
            'size_id' => $size->id, 'color_id' => $color->id,
            'stock' => 10, 'sale_price' => 250, 'active' => true,
        ]);
        $layaway = app(LayawayService::class)->create([
            'items' => [['variant_id' => $variant->id, 'qty' => 2]],
            'global_discount_type' => 'percent', 'global_discount_value' => 20,
        ], $manager);
        $this->actingAs($cashier)->put(route('layaways.adjustment.update', $layaway), [
            'version' => $layaway->version,
            'items' => [['variant_id' => $variant->id, 'qty' => 2]],
            'global_discount_type' => 'percent', 'global_discount_value' => 20,
            'reason' => 'Actualizar datos sin cambiar descuento',
        ])->assertRedirect();
        $this->assertSame('100.00', $layaway->fresh()->manual_discount_total);
    }

    public function test_layaway_routes_require_their_business_permissions(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $cashier = User::factory()->create(['active' => true]);
        $cashier->assignRole('cajero');
        $posOnly = User::factory()->create(['active' => true]);
        $posOnly->givePermissionTo('pos.view');
        $layaway = \App\Models\Layaway::create([
            'created_by' => $manager->id, 'status' => 'open',
            'subtotal' => '100.00', 'paid_total' => '0.00',
        ]);

        $this->actingAs($posOnly)->get(route('pos.index'))->assertOk();
        $this->get(route('layaways.index'))->assertForbidden();
        $this->get(route('layaways.create'))->assertForbidden();
        $this->post(route('layaways.store'), ['items' => []])->assertForbidden();
        $this->post(route('layaways.payments.store', $layaway), ['method' => 'cash', 'amount' => 10])->assertForbidden();
        $this->post(route('layaways.cancel', $layaway))->assertForbidden();

        $this->actingAs($cashier)->get(route('layaways.index'))->assertOk();
        $this->get(route('layaways.create'))->assertOk();
        $this->post(route('layaways.cancel', $layaway))->assertForbidden();
        $this->assertSame('open', $layaway->fresh()->status);
    }

    public function test_manager_and_cashier_can_open_their_web_workflows_without_crossing_roles(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $cashier = User::factory()->create(['active' => true]);
        $cashier->assignRole('cajero');

        $shared = ['dashboard', 'pos.index', 'products.index',
            'customers.index', 'layaways.index', 'sales.index'];
        $managerOnly = ['products.create', 'products.bulk', 'catalog-imports.index',
            'catalog-slides.index', 'catalogs.occasions.index', 'reports.index',
            'cashcuts.index', 'settings.index'];

        $this->actingAs($manager);
        foreach (array_merge($shared, $managerOnly) as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('product-labels.index'))
            ->assertRedirect(route('products.index', ['labels' => '1']));
        $this->getJson(route('product-labels.index'))->assertOk();

        $this->actingAs($cashier);
        foreach ($shared as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('product-labels.index'))
            ->assertRedirect(route('products.index', ['labels' => '1']));
        $this->getJson(route('product-labels.index'))->assertOk();
        foreach ($managerOnly as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }
}
