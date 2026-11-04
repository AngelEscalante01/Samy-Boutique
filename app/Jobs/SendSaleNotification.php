<?php

namespace App\Jobs;

use App\Mail\SaleNotificationMail;
use App\Models\NotificationEmail;
use App\Models\Sale;
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

class SendSaleNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 86400;

    public function __construct(
        public readonly int $saleId,
        public readonly int $actorId,
        public readonly int $recipientId,
    ) {}

    public function uniqueId(): string
    {
        return "sale-notification:{$this->saleId}:{$this->recipientId}";
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $recipient = NotificationEmail::query()
            ->whereKey($this->recipientId)
            ->where('active', true)
            ->first();

        if (! $recipient) {
            return;
        }

        $sale = Sale::query()
            ->with(['customer', 'creator', 'items.variant.size', 'items.variant.color', 'payments'])
            ->findOrFail($this->saleId);
        $actor = User::query()->findOrFail($this->actorId);

        // SMTP runs only in the queue worker. Throwing lets Laravel retry this
        // recipient independently without resending to other recipients.
        Mail::to($recipient->email)->send(new SaleNotificationMail($sale, $actor));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Sale notification failed after its retry limit.', [
            'sale_id' => $this->saleId,
            'recipient_id' => $this->recipientId,
        ]);
    }
}
