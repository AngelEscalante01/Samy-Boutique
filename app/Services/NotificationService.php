<?php

namespace App\Services;

use App\Jobs\SendSaleNotification;
use App\Jobs\SendLayawayNotification;
use App\Models\Layaway;
use App\Models\LayawayPayment;
use App\Models\NotificationEmail;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    public function notifySale(Sale $sale, User $actor): void
    {
        $saleId = (int) $sale->getKey();
        $actorId = (int) $actor->getKey();

        try {
            DB::afterCommit(function () use ($saleId, $actorId): void {
                try {
                    if (! (bool) Setting::get('notifications.sale_enabled', true)) {
                        return;
                    }

                    $recipientIds = NotificationEmail::query()
                        ->where('active', true)
                        ->pluck('id');

                    foreach ($recipientIds as $recipientId) {
                        try {
                            $delaySeconds = max(0, (int) config(
                                'queue.connections.sale_notifications.delay_seconds',
                                60,
                            ));
                            $job = (new SendSaleNotification($saleId, $actorId, (int) $recipientId))
                                ->onConnection('sale_notifications')
                                ->onQueue('sale-notifications');

                            if ($delaySeconds > 0) {
                                $job->delay(now()->addSeconds($delaySeconds));
                            }

                            $pendingDispatch = dispatch($job);
                            unset($pendingDispatch);
                        } catch (Throwable $exception) {
                            $this->logDispatchFailure($saleId, (int) $recipientId);
                        }
                    }
                } catch (Throwable $exception) {
                    $this->logDispatchFailure($saleId);
                }
            });
        } catch (Throwable $exception) {
            $this->logDispatchFailure($saleId);
        }
    }

    private function logDispatchFailure(int $saleId, ?int $recipientId = null): void
    {
        Log::error('Sale notification could not be queued; sale remains committed.', array_filter([
            'sale_id' => $saleId,
            'recipient_id' => $recipientId,
        ], static fn ($value) => $value !== null));
    }

    public function notifyLayawayCreated(Layaway $layaway, User $actor): void
    {
        $this->queueLayawayNotification((int) $layaway->id, (int) $actor->id);
    }

    public function notifyLayawayPayment(Layaway $layaway, LayawayPayment $payment, User $actor): void
    {
        $this->queueLayawayNotification((int) $layaway->id, (int) $actor->id, (int) $payment->id);
    }

    private function queueLayawayNotification(int $layawayId, int $actorId, ?int $paymentId = null): void
    {
        try {
            DB::afterCommit(function () use ($layawayId, $actorId, $paymentId): void {
                try {
                    $setting = $paymentId === null ? 'notifications.layaway_enabled' : 'notifications.layaway_payment_enabled';
                    if (! (bool) Setting::get($setting, true)) {
                        return;
                    }
                    foreach (NotificationEmail::query()->where('active', true)->pluck('id') as $recipientId) {
                        try {
                            $job = (new SendLayawayNotification($layawayId, $actorId, (int) $recipientId, $paymentId))
                                ->onConnection('sale_notifications')->onQueue('sale-notifications');
                            $pendingDispatch = dispatch($job);
                            unset($pendingDispatch);
                        } catch (Throwable $exception) {
                            $this->logLayawayDispatchFailure($layawayId, $paymentId, (int) $recipientId);
                        }
                    }
                } catch (Throwable $exception) {
                    $this->logLayawayDispatchFailure($layawayId, $paymentId);
                }
            });
        } catch (Throwable $exception) {
            $this->logLayawayDispatchFailure($layawayId, $paymentId);
        }
    }

    private function logLayawayDispatchFailure(int $layawayId, ?int $paymentId, ?int $recipientId = null): void
    {
        Log::error('Layaway notification could not be queued; business operation remains committed.', [
            'layaway_id' => $layawayId, 'payment_id' => $paymentId, 'recipient_id' => $recipientId,
        ]);
    }
}
