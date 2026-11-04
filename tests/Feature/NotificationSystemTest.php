<?php

namespace Tests\Feature;

use App\Models\NotificationDelivery;
use App\Models\NotificationDevice;
use App\Models\NotificationDispatch;
use App\Models\NotificationPreference;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\NotificationEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->manager = User::factory()->create(['active' => true]);
        $this->manager->assignRole('gerente');
    }

    public function test_root_service_worker_route_loads_the_workbox_bundle_with_root_scope(): void
    {
        $this->get('/sw.js')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
            ->assertHeader('Service-Worker-Allowed', '/')
            ->assertSee("importScripts('/build/sw.js?v=", false)
            ->assertSee('SAMY_FCM_PONG', false);
    }

    private function api(User $user): static
    {
        $this->flushHeaders();
        app('auth')->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('notifications-test')->plainTextToken)
            ->withHeader('Accept', 'application/json');
    }

    public function test_inbox_is_private_and_read_state_is_per_user(): void
    {
        $other = User::factory()->create(['active' => true]);
        $first = SystemNotification::query()->create([
            'user_id' => $this->manager->id,
            'category' => 'sale', 'title' => 'Venta nueva', 'message' => 'Aviso privado',
            'event_key' => 'sale:private-test',
        ]);

        $this->api($other)->getJson('/api/notifications/'.$first->id)->assertNotFound();
        $this->api($this->manager)->getJson('/api/notifications')->assertOk()->assertJsonPath('data.0.title', 'Venta nueva');
        $this->api($this->manager)->patchJson('/api/notifications/'.$first->id.'/read')->assertOk();
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertSame(0, $this->api($this->manager)->getJson('/api/notifications/unread-count')->json('data.unread_count'));
    }

    public function test_push_device_is_encrypted_and_logout_cancels_pending_delivery(): void
    {
        $id = (string) \Illuminate\Support\Str::uuid();
        $this->api($this->manager)->postJson('/api/notification-devices', [
            'installation_id' => $id, 'platform' => 'android', 'name' => 'Tablet',
            'token' => 'secret-fcm-token', 'permission_status' => 'granted',
        ])->assertOk()->assertJsonMissing(['token' => 'secret-fcm-token']);

        $device = NotificationDevice::query()->firstOrFail();
        $this->assertSame('secret-fcm-token', $device->token);
        $this->assertStringNotContainsString('secret-fcm-token', $device->getRawOriginal('token'));

        $notification = SystemNotification::query()->create([
            'user_id' => $this->manager->id, 'category' => 'sale', 'title' => 'Venta',
            'message' => 'Nuevo aviso', 'event_key' => 'test:pending-device',
        ]);
        $delivery = NotificationDelivery::query()->create([
            'notification_id' => $notification->id, 'device_id' => $device->id,
            'user_id' => $this->manager->id, 'token_snapshot' => 'secret-fcm-token', 'status' => 'pending',
        ]);

        $this->api($this->manager)->postJson('/api/logout', ['installation_id' => $id])->assertOk();
        $this->assertNull($device->fresh()->user_id);
        $this->assertNull($device->fresh()->token);
        $this->assertSame('cancelled', $delivery->fresh()->status);
    }

    public function test_events_are_deduplicated_and_written_to_durable_dispatch_outbox(): void
    {
        $events = app(NotificationEventService::class);
        $events->forManagers('sale', 'Venta nueva', 'Se registró una venta.', 'sale', 123, '/sales/123', 'normal', 'sale:123');
        $events->forManagers('sale', 'Venta nueva', 'Se registró una venta.', 'sale', 123, '/sales/123', 'normal', 'sale:123');

        $notification = SystemNotification::query()->where('event_key', 'sale:123')->firstOrFail();
        $this->assertSame(1, SystemNotification::query()->where('event_key', 'sale:123')->count());
        $this->assertSame(1, NotificationDispatch::query()->where('notification_id', $notification->id)->count());
    }

    public function test_preferences_can_disable_category_and_push_independently(): void
    {
        NotificationPreference::query()->create([
            'user_id' => $this->manager->id,
            'preferences' => ['sale' => false, 'push_enabled' => true],
        ]);
        $this->api($this->manager)->putJson('/api/notification-preferences', [
            'preferences' => ['sale' => true, 'push_enabled' => false],
        ])->assertOk()->assertJsonPath('data.preferences.sale', true)->assertJsonPath('data.preferences.push_enabled', false);

        app(NotificationEventService::class)->forManagers('sale', 'Venta', 'Aviso', 'sale', 9, '/sales/9', 'normal', 'sale:pref-test');
        $this->assertDatabaseHas('system_notifications', ['user_id' => $this->manager->id, 'event_key' => 'sale:pref-test']);
        $notification = SystemNotification::query()->where('event_key', 'sale:pref-test')->firstOrFail();
        $this->assertDatabaseMissing('notification_dispatches', ['notification_id' => $notification->id]);
    }

    public function test_only_authorized_managers_can_send_manual_announcements(): void
    {
        $cashier = User::factory()->create(['active' => true]);
        $cashier->assignRole('cajero');
        $body = [
            'title' => 'Aviso', 'message' => 'Mensaje de prueba', 'roles' => ['cajero'],
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(), 'confirm' => true,
        ];

        $this->api($cashier)->postJson('/api/notifications/manual', $body)->assertForbidden();
        $this->api($this->manager)->postJson('/api/notifications/manual', $body)->assertOk();
        $this->assertDatabaseHas('system_notifications', ['user_id' => $cashier->id, 'category' => 'administrative']);
    }
}
