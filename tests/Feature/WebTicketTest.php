<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Layaway;
use App\Models\LayawayPayment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_ticket_is_private_escaped_and_does_not_create_another_sale(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $cashier = User::factory()->create(['active' => true]);
        $cashier->assignRole('cajero');
        $customer = Customer::create(['name' => '<script>alert("cliente")</script>']);
        $sale = Sale::create(['created_by' => $cashier->id, 'customer_id' => $customer->id, 'status' => 'completed', 'subtotal' => 1100, 'total' => 990, 'cash_received' => 1000]);
        $sale->forceFill(['created_at' => '2026-10-29 12:30:00'])->save();
        $sale->payments()->create(['method' => 'cash', 'amount' => 990]);
        $this->get(route('sales.ticket', $sale))->assertRedirect('/login');
        $this->actingAs($cashier)->get(route('sales.ticket', $sale))->assertOk()
            ->assertSee('VE-00001')->assertSee('Imprimir o guardar PDF')->assertSee('29/10/2026 06:30')
            ->assertSee('$990.00')->assertSee('$10.00')
            ->assertDontSee('<script>alert("cliente")</script>', false)
            ->assertSee('&lt;script&gt;alert', false);
        $sale->update(['status' => 'cancelled']);
        $this->get(route('sales.ticket', $sale))->assertOk()->assertSee('CANCELADO');
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_layaway_tickets_use_discounted_total_and_net_payments_after_refunds(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $layaway = Layaway::create(['created_by' => $manager->id, 'status' => 'open', 'subtotal' => 1000, 'manual_discount_total' => 100, 'paid_total' => 500, 'refunded_total' => 100]);
        $payment = LayawayPayment::create(['layaway_id' => $layaway->id, 'created_by' => $manager->id, 'amount' => 500, 'method' => 'cash', 'payer_name' => 'Pagador original']);
        $this->actingAs($manager)->get(route('layaways.ticket', $layaway))->assertOk()
            ->assertSee('$900.00')->assertSee('$400.00')->assertSee('$500.00')->assertSee('Pagado neto');
        $this->get(route('layaways.payments.ticket', [$layaway, $payment]))->assertOk()
            ->assertSee('Estado actual del apartado')->assertSee('Pagador original')->assertSee('$400.00');
        $this->get(route('layaways.ticket', ['layaway' => $layaway, 'kind' => 'closed']))->assertStatus(409);

        $other = Layaway::create(['created_by' => $manager->id, 'status' => 'open', 'subtotal' => 200, 'paid_total' => 0]);
        $this->get(route('layaways.payments.ticket', [$other, $payment]))->assertNotFound();
        $layaway->update(['status' => 'cancelled']);
        $this->get(route('layaways.ticket', $layaway))->assertOk()->assertSee('CANCELADO')->assertSee('Excedente por devolver');
        $this->assertDatabaseCount('layaway_payments', 1);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_pos_permission_alone_does_not_expose_sales_or_layaway_receipts(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['active' => true]);
        $user->givePermissionTo('pos.view');
        $sale = Sale::create(['created_by' => $user->id, 'status' => 'completed', 'subtotal' => 100, 'total' => 100]);
        $layaway = Layaway::create(['created_by' => $user->id, 'status' => 'open', 'subtotal' => 100, 'paid_total' => 0]);
        $this->actingAs($user)->get(route('sales.ticket', $sale))->assertForbidden();
        $this->get(route('layaways.ticket', $layaway))->assertForbidden();
        $this->get(route('layaways.print-data', $layaway))->assertForbidden();
    }
}
