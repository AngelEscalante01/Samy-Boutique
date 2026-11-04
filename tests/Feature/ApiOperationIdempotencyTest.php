<?php

namespace Tests\Feature;

use App\Models\{Category, Color, Product, ProductVariant, Size, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ApiOperationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Queue::fake();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['active' => true]);
        $user->assignRole('gerente');
        $this->withToken($user->createToken('test')->plainTextToken);
        $category = Category::create(['name' => 'Fixture', 'active' => true]);
        $size = Size::create(['name' => 'M', 'active' => true]);
        $color = Color::create(['name' => 'Negro', 'hex' => '#000000', 'active' => true]);
        $product = Product::create(['sku' => 'IDEM-1', 'name' => 'Fixture', 'category_id' => $category->id, 'size_id' => $size->id, 'color_id' => $color->id, 'gender' => 'unisex', 'purchase_price' => 100, 'sale_price' => 250, 'sale_price_base' => 250, 'status' => 'disponible', 'created_by' => $user->id]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'IDEM-1-M', 'size_id' => $size->id, 'color_id' => $color->id, 'stock' => 3, 'sale_price' => 250, 'active' => true]);
        return [$variant, ['items' => [['variant_id' => $variant->id, 'qty' => 1]], 'payments' => [['method' => 'cash', 'amount' => 250]], 'dinero_recibido' => 250]];
    }

    public function test_lost_response_retry_returns_original_sale_without_changing_stock_or_payments(): void
    {
        [$variant, $payload] = $this->fixture();
        $headers = ['Idempotency-Key' => 'device-a:operation-000001'];
        $first = $this->postJson('/api/sales', $payload, $headers)->assertCreated();
        $events = \App\Models\SystemNotification::count();
        $second = $this->postJson('/api/sales', $payload, $headers)->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_payments', 1);
        $this->assertSame(2, $variant->fresh()->stock);
        $this->assertSame($events, \App\Models\SystemNotification::count());
    }

    public function test_same_identity_with_different_content_conflicts_and_validation_failure_can_be_corrected(): void
    {
        [$variant, $payload] = $this->fixture();
        $headers = ['Idempotency-Key' => 'device-a:operation-000002'];
        $invalid = $payload;
        $invalid['items'][0]['qty'] = 99;
        $this->postJson('/api/sales', $invalid, $headers)->assertUnprocessable();
        $this->assertDatabaseCount('api_operation_receipts', 0);
        $this->postJson('/api/sales', $payload, $headers)->assertCreated();
        $this->postJson('/api/sales', $invalid, $headers)->assertConflict();
        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(2, $variant->fresh()->stock);
    }
    public function test_layaway_and_payment_retries_preserve_inventory_money_and_notification_counts(): void
    {
        [$variant] = $this->fixture();
        $this->getJson('/api/capabilities')->assertOk()->assertJsonPath('data.layaways_idempotency_v1', true);
        $payload = ['items' => [['variant_id' => $variant->id, 'qty' => 1]], 'anticipo_inicial' => 50, 'metodo_anticipo' => 'cash'];
        $headers = ['Idempotency-Key' => 'android:layaway-00000001'];
        $first = $this->postJson('/api/layaways', $payload, $headers)->assertCreated();
        $id = $first->json('data.id');
        $notifications = \App\Models\SystemNotification::count();
        $retry = $this->postJson('/api/layaways', $payload, $headers)->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($first->getContent(), $retry->getContent());
        $this->assertSame(2, $variant->fresh()->stock);
        $this->assertDatabaseCount('layaways', 1);
        $this->assertDatabaseCount('layaway_payments', 1);
        $this->assertSame($notifications, \App\Models\SystemNotification::count());
        $paymentHeaders = ['Idempotency-Key' => 'android:payment-00000001'];
        $paymentPayload = ['amount' => 200, 'method' => 'cash', 'auto_liquidate' => true];
        $firstPayment = $this->postJson("/api/layaways/{$id}/payments", $paymentPayload, $paymentHeaders)->assertCreated()->assertJsonPath('data.liquidated', true);
        $notifications = \App\Models\SystemNotification::count();
        $retryPayment = $this->postJson("/api/layaways/{$id}/payments", $paymentPayload, $paymentHeaders)->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($firstPayment->getContent(), $retryPayment->getContent());
        $this->assertDatabaseCount('layaway_payments', 2);
        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(2, $variant->fresh()->stock);
        $this->assertSame($notifications, \App\Models\SystemNotification::count());
    }

    public function test_android_layaway_totals_use_discounts_and_net_payments(): void
    {
        [$variant] = $this->fixture();
        $id = $this->postJson('/api/layaways', ['items' => [['variant_id' => $variant->id, 'qty' => 1]]])->assertCreated()->json('data.id');
        \App\Models\Layaway::findOrFail($id)->update(['manual_discount_total' => 20, 'loyalty_discount_total' => 5, 'paid_total' => 200, 'refunded_total' => 30]);
        $this->getJson("/api/layaways/{$id}")->assertOk()->assertJsonPath('data.total', 225)->assertJsonPath('data.paid_total', 170)->assertJsonPath('data.gross_paid_total', 200)->assertJsonPath('data.saldo_restante', 55);
        $this->getJson('/api/layaways')->assertOk()->assertJsonPath('data.0.total', 225)->assertJsonPath('data.0.paid_total', 170);
    }

}
