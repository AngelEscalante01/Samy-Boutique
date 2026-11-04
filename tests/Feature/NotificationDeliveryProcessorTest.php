<?php

namespace Tests\Feature;

use App\Models\NotificationDelivery;
use App\Models\NotificationDevice;
use App\Models\NotificationDispatch;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\FirebaseMessagingService;
use App\Services\NotificationDeliveryProcessor;
use App\Services\NotificationEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class NotificationDeliveryProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('firebase.push_enabled', true);
    }

    private function recipient(): array
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['active' => true]);
        $user->assignRole('gerente');
        $device = NotificationDevice::query()->create([
            'user_id' => $user->id,
            'installation_id' => (string) Str::uuid(),
            'platform' => 'android',
            'token' => 'simulated-token',
            'token_hash' => hash('sha256', 'simulated-token'),
            'permission_status' => 'granted',
            'active' => true,
        ]);

        return [$user, $device];
    }

    public function test_outbox_is_materialized_and_accepted_once_without_real_fcm(): void
    {
        [$user, $device] = $this->recipient();
        app(NotificationEventService::class)->forManagers(
            'sale', 'Venta registrada', 'Venta de prueba', 'sale', 42, '/sales/42', 'normal', 'sale:test-42',
        );
        $notification = SystemNotification::query()->where('event_key', 'sale:test-42')->firstOrFail();
        $this->assertSame($user->id, $notification->user_id);
        $this->assertDatabaseHas('notification_dispatches', ['notification_id' => $notification->id, 'status' => 'pending']);
        $this->assertDatabaseCount('notification_deliveries', 0);

        $firebase = Mockery::mock(FirebaseMessagingService::class);
        $firebase->shouldReceive('send')->once()->with('simulated-token', $notification->id, 'sale', 'Venta registrada', 'Venta de prueba')
            ->andReturn(['accepted' => true, 'permanent' => false, 'code' => null, 'message_id' => 'simulated/1']);
        $processor = new NotificationDeliveryProcessor($firebase);
        $this->assertSame(1, $processor->processBatch());
        $this->assertSame(0, $processor->processBatch());

        $this->assertDatabaseHas('notification_dispatches', ['notification_id' => $notification->id, 'status' => 'processed']);
        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id, 'device_id' => $device->id,
            'status' => 'sent', 'attempt_count' => 1, 'fcm_message_id' => 'simulated/1',
        ]);
        $this->assertDatabaseCount('notification_delivery_attempts', 1);
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_temporary_failure_retries_when_due_and_invalid_token_is_disabled(): void
    {
        [, $device] = $this->recipient();
        $notification = SystemNotification::query()->create([
            'user_id' => $device->user_id, 'category' => 'sale', 'title' => 'Venta',
            'message' => 'Aviso', 'event_key' => 'sale:retry',
        ]);
        NotificationDispatch::query()->create(['notification_id' => $notification->id, 'status' => 'pending']);
        $firebase = Mockery::mock(FirebaseMessagingService::class);
        $firebase->shouldReceive('send')->twice()
            ->andReturn(
                ['accepted' => false, 'permanent' => false, 'code' => 'UNAVAILABLE', 'message_id' => null],
                ['accepted' => false, 'permanent' => true, 'code' => 'UNREGISTERED', 'message_id' => null],
            );
        $processor = new NotificationDeliveryProcessor($firebase);

        $processor->processBatch();
        $delivery = NotificationDelivery::query()->firstOrFail();
        $this->assertSame('error_temporal', $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);
        $this->assertSame(0, $processor->processBatch());
        $this->travel(61)->seconds();
        $processor->processBatch();
        $this->assertSame('error_definitivo', $delivery->fresh()->status);
        $this->assertSame(2, $delivery->fresh()->attempt_count);
        $this->assertDatabaseCount('notification_delivery_attempts', 2);
        $this->assertNull($device->fresh()->token);
        $this->assertSame('invalid', $device->fresh()->permission_status);
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_active_claim_is_not_reclaimed_and_rotated_token_is_not_sent(): void
    {
        [, $device] = $this->recipient();
        $notification = SystemNotification::query()->create([
            'user_id' => $device->user_id, 'category' => 'sale', 'title' => 'Venta',
            'message' => 'Aviso', 'event_key' => 'sale:claim',
        ]);
        $delivery = NotificationDelivery::query()->create([
            'notification_id' => $notification->id, 'device_id' => $device->id,
            'user_id' => $device->user_id, 'token_snapshot' => 'simulated-token', 'status' => 'sending',
        ]);
        $firebase = Mockery::mock(FirebaseMessagingService::class);
        $firebase->shouldNotReceive('send');
        $processor = new NotificationDeliveryProcessor($firebase);

        $processor->processBatch();
        $this->assertSame('sending', $delivery->fresh()->status);
        $this->travel(6)->minutes();
        $device->update(['token' => 'rotated-token', 'token_hash' => hash('sha256', 'rotated-token')]);
        $processor->processBatch();
        $this->assertSame('cancelled', $delivery->fresh()->status);
        $this->assertSame('token_rotated', $delivery->fresh()->last_error_code);
    }

    public function test_disabled_transport_preserves_pending_outbox_without_fcm(): void
    {
        [$user] = $this->recipient();
        app(NotificationEventService::class)->forManagers(
            'sale', 'Venta', 'Aviso', 'sale', 43, '/sales/43', 'normal', 'sale:disabled',
        );
        config()->set('firebase.push_enabled', false);
        $firebase = Mockery::mock(FirebaseMessagingService::class);
        $firebase->shouldNotReceive('send');

        $this->assertSame(0, (new NotificationDeliveryProcessor($firebase))->processBatch());
        $notification = SystemNotification::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertDatabaseHas('notification_dispatches', ['notification_id' => $notification->id, 'status' => 'pending']);
        $this->assertDatabaseCount('notification_deliveries', 0);
    }

    public function test_scheduled_recovery_retries_a_due_delivery_without_another_event(): void
    {
        [$user] = $this->recipient();
        app(NotificationEventService::class)->forManagers(
            'sale', 'QA aviso sintético', 'QA recuperación', 'sale', 44, '/sales/44', 'normal', 'sale:scheduled-retry',
        );
        $firebase = Mockery::mock(FirebaseMessagingService::class);
        $firebase->shouldReceive('send')->twice()->andReturn(
            ['accepted' => false, 'permanent' => false, 'code' => 'UNAVAILABLE', 'message_id' => null],
            ['accepted' => true, 'permanent' => false, 'code' => null, 'message_id' => 'simulated/recovered'],
        );
        $this->app->instance(FirebaseMessagingService::class, $firebase);
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($event) => $event->description === 'notifications:recover-push');
        $this->assertNotNull($event);
        config(['firebase.push_enabled' => false]);
        $this->assertFalse($event->filtersPass($this->app));
        $this->assertDatabaseCount('notification_deliveries', 0);
        config(['firebase.push_enabled' => true]);
        $this->assertTrue($event->filtersPass($this->app));
        $event->run($this->app);
        $delivery = NotificationDelivery::query()->firstOrFail();
        $this->assertSame('error_temporal', $delivery->status);
        $event->run($this->app); // Not due: must not make another provider call.
        $this->assertSame(1, $delivery->fresh()->attempt_count);
        $this->travel(61)->seconds();
        $event->run($this->app);
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame(2, $delivery->fresh()->attempt_count);
        $this->assertDatabaseCount('system_notifications', 1);
        $this->assertDatabaseCount('notification_deliveries', 1);
    }
}
