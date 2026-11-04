<?php

namespace App\Services;

use App\Models\NotificationDelivery;
use App\Models\NotificationDevice;
use App\Models\NotificationDispatch;
use Illuminate\Support\Facades\DB;

class NotificationDeliveryProcessor
{
    public function __construct(private readonly FirebaseMessagingService $firebase) {}

    public function processBatch(?int $limit = null): int
    {
        if (! config('firebase.push_enabled', false)) {
            return 0;
        }

        $limit = min(max($limit ?? (int) config('firebase.delivery_batch_size', 50), 1), 200);
        $this->materializeDispatches($limit);
        $ids = NotificationDelivery::query()
            ->where(function ($query): void {
                $query->where(function ($pending): void {
                    $pending->whereIn('status', ['pending', 'error_temporal'])
                        ->where(fn ($due) => $due->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()));
                })->orWhere(fn ($stale) => $stale->where('status', 'sending')->where('updated_at', '<', now()->subMinutes(5)));
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            $this->processOne((int) $id);
        }

        return $ids->count();
    }

    private function materializeDispatches(int $limit): void
    {
        $ids = NotificationDispatch::query()->where('status', 'pending')->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id): void {
                $dispatch = NotificationDispatch::query()->lockForUpdate()->find($id);
                if (! $dispatch || $dispatch->status !== 'pending') {
                    return;
                }

                $notification = $dispatch->notification()->first();
                if (! $notification) {
                    $dispatch->update(['status' => 'processed', 'processed_at' => now()]);

                    return;
                }

                $devices = NotificationDevice::query()
                    ->where('user_id', $notification->user_id)
                    ->where('active', true)
                    ->where('permission_status', 'granted')
                    ->whereNotNull('token')
                    ->get(['id', 'token']);

                foreach ($devices as $device) {
                    if ($device->token) {
                        NotificationDelivery::query()->firstOrCreate(
                            ['notification_id' => $notification->id, 'device_id' => $device->id],
                            [
                                'user_id' => $notification->user_id,
                                'token_snapshot' => $device->token,
                                'status' => 'pending',
                            ],
                        );
                    }
                }

                $dispatch->update(['status' => 'processed', 'processed_at' => now()]);
            });
        }
    }

    private function processOne(int $id): void
    {
        $delivery = DB::transaction(function () use ($id): ?NotificationDelivery {
            $delivery = NotificationDelivery::query()->lockForUpdate()->find($id);
            if (! $delivery || ! in_array($delivery->status, ['pending', 'error_temporal', 'sending'], true)) {
                return null;
            }

            if ($delivery->status === 'error_temporal' && $delivery->next_attempt_at?->isFuture()) {
                return null;
            }

            // A second worker may have selected this row before the first one
            // claimed it. Only an abandoned send may be reclaimed.
            if ($delivery->status === 'sending' && $delivery->updated_at?->isAfter(now()->subMinutes(5))) {
                return null;
            }

            $delivery->update(['status' => 'sending', 'last_attempt_at' => now()]);

            return $delivery->fresh();
        });

        if (! $delivery) {
            return;
        }

        $device = $delivery->device_id ? NotificationDevice::query()->find($delivery->device_id) : null;
        if (! $device || ! $device->active || (int) $device->user_id !== (int) $delivery->user_id) {
            $this->finish($delivery, 'cancelled', 'device_unbound');

            return;
        }

        if ($device->permission_status !== 'granted' || ! $device->token) {
            $this->finish($delivery, 'cancelled', 'permission_or_token_missing');

            return;
        }

        if ($device->token !== $delivery->token_snapshot) {
            $this->finish($delivery, 'cancelled', 'token_rotated');

            return;
        }

        $token = $device->token;
        $notification = $delivery->notification;
        $result = $this->firebase->send(
            $token,
            (int) $delivery->notification_id,
            $notification?->category ?? 'general',
            $notification?->title ?? 'Samy Boutique',
            $notification?->message ?? 'Tienes un nuevo aviso.',
        );
        $attempt = (int) $delivery->attempt_count + 1;

        DB::transaction(function () use ($delivery, $device, $result, $attempt, $token): void {
            $delivery->refresh();
            $status = $result['accepted'] ? 'sent' : ($result['permanent'] ? 'error_definitivo' : 'error_temporal');
            $delayMinutes = [1, 5, 20, 60, 180][$attempt - 1] ?? null;
            if ($status === 'error_temporal' && $delayMinutes === null) {
                $status = 'error_definitivo';
            }

            $delivery->update([
                'status' => $status,
                'attempt_count' => $attempt,
                'last_attempt_at' => now(),
                'next_attempt_at' => $status === 'error_temporal' ? now()->addMinutes((int) $delayMinutes) : null,
                'fcm_message_id' => $result['message_id'],
                'last_error_code' => $result['code'],
            ]);

            $delivery->attempts()->create([
                'attempt_number' => $attempt,
                'status' => $status,
                'error_code' => $result['code'],
            ]);

            if ($result['code'] === 'UNREGISTERED' && $device->token_hash === hash('sha256', $token)) {
                $device->update([
                    'token' => null,
                    'token_hash' => null,
                    'permission_status' => 'invalid',
                    'token_updated_at' => now(),
                ]);
            }
        });
    }

    private function finish(NotificationDelivery $delivery, string $status, string $code): void
    {
        DB::transaction(function () use ($delivery, $status, $code): void {
            $delivery->update(['status' => $status, 'last_error_code' => $code, 'next_attempt_at' => null]);
        });
    }
}
