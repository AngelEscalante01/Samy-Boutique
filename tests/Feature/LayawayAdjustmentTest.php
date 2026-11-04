<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use App\Services\LayawayService;
use App\Services\SalesAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LayawayAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_change_addition_and_garment_swap_preserve_payments_and_adjust_stock(): void
    {
        Queue::fake();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $firstCustomer = Customer::create(['name' => 'Ana', 'active' => true]);
        $secondCustomer = Customer::create(['name' => 'Beatriz', 'active' => true]);
        $category = Category::create(['name' => 'Vestidos', 'active' => true]);
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Negro', 'active' => true]);
        $variants = [];
        foreach ([['A', 250, 5], ['B', 300, 3], ['C', 200, 2]] as [$sku, $price, $stock]) {
            $product = Product::create([
                'sku' => 'LAY-'.$sku, 'name' => 'Vestido '.$sku, 'category_id' => $category->id,
                'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id,
                'purchase_price' => 100, 'sale_price' => $price, 'status' => 'disponible', 'created_by' => $manager->id,
            ]);
            $variants[$sku] = ProductVariant::create([
                'sku' => 'LAY-'.$sku.'-M', 'product_id' => $product->id,
                'size_id' => $size->id, 'color_id' => $color->id,
                'stock' => $stock, 'sale_price' => $price, 'active' => true,
            ]);
        }
        $layaway = app(LayawayService::class)->create([
            'customer_id' => $firstCustomer->id,
            'items' => [['variant_id' => $variants['A']->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ], $manager);
        $this->actingAs($manager);

        $customerChange = [
            'version' => $layaway->version, 'customer_id' => $secondCustomer->id,
            'items' => [['variant_id' => $variants['A']->id, 'qty' => 1]],
            'reason' => 'Corregir cliente',
        ];
        $this->postJson("/layaways/{$layaway->id}/adjustment-preview", $customerChange)
            ->assertOk()->assertJsonPath('customer_id', $secondCustomer->id);
        $this->put("/layaways/{$layaway->id}/adjustment", $customerChange)->assertRedirect();
        $this->assertSame($secondCustomer->id, $layaway->fresh()->customer_id);
        $this->assertSame(4, $variants['A']->fresh()->stock);

        $addPrenda = [
            'version' => $layaway->fresh()->version, 'customer_id' => $secondCustomer->id,
            'items' => [
                ['variant_id' => $variants['A']->id, 'qty' => 1],
                ['variant_id' => $variants['B']->id, 'qty' => 1],
            ],
            'reason' => 'Agregar vestido B',
        ];
        $this->postJson("/layaways/{$layaway->id}/adjustment-preview", $addPrenda)
            ->assertOk()->assertJsonPath('total', '550.00')->assertJsonPath('balance', '450.00');
        $this->put("/layaways/{$layaway->id}/adjustment", $addPrenda)->assertRedirect();
        $this->assertSame(2, $variants['B']->fresh()->stock);

        $swapPrenda = [
            'version' => $layaway->fresh()->version, 'customer_id' => $secondCustomer->id,
            'items' => [
                ['variant_id' => $variants['B']->id, 'qty' => 1],
                ['variant_id' => $variants['C']->id, 'qty' => 1],
            ],
            'reason' => 'Sustituir vestido A por C',
        ];
        $this->postJson("/layaways/{$layaway->id}/adjustment-preview", $swapPrenda)
            ->assertOk()->assertJsonPath('total', '500.00')->assertJsonPath('balance', '400.00');
        $this->put("/layaways/{$layaway->id}/adjustment", $swapPrenda)->assertRedirect();
        $this->assertSame(5, $variants['A']->fresh()->stock);
        $this->assertSame(2, $variants['B']->fresh()->stock);
        $this->assertSame(1, $variants['C']->fresh()->stock);
        $this->assertSame('100.00', $layaway->fresh()->paid_total);
        $this->assertSame(1, $layaway->payments()->count());
        $this->assertSame(2, $layaway->items()->count());
        $this->assertSame(3, $layaway->adjustments()->count());
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_liquidated_undelivered_layaway_can_be_changed_refunded_and_delivered(): void
    {
        Queue::fake();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $category = Category::create(['name' => 'Vestidos', 'active' => true]);
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Negro', 'active' => true]);
        $product = Product::create(['sku' => 'LAY-2', 'name' => 'Vestido', 'category_id' => $category->id, 'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 100, 'sale_price' => 250, 'status' => 'disponible', 'created_by' => $manager->id]);
        $variant = ProductVariant::create(['sku' => 'LAY-2-M', 'product_id' => $product->id, 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 5, 'sale_price' => 250, 'active' => true]);
        $layaway = app(LayawayService::class)->create([
            'items' => [['variant_id' => $variant->id, 'qty' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 200]],
        ], $manager);
        $sale = app(LayawayService::class)->liquidate($layaway, ['payments' => [['method' => 'card', 'amount' => 300]]], $manager);
        $layaway->refresh();
        $this->assertSame('500.00', $layaway->paid_total);
        $this->assertSame(2, $layaway->payments()->count());
        $this->assertSame('500.00', $sale->total);

        $payload = ['version' => 1, 'items' => [['variant_id' => $variant->id, 'qty' => 1]], 'reason' => 'Se retiró una prenda'];
        $this->actingAs($manager)->postJson("/layaways/{$layaway->id}/adjustment-preview", $payload)
            ->assertOk()->assertJsonPath('excess', '250.00');
        $this->put("/layaways/{$layaway->id}/adjustment", $payload)->assertRedirect();
        $this->assertSame('250.00', $layaway->fresh()->excess);
        $this->assertSame('250.00', $sale->fresh()->total);
        $this->assertSame(1, $sale->items()->first()->qty);
        $this->assertSame(4, $variant->fresh()->stock);

        $this->post("/layaways/{$layaway->id}/refunds", ['amount' => 250, 'method' => 'cash', 'reason' => 'Excedente'])
            ->assertRedirect();
        $this->assertSame('0.00', $layaway->fresh()->excess);
        $this->assertSame('-250.00', $sale->payments()->where('amount', '<', 0)->first()->amount);
        $this->post("/layaways/{$layaway->id}/deliver")->assertRedirect();
        $this->assertNotNull($layaway->fresh()->delivered_at);
        $this->put("/layaways/{$layaway->id}/adjustment", ['version' => 2, 'items' => [['variant_id' => $variant->id, 'qty' => 2]], 'reason' => 'Después de entrega'])
            ->assertSessionHasErrors('layaway');
        $cut = app(SalesAnalyticsService::class)->buildDailyCutMetrics(now()->toDateString());
        $this->assertSame(-50.0, $cut['summary']['payments']['cash']);
        $this->assertSame(300.0, $cut['summary']['payments']['card']);
    }

    public function test_reducing_to_one_unit_clears_discount_and_excess_is_refunded_once(): void
    {
        Queue::fake();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $customer = Customer::create(['name' => 'Nueva clienta', 'active' => true]);
        $category = Category::create(['name' => 'Vestidos', 'active' => true]);
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Negro', 'active' => true]);
        $product = Product::create(['sku' => 'LAY-1', 'name' => 'Vestido', 'category_id' => $category->id, 'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 100, 'sale_price' => 250, 'status' => 'disponible', 'created_by' => $manager->id]);
        $variant = ProductVariant::create(['sku' => 'LAY-1-M', 'product_id' => $product->id, 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 5, 'sale_price' => 250, 'active' => true]);
        $layaway = app(LayawayService::class)->create([
            'items' => [['variant_id' => $variant->id, 'qty' => 2]],
            'global_discount_type' => 'amount', 'global_discount_value' => 100,
            'payments' => [['method' => 'cash', 'amount' => 400]],
        ], $manager);
        $this->assertSame('400.00', $layaway->total);
        $this->assertSame(3, $variant->fresh()->stock);

        $payload = [
            'version' => $layaway->version, 'customer_id' => $customer->id,
            'items' => [['variant_id' => $variant->id, 'qty' => 1]],
            'global_discount_type' => 'amount', 'global_discount_value' => 100,
            'reason' => 'Cliente retiró una prenda',
        ];
        $this->actingAs($manager)->postJson("/layaways/{$layaway->id}/adjustment-preview", $payload)
            ->assertOk()->assertJsonPath('total', '250.00')->assertJsonPath('excess', '150.00');
        $this->put("/layaways/{$layaway->id}/adjustment", $payload)->assertRedirect();
        $layaway->refresh();
        $this->assertSame('0.00', $layaway->manual_discount_total);
        $this->assertSame('150.00', $layaway->excess);
        $this->assertSame($customer->id, $layaway->customer_id);
        $this->assertSame(4, $variant->fresh()->stock);
        $this->assertDatabaseHas('layaway_adjustments', ['layaway_id' => $layaway->id, 'version_before' => 1, 'version_after' => 2]);
        $this->put("/layaways/{$layaway->id}/adjustment", $payload)->assertSessionHasErrors('version');

        $refund = ['amount' => 150, 'method' => 'cash', 'reason' => 'Excedente por ajuste'];
        $this->post("/layaways/{$layaway->id}/refunds", $refund)->assertRedirect();
        $this->assertSame('0.00', $layaway->fresh()->excess);
        $this->assertSame('400.00', $layaway->fresh()->paid_total);
        $this->assertSame('150.00', $layaway->fresh()->refunded_total);
        $this->post("/layaways/{$layaway->id}/refunds", $refund)->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('layaway_refunds', 1);
        $cut = app(SalesAnalyticsService::class)->buildDailyCutMetrics(now()->toDateString());
        $this->assertSame(250.0, $cut['summary']['payments']['cash']);
    }

    public function test_partial_refund_retry_does_not_create_another_refund_or_cash_movement(): void
    {
        Queue::fake();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $category = Category::create(['name' => 'Vestidos', 'active' => true]);
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Negro', 'active' => true]);
        $product = Product::create(['sku' => 'LAY-REFUND', 'name' => 'Vestido', 'category_id' => $category->id, 'gender' => 'dama', 'size_id' => $size->id, 'color_id' => $color->id, 'purchase_price' => 100, 'sale_price' => 250, 'status' => 'disponible', 'created_by' => $manager->id]);
        $variant = ProductVariant::create(['sku' => 'LAY-REFUND-M', 'product_id' => $product->id, 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 5, 'sale_price' => 250, 'active' => true]);
        $layaway = app(LayawayService::class)->create([
            'items' => [['variant_id' => $variant->id, 'qty' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 200]],
        ], $manager);
        $sale = app(LayawayService::class)->liquidate($layaway, ['payments' => [['method' => 'cash', 'amount' => 300]]], $manager);
        $this->actingAs($manager)->put("/layaways/{$layaway->id}/adjustment", [
            'version' => $layaway->version, 'items' => [['variant_id' => $variant->id, 'qty' => 1]], 'reason' => 'Retirar una prenda',
        ])->assertRedirect();
        $this->assertSame('250.00', $layaway->fresh()->excess);

        $payload = ['operation_key' => 'refund-retry-123456789', 'amount' => 100, 'method' => 'cash', 'reason' => 'Devolución parcial'];
        $this->post("/layaways/{$layaway->id}/refunds", $payload)->assertRedirect();
        $this->post("/layaways/{$layaway->id}/refunds", $payload)->assertRedirect();
        $this->assertSame('100.00', $layaway->fresh()->refunded_total);
        $this->assertSame('150.00', $layaway->fresh()->excess);
        $this->assertDatabaseCount('layaway_refunds', 1);
        $this->assertSame(1, $sale->payments()->where('amount', '<', 0)->count());

        $changed = $payload;
        $changed['amount'] = 50;
        $this->post("/layaways/{$layaway->id}/refunds", $changed)->assertSessionHasErrors('operation_key');
        $this->assertDatabaseCount('layaway_refunds', 1);
    }
}
