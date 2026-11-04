<?php

namespace Tests\Feature;

use App\Models\Layaway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_and_recent_layaways_match_discounted_net_balance_without_offsetting_excess(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create(['active' => true]);
        $manager->assignRole('gerente');
        $discounted = Layaway::create(['created_by' => $manager->id, 'status' => 'open', 'subtotal' => 550, 'manual_discount_total' => 55, 'paid_total' => 400]);
        $refunded = Layaway::create(['created_by' => $manager->id, 'status' => 'open', 'subtotal' => 1000, 'manual_discount_total' => 100, 'loyalty_discount_total' => 50, 'paid_total' => 500, 'refunded_total' => 100]);
        Layaway::create(['created_by' => $manager->id, 'status' => 'open', 'subtotal' => 100, 'paid_total' => 300]);
        Layaway::create(['created_by' => $manager->id, 'status' => 'cancelled', 'subtotal' => 900, 'paid_total' => 0]);
        $this->actingAs($manager)->getJson(route('dashboard.data.summary'))->assertOk()
            ->assertJsonPath('layaways_active_count', 3)->assertJsonPath('layaways_pending_total', 545);
        $recent = $this->getJson(route('dashboard.data.recent-layaways'))->assertOk()->json();
        $byId = collect($recent)->keyBy('id');
        $this->assertEquals(95, $byId[$discounted->id]['balance']);
        $this->assertEquals(495, $byId[$discounted->id]['total']);
        $this->assertEquals(450, $byId[$refunded->id]['balance']);
        $this->assertEquals(400, $byId[$refunded->id]['paid']);
        [$title, $body] = app(\App\Services\NotificationMessageFormatter::class)->layaway($refunded);
        $this->assertStringContainsString('$850.00', $title);
        $this->assertStringContainsString('Abonado: $400.00; saldo: $450.00', $body);
        $this->assertDatabaseCount('layaways', 4);
    }
}
