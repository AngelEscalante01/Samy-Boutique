<?php

namespace Tests\Feature;

use App\Models\{Category, Color, Product, ProductVariant, Size, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Queue, Route};
use Tests\TestCase;

class NativeParityApiTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->manager = User::factory()->create(['active' => true]);
        $this->manager->assignRole('gerente');
        $this->withToken($this->manager->createToken('native-test')->plainTextToken);
    }

    public function test_native_screens_return_data_without_html_and_reuse_dashboard_balances(): void
    {
        $this->getJson('/api/mobile/dashboard')->assertOk()->assertJsonStructure(['data' => ['snapshot' => ['summary', 'chart', 'recentSales', 'recentLayaways']]])->assertJsonMissingPath('component');
        $this->getJson('/api/mobile/users')->assertOk()->assertJsonStructure(['data' => ['users', 'roles']]);
        $this->getJson('/api/mobile/settings')->assertOk()->assertJsonPath('data.settings.loyalty.enabled', false);
        $cashier = User::factory()->create(['active' => true]);
        $cashier->assignRole('cajero');
        auth()->forgetGuards();
        $this->withToken($cashier->createToken('native-test')->plainTextToken);
        $this->getJson('/api/mobile/users')->assertForbidden();
        $this->getJson('/api/mobile/settings')->assertForbidden();
        $this->getJson('/api/mobile/catalog-slides')->assertForbidden();
    }

    public function test_movement_timestamps_keep_the_same_instant_as_sale_and_payment_details(): void
    {
        config(['app.timezone' => 'UTC']);
        $variant = $this->variant();
        $this->postJson('/api/sales', ['items' => [['variant_id' => $variant->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 250]], 'dinero_recibido' => 250,
        ])->assertCreated();
        $sale = \App\Models\Sale::firstOrFail();
        $sale->forceFill(['created_at' => '2026-10-30 04:47:17'])->save();
        $customer = \App\Models\Customer::create(['name' => 'QA fecha de movimiento', 'active' => true]);
        $this->postJson('/api/mobile/layaways', ['customer_id' => $customer->id,
            'items' => [['variant_id' => $variant->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 50]],
        ], ['Idempotency-Key' => 'native-timestamp-fixture-001'])->assertOk();
        $payment = \App\Models\LayawayPayment::firstOrFail();
        $payment->forceFill(['paid_at' => '2026-10-30 04:47:17', 'created_at' => '2026-10-30 04:47:17'])->save();

        $movements = collect($this->getJson('/api/mobile/sales')->assertOk()->json('data.sales.data'));
        foreach (['venta', 'abono'] as $kind) {
            $stamp = $movements->firstWhere('movement_type', $kind)['created_at'];
            $this->assertSame('2026-10-30T04:47:17.000000Z', $stamp);
            $this->assertSame('2026-10-29 22:47:17', \Carbon\CarbonImmutable::parse($stamp)->setTimezone('America/Mexico_City')->format('Y-m-d H:i:s'));
        }
        $saleTime = $this->getJson('/api/mobile/sales/'.$sale->id)->assertOk()->json('data.sale.created_at');
        $paymentTime = $this->getJson('/api/mobile/sales/movements/abono/'.$payment->id)->assertOk()->json('data.movement.created_at');
        $this->assertTrue(\Carbon\CarbonImmutable::parse($saleTime)->equalTo($movements->firstWhere('movement_type', 'venta')['created_at']));
        $this->assertTrue(\Carbon\CarbonImmutable::parse($paymentTime)->equalTo($movements->firstWhere('movement_type', 'abono')['created_at']));

        $cutScreen = $this->getJson('/api/mobile/cash-cuts?date=2026-10-30')->assertOk();
        $cutSale = collect($cutScreen->json('data.sales'))->firstWhere('id', $sale->id);
        $this->assertSame($saleTime, $cutSale['created_at']);
        $this->assertSame('2026-10-29 22:47:17', \Carbon\CarbonImmutable::parse($cutSale['created_at'])->setTimezone('America/Mexico_City')->format('Y-m-d H:i:s'));
        $cut = \App\Models\CashCut::create(['cut_date' => '2026-10-30', 'created_by' => $this->manager->id,
            'totals_json' => $cutScreen->json('data.summary')]);
        $cut->forceFill(['created_at' => '2026-10-30 04:47:17'])->save();
        $this->getJson('/api/mobile/cash-cuts/'.$cut->id)->assertOk()
            ->assertJsonPath('data.cashCut.created_at', '2026-10-30T04:47:17.000000Z')
            ->assertJsonPath('data.cashCut.cut_date', '2026-10-30');
        $this->getJson('/api/mobile/cash-cuts')->assertOk()
            ->assertJsonPath('data.savedCuts.0.created_at', '2026-10-30T04:47:17.000000Z');
        $sale->forceFill(['status' => 'cancelled'])->save();
        $metrics = app(\App\Services\SalesAnalyticsService::class)->buildDailyCutMetrics('2026-10-30');
        $this->assertSame($saleTime, $metrics['summary']['cancelled_sales'][0]['created_at']);
    }

    public function test_native_redirect_actions_commit_and_replay_only_once(): void
    {
        $headers = ['Idempotency-Key' => 'native-category-operation-001'];
        $payload = ['name' => 'QA nativa', 'sku_prefix' => 'QAN', 'active' => true];
        $first = $this->postJson('/api/mobile/catalogs/categories', $payload, $headers)->assertOk()->assertJsonPath('success', true);
        $second = $this->postJson('/api/mobile/catalogs/categories', $payload, $headers)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertDatabaseCount('categories', 1);
        $this->getJson('/api/mobile/catalogs/categories')->assertOk()->assertJsonPath('data.categories.data.0.name', 'QA nativa');
    }

    public function test_product_create_and_edit_use_the_same_variant_and_cost_rules_as_web(): void
    {
        $category = Category::create(['name' => 'Prueba', 'sku_prefix' => 'QAT', 'active' => true]);
        app(\App\Services\CatalogCodeService::class)->setPrefix($category, 'QAT');
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Negro', 'hex' => '#000000', 'active' => true]);
        $payload = ['name' => 'Prenda QA nativa', 'category_id' => $category->id, 'gender' => 'unisex',
            'variants' => [['size_id' => $size->id, 'color_id' => $color->id, 'stock' => 3, 'sale_price' => 250, 'purchase_price' => 100]]];
        $headers = ['Idempotency-Key' => 'native-product-operation-0001'];
        $result = $this->postJson('/api/mobile/products', $payload, $headers);
        $result->assertOk()->assertJsonPath('success', true);
        $this->postJson('/api/mobile/products', $payload, $headers)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertDatabaseCount('products', 1);
        $product = Product::firstOrFail();
        $this->getJson('/api/mobile/products/'.$product->id)->assertOk()->assertJsonPath('data.product.variants.0.stock', 3);
        $edit = $this->getJson('/api/mobile/products/'.$product->id.'/edit')->assertOk()->assertJsonPath('data.product.name', 'Prenda QA nativa');
        $payload['expected_updated_at'] = $edit->json('data.product.updated_at');
        $payload['variants'][0]['id'] = ProductVariant::firstOrFail()->id;
        $payload['variants'][0]['stock'] = 4;
        $this->putJson('/api/mobile/products/'.$product->id, $payload)->assertOk();
        $this->assertSame(4, ProductVariant::firstOrFail()->stock);
    }

    public function test_file_content_is_part_of_the_idempotent_identity(): void
    {
        Route::middleware(['auth:sanctum', \App\Http\Middleware\IdempotentApiOperation::class])
            ->post('/api/native-file-fixture', fn () => response()->json(['success' => true], 201));
        $headers = ['Idempotency-Key' => 'native-upload-operation-0001'];
        $this->post('/api/native-file-fixture', ['file' => UploadedFile::fake()->createWithContent('qa.txt', 'first')], $headers)->assertCreated();
        $this->post('/api/native-file-fixture', ['file' => UploadedFile::fake()->createWithContent('qa.txt', 'first')], $headers)->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->post('/api/native-file-fixture', ['file' => UploadedFile::fake()->createWithContent('qa.txt', 'changed')], $headers)->assertConflict();
    }
    private function variant(): ProductVariant
    {
        $category = Category::create(['name' => 'QA categoría', 'active' => true]);
        $size = Size::create(['name' => 'QA talla', 'active' => true]);
        $color = Color::create(['name' => 'QA color', 'active' => true]);
        $product = Product::create(['sku' => 'QA-NATIVE', 'name' => 'Prenda sintética', 'category_id' => $category->id,
            'size_id' => $size->id, 'color_id' => $color->id, 'gender' => 'unisex', 'purchase_price' => 100,
            'sale_price' => 250, 'sale_price_base' => 250, 'status' => 'disponible', 'created_by' => $this->manager->id]);
        return ProductVariant::create(['product_id' => $product->id, 'sku' => 'QA-NATIVE-M', 'size_id' => $size->id,
            'color_id' => $color->id, 'stock' => 10, 'active' => true, 'sale_price' => 250, 'purchase_price' => 100]);
    }

    public function test_all_native_modules_expose_their_screen_contracts(): void
    {
        $this->variant();
        foreach (['products', 'products/create', 'products/bulk', 'customers', 'customers/create', 'catalogs/categories',
            'catalogs/sizes', 'catalogs/colors', 'catalogs/occasions', 'coupons', 'coupons/create', 'catalog-slides',
            'layaways', 'layaways/create', 'reports?from=2026-10-01&to=2026-10-29', 'cash-cuts?date=2026-10-29',
            'notifications/email-settings', 'catalog-imports', 'notifications/recipients', 'notifications/sent-history'] as $path) {
            $this->getJson('/api/mobile/'.$path)->assertOk()->assertJsonPath('success', true)->assertJsonMissingPath('component');
        }
    }

    public function test_permission_changes_also_block_receipt_replays(): void
    {
        $payload = ['name' => 'Categoría antes del cambio', 'active' => true];
        $headers = ['Idempotency-Key' => 'native-permission-replay-0001'];
        $this->postJson('/api/mobile/catalogs/categories', $payload, $headers)->assertOk();
        $this->manager->syncRoles(['cajero']);
        auth()->forgetGuards();
        $this->postJson('/api/mobile/catalogs/categories', $payload, $headers)->assertForbidden();
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_native_layaway_adjustment_refund_delivery_and_retries_are_atomic(): void
    {
        $variant = $this->variant();
        $customer = \App\Models\Customer::create(['name' => 'Cliente QA nativo', 'active' => true]);
        $payload = ['customer_id' => $customer->id, 'items' => [['variant_id' => $variant->id, 'qty' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 100, 'payer_name' => 'QA']], 'vigencia_dias' => 15];
        $headers = ['Idempotency-Key' => 'native-layaway-create-0001'];
        $this->postJson('/api/mobile/layaways', $payload, $headers)->assertOk();
        $this->postJson('/api/mobile/layaways', $payload, $headers)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $layaway = \App\Models\Layaway::firstOrFail();
        $this->assertSame(8, $variant->fresh()->stock);
        $this->postJson("/api/mobile/layaways/{$layaway->id}/liquidate", ['payments' => [['method' => 'card', 'amount' => 400]]], ['Idempotency-Key' => 'native-liquidate-0001'])->assertOk();
        $adjust = ['version' => $layaway->fresh()->version, 'customer_id' => $customer->id,
            'items' => [['variant_id' => $variant->id, 'qty' => 1]], 'reason' => 'Retirar una prenda QA'];
        $this->postJson("/api/mobile/layaways/{$layaway->id}/adjustment-preview", $adjust)->assertOk()->assertJsonPath('data.excess', '250.00');
        $headers = ['Idempotency-Key' => 'native-adjustment-0001'];
        $this->putJson("/api/mobile/layaways/{$layaway->id}/adjustment", $adjust, $headers)->assertOk();
        $this->putJson("/api/mobile/layaways/{$layaway->id}/adjustment", $adjust, $headers)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame(9, $variant->fresh()->stock);
        $refund = ['amount' => 250, 'method' => 'cash', 'reason' => 'Excedente QA'];
        $headers = ['Idempotency-Key' => 'native-refund-0001'];
        $this->postJson("/api/mobile/layaways/{$layaway->id}/refunds", $refund, $headers)->assertOk();
        $this->postJson("/api/mobile/layaways/{$layaway->id}/refunds", $refund, $headers)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertDatabaseCount('layaway_refunds', 1);
        $this->assertDatabaseCount('layaway_adjustments', 1);
        $this->postJson("/api/mobile/layaways/{$layaway->id}/deliver", [], ['Idempotency-Key' => 'native-deliver-0001'])->assertOk();
        $this->getJson("/api/mobile/layaways/{$layaway->id}")->assertOk()->assertJsonPath('data.layaway.balance', '0.00')->assertJsonPath('data.layaway.excess', '0.00');
        $this->assertNotNull($layaway->fresh()->delivered_at);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('layaway_payments', 2);
    }

    public function test_native_cash_cuts_are_immutable_and_validation_keeps_no_receipt(): void
    {
        $headers = ['Idempotency-Key' => 'native-cut-final-0001'];
        $this->postJson('/api/mobile/cash-cuts', ['date' => '2026-10-29'], $headers)->assertOk();
        $this->postJson('/api/mobile/cash-cuts', ['date' => '2026-10-29'], $headers)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $cut = \App\Models\CashCut::firstOrFail();
        $snapshot = $cut->totals_json;
        $this->postJson('/api/mobile/cash-cuts', ['date' => '2026-10-29'], ['Idempotency-Key' => 'native-cut-other-0001'])->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertSame($snapshot, $cut->fresh()->totals_json);
        $this->assertDatabaseCount('cash_cuts', 1);
        $this->assertDatabaseMissing('api_operation_receipts', ['operation_key' => 'native-cut-other-0001']);
    }

    public function test_native_profile_and_password_use_current_password_without_browser_session(): void
    {
        $this->manager->update(['password' => 'QA-Initial-Password!']);
        $this->patchJson('/api/mobile/profile', ['name' => 'Gerente sintético QA', 'email' => 'gerente.qa@example.test'], ['Idempotency-Key' => 'native-profile-update-0001'])->assertOk();
        $this->putJson('/api/mobile/profile/password', ['current_password' => 'wrong', 'password' => 'QA-New-Password!', 'password_confirmation' => 'QA-New-Password!'], ['Idempotency-Key' => 'native-profile-password-wrong'])->assertUnprocessable();
        $payload = ['current_password' => 'QA-Initial-Password!', 'password' => 'QA-New-Password!', 'password_confirmation' => 'QA-New-Password!'];
        $headers = ['Idempotency-Key' => 'native-profile-password-0001'];
        $this->putJson('/api/mobile/profile/password', $payload, $headers)->assertOk();
        $this->putJson('/api/mobile/profile/password', $payload, $headers)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('QA-New-Password!', $this->manager->fresh()->password));
    }

    public function test_native_payment_can_auto_liquidate_once_when_requested(): void
    {
        $variant = $this->variant();
        $layaway = app(\App\Services\LayawayService::class)->create([
            'items' => [['variant_id' => $variant->id, 'qty' => 1]],
        ], $this->manager);
        $payload = ['method' => 'cash', 'amount' => 250, 'auto_liquidate' => true, 'payer_name' => 'Pagador QA'];
        $headers = ['Idempotency-Key' => 'native-payment-liquidate-0001'];
        $path = "/api/mobile/layaways/{$layaway->id}/payments";
        $this->postJson($path, $payload, $headers)->assertCreated()->assertJsonPath('data.liquidated', true);
        $this->postJson($path, $payload, $headers)->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('layaway_payments', 1);
        $this->assertSame('liquidated', $layaway->fresh()->status);
        $this->assertSame(9, $variant->fresh()->stock);
    }

}
