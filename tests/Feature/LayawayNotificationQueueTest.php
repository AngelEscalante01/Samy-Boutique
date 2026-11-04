<?php

namespace Tests\Feature;

use App\Jobs\SendLayawayNotification;
use App\Mail\LayawayEventNotificationMail;
use App\Models\Layaway;
use App\Models\NotificationEmail;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LayawayNotificationQueueTest extends TestCase
{
    use DatabaseMigrations;

    private User $actor;
    private NotificationEmail $recipient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create();
        $this->recipient = NotificationEmail::create(['email' => 'owner@example.test', 'active' => true]);
        Mail::fake();
    }

    private function layaway(): Layaway
    {
        return Layaway::create(['created_by' => $this->actor->id, 'subtotal' => 100, 'paid_total' => 0]);
    }

    public function test_both_events_wait_for_outer_commit_and_never_send_mail_in_request(): void
    {
        DB::beginTransaction();
        $layaway = $this->layaway();
        $payment = $layaway->payments()->create(['method' => 'cash', 'amount' => 10]);
        DB::transaction(function () use ($layaway, $payment): void {
            app(NotificationService::class)->notifyLayawayCreated($layaway, $this->actor);
            app(NotificationService::class)->notifyLayawayPayment($layaway, $payment, $this->actor);
        });
        $this->assertDatabaseCount('jobs', 0);
        Mail::assertNothingSent();
        DB::commit();
        $this->assertDatabaseCount('jobs', 2);
        $this->assertSame(2, DB::table('jobs')->where('queue', 'sale-notifications')->count());
        Mail::assertNothingSent();
    }

    public function test_outer_rollback_discards_both_notifications(): void
    {
        DB::beginTransaction();
        $layaway = $this->layaway();
        $payment = $layaway->payments()->create(['method' => 'cash', 'amount' => 10]);
        app(NotificationService::class)->notifyLayawayCreated($layaway, $this->actor);
        app(NotificationService::class)->notifyLayawayPayment($layaway, $payment, $this->actor);
        DB::rollBack();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseMissing('layaways', ['id' => $layaway->id]);
        Mail::assertNothingSent();
    }

    public function test_dispatch_failure_does_not_revert_committed_layaway_or_payment(): void
    {
        config(['queue.connections.sale_notifications.driver' => 'unavailable']);
        DB::transaction(function (): void {
            $layaway = $this->layaway();
            $payment = $layaway->payments()->create(['method' => 'cash', 'amount' => 10]);
            app(NotificationService::class)->notifyLayawayCreated($layaway, $this->actor);
            app(NotificationService::class)->notifyLayawayPayment($layaway, $payment, $this->actor);
        });
        $this->assertDatabaseCount('layaways', 1);
        $this->assertDatabaseCount('layaway_payments', 1);
        $this->assertDatabaseCount('jobs', 0);
        Mail::assertNothingSent();
    }

    public function test_worker_sends_both_renderable_events_without_payment_creator_relation(): void
    {
        $layaway = $this->layaway();
        $payment = $layaway->payments()->create(['method' => 'cash', 'amount' => 10]);
        app(NotificationService::class)->notifyLayawayCreated($layaway, $this->actor);
        app(NotificationService::class)->notifyLayawayPayment($layaway, $payment, $this->actor);
        Mail::assertNothingSent();
        (new SendLayawayNotification($layaway->id, $this->actor->id, $this->recipient->id))->handle();
        (new SendLayawayNotification($layaway->id, $this->actor->id, $this->recipient->id, $payment->id))->handle();
        Mail::assertSent(LayawayEventNotificationMail::class, 2);
        Mail::assertSent(LayawayEventNotificationMail::class, function ($mail): bool {
            return $mail->payment !== null && str_contains($mail->render(), 'Abono registrado');
        });
        Mail::assertSent(LayawayEventNotificationMail::class, function ($mail): bool {
            return $mail->payment === null && str_contains($mail->render(), 'Apartado registrado');
        });
    }

    public function test_same_event_is_deduplicated_while_pending_but_distinct_payments_are_not(): void
    {
        $layaway = $this->layaway();
        $service = app(NotificationService::class);
        $service->notifyLayawayCreated($layaway, $this->actor);
        $service->notifyLayawayCreated($layaway, $this->actor);
        foreach ([10, 20] as $amount) {
            $payment = $layaway->payments()->create(['method' => 'cash', 'amount' => $amount]);
            $service->notifyLayawayPayment($layaway, $payment, $this->actor);
            $service->notifyLayawayPayment($layaway, $payment, $this->actor);
        }
        $this->assertDatabaseCount('jobs', 3);
    }

    public function test_worker_respects_disabled_recipient_and_setting(): void
    {
        $layaway = $this->layaway();
        $job = new SendLayawayNotification($layaway->id, $this->actor->id, $this->recipient->id);
        $this->recipient->update(['active' => false]);
        $job->handle();
        $this->recipient->update(['active' => true]);
        Setting::set('notifications.layaway_enabled', false);
        $job->handle();
        Mail::assertNothingSent();
    }

    public function test_smtp_failure_reaches_worker_without_reverting_business_data(): void
    {
        $layaway = $this->layaway();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Simulated SMTP timeout'));
        try {
            (new SendLayawayNotification($layaway->id, $this->actor->id, $this->recipient->id))->handle();
            $this->fail('Worker must receive SMTP failure for its bounded retries.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated SMTP timeout', $exception->getMessage());
        }
        $this->assertDatabaseHas('layaways', ['id' => $layaway->id]);
    }
}
