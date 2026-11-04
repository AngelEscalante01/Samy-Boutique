<?php

namespace Tests\Feature;

use App\Models\Layaway;
use App\Models\NotificationPreference;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\LayawayExpiryScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LayawayExpiryCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->manager = User::factory()->create(['active' => true]);
        $this->manager->assignRole('gerente');
        Mail::fake();
    }

    private function layaway(string $status, ?string $date): Layaway
    {
        return Layaway::query()->create([
            'created_by' => $this->manager->id,
            'status' => $status,
            'subtotal' => '100.00',
            'paid_total' => '25.00',
            'fecha_vencimiento' => $date,
        ]);
    }

    public function test_canonical_date_schema_and_api_preserve_dates_for_all_statuses(): void
    {
        $this->assertTrue(Schema::hasColumn('layaways', 'fecha_vencimiento'));
        $this->assertFalse(Schema::hasColumn('layaways', 'expires_at'));

        $open = $this->layaway('open', '2026-10-15');
        $withoutDate = $this->layaway('open', null);
        $liquidated = $this->layaway('liquidated', '2026-10-12');
        $cancelled = $this->layaway('cancelled', '2026-10-11');

        $this->withHeader('Authorization', 'Bearer '.$this->manager->createToken('layaway-date-test')->plainTextToken)
            ->withHeader('Accept', 'application/json');

        foreach ([$open, $withoutDate, $liquidated, $cancelled] as $row) {
            $this->getJson('/api/layaways/'.$row->id)
                ->assertOk()
                ->assertJsonPath('data.fecha_vencimiento', $row->fecha_vencimiento?->toDateString())
                ->assertJsonPath('data.saldo_restante', 75);
        }

        $dates = collect($this->getJson('/api/layaways?per_page=20')->assertOk()->json('data'))
            ->pluck('fecha_vencimiento', 'id');
        $this->assertSame('2026-10-15', $dates->get($open->id));
        $this->assertNull($dates->get($withoutDate->id));
        $this->assertSame('2026-10-12', $dates->get($liquidated->id));
        $this->assertSame('2026-10-11', $dates->get($cancelled->id));
    }

    public function test_dry_run_is_read_only_and_live_scan_excludes_historical_rows(): void
    {
        config(['firebase.layaway_timezone' => 'America/Mexico_City']);
        $today = now('America/Mexico_City')->toDateString();
        $yesterday = now('America/Mexico_City')->subDay()->toDateString();
        $tomorrow = now('America/Mexico_City')->addDay()->toDateString();

        $historical = $this->layaway('open', $yesterday);
        $cut = $historical->id;
        $due = $this->layaway('open', $tomorrow);
        $expired = $this->layaway('open', $yesterday);
        $this->layaway('open', null);
        $this->layaway('liquidated', $today);
        $this->layaway('cancelled', $today);

        config(['firebase.layaway_scan_min_id' => $cut, 'firebase.layaway_scan_enabled' => false]);
        $scanner = app(LayawayExpiryScanner::class);
        $all = $scanner->run(true, true);
        $this->assertSame(1, $all['due']);
        $this->assertSame(2, $all['expired']);
        $this->assertSame(0, $all['published']);
        $this->assertDatabaseCount('system_notifications', 0);

        $this->assertSame(0, $scanner->run()['published']);
        config(['firebase.layaway_scan_enabled' => true]);
        $first = $scanner->run();
        $this->assertSame(1, $first['due']);
        $this->assertSame(1, $first['expired']);
        $this->assertSame(2, $first['published']);
        $this->assertDatabaseMissing('system_notifications', ['event_key' => "layaway_expired:{$historical->id}:{$yesterday}"]);
        $this->assertDatabaseHas('system_notifications', ['event_key' => "layaway_due:{$due->id}:{$tomorrow}"]);
        $this->assertDatabaseHas('system_notifications', ['event_key' => "layaway_expired:{$expired->id}:{$yesterday}"]);
        $this->assertSame(0, $scanner->run()['published']);
        $this->assertDatabaseCount('system_notifications', 2);
        $this->assertDatabaseCount('notification_dispatches', 2);
        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('jobs')->where('queue', 'sale-notifications')->count());
    }

    public function test_layaway_notification_preference_suppresses_recipient(): void
    {
        $row = $this->layaway('open', now('America/Mexico_City')->toDateString());
        NotificationPreference::query()->create([
            'user_id' => $this->manager->id,
            'preferences' => ['layaway_due' => false],
        ]);
        config(['firebase.layaway_scan_min_id' => 0, 'firebase.layaway_scan_enabled' => true]);

        app(LayawayExpiryScanner::class)->run();

        $this->assertDatabaseMissing('system_notifications', ['user_id' => $this->manager->id, 'resource_id' => (string) $row->id]);
        $this->assertSame(0, SystemNotification::query()->count());
    }

    public function test_productive_vigencia_edit_and_print_routes_remain_available(): void
    {
        $layaway = $this->layaway('open', '2026-10-09');
        $layaway->forceFill(['vigencia_dias' => 7])->save();
        $this->actingAs($this->manager);

        $this->get('/layaways?vigencia=expired')->assertOk();
        $this->patchJson(route('layaways.vigencia.update', $layaway->id), ['vigencia_dias' => 30])
            ->assertOk()
            ->assertJsonPath('vigencia_dias', 30);
        $this->assertSame(30, $layaway->fresh()->vigencia_dias);
        $this->getJson(route('layaways.print.created', $layaway->id))
            ->assertOk()
            ->assertJsonPath('type', 'layaway_created');
    }
}
