<?php

namespace App\Jobs;

use App\Mail\LayawayEventNotificationMail;
use App\Models\Layaway;
use App\Models\NotificationEmail;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendLayawayNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public int $uniqueFor = 86400;

    public function __construct(
        public readonly int $layawayId,
        public readonly int $actorId,
        public readonly int $recipientId,
        public readonly ?int $paymentId = null,
    ) {}

    public function uniqueId(): string
    {
        $event = $this->paymentId === null ? 'created' : "payment:{$this->paymentId}";
        return "layaway-notification:{$this->layawayId}:{$event}:{$this->recipientId}";
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $setting = $this->paymentId === null ? 'notifications.layaway_enabled' : 'notifications.layaway_payment_enabled';
        if (! (bool) Setting::get($setting, true)) {
            return;
        }
        $recipient = NotificationEmail::query()->whereKey($this->recipientId)->where('active', true)->first();
        if (! $recipient) {
            return;
        }
        $layaway = Layaway::query()->findOrFail($this->layawayId);
        $actor = User::query()->findOrFail($this->actorId);
        $payment = $this->paymentId === null ? null : $layaway->payments()->findOrFail($this->paymentId);
        // SMTP only runs in the worker. An ambiguous SMTP acknowledgement can
        // cause a duplicate on retry; a pending-job lock is not exactly-once delivery.
        Mail::to($recipient->email)->send(new LayawayEventNotificationMail($layaway, $actor, $payment));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Layaway notification exhausted retries; SMTP delivery may be uncertain.', [
            'layaway_id' => $this->layawayId, 'payment_id' => $this->paymentId, 'recipient_id' => $this->recipientId,
        ]);
    }
}
