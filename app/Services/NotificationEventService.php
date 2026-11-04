<?php

namespace App\Services;

use App\Jobs\ProcessNotificationDeliveries;
use App\Models\NotificationAnnouncement;
use App\Models\NotificationDispatch;
use App\Models\NotificationInventoryState;
use App\Models\NotificationPreference;
use App\Models\ProductVariant;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class NotificationEventService
{
    public function forManagers(
        string $category,
        string $title,
        string $message,
        ?string $resourceType = null,
        int|string|null $resourceId = null,
        ?string $url = null,
        string $priority = 'normal',
        ?string $eventKey = null,
        ?int $senderId = null,
    ): Collection {
        $users = User::query()
            ->where('active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['gerente', 'administrador']))
            ->get(['id']);

        return $this->publishToUsers(
            $users->pluck('id'), $category, $title, $message, $resourceType,
            $resourceId, $url, $priority, $eventKey, $senderId,
        );
    }

    public function publishToUsers(
        iterable $userIds,
        string $category,
        string $title,
        string $message,
        ?string $resourceType = null,
        int|string|null $resourceId = null,
        ?string $url = null,
        string $priority = 'normal',
        ?string $eventKey = null,
        ?int $senderId = null,
    ): Collection {
        $ids = collect($userIds)->map(fn ($id) => (int) $id)->unique()->values();
        $eventKey ??= (string) Str::uuid();
        if ($ids->isEmpty()) {
            return collect();
        }

        $preferences = NotificationPreference::query()
            ->whereIn('user_id', $ids)
            ->get(['user_id', 'preferences'])
            ->keyBy('user_id');

        $pushEnabled = [];

        $enabledIds = $ids->filter(function (int $id) use ($preferences, $category, $priority, &$pushEnabled): bool {
            $settings = $preferences->get($id)?->preferences ?? [];
            $pushEnabled[$id] = ($settings['push_enabled'] ?? true) === true;
            if (($settings[$category] ?? true) !== true) {
                return false;
            }

            return $category !== 'sale' || ($settings['all_sales'] ?? true) === true || $priority === 'high';
        })->values();

        if ($enabledIds->isEmpty()) {
            return collect();
        }

        $notifications = collect();
        DB::transaction(function () use (
            $enabledIds, $category, $title, $message, $resourceType, $resourceId,
            $url, $priority, $eventKey, $senderId, $notifications, $pushEnabled,
        ): void {
            foreach ($enabledIds as $userId) {
                $notification = SystemNotification::query()->firstOrCreate(
                    ['user_id' => $userId, 'event_key' => $eventKey],
                    [
                        'sender_user_id' => $senderId,
                        'category' => $category,
                        'title' => Str::limit(trim($title), 140, ''),
                        'message' => Str::limit(trim($message), 500, ''),
                        'priority' => $priority,
                        'resource_type' => $resourceType,
                        'resource_id' => $resourceId === null ? null : (string) $resourceId,
                        'url' => $this->safeInternalUrl($url),
                    ],
                );

                if (! $notification->wasRecentlyCreated) {
                    continue;
                }

                $notifications->push($notification);
                if ($pushEnabled[$userId] ?? true) {
                    NotificationDispatch::query()->firstOrCreate(
                        ['notification_id' => $notification->id],
                        ['status' => 'pending'],
                    );
                }

            }

            if ($notifications->isNotEmpty()) {
                DB::afterCommit(static function (): void {
                    try {
                        if (config('queue.default') !== 'sync') {
                            ProcessNotificationDeliveries::dispatch(50);
                        }
                    } catch (Throwable) {
                        // The delivery row remains the durable recovery source.
                    }
                });
            }
        });

        return $notifications;
    }

    public function sendAnnouncement(User $sender, array $data): NotificationAnnouncement
    {
        $key = (string) ($data['idempotency_key'] ?? Str::uuid());
        $roles = collect($data['roles'] ?? [])->unique()->values();
        $userIds = collect($data['user_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        if ($roles->isEmpty() && $userIds->isEmpty()) {
            throw new \InvalidArgumentException('Selecciona al menos un destinatario.');
        }

        $recipients = User::query()
            ->where('active', true)
            ->where(function ($query) use ($roles, $userIds): void {
                if ($userIds->isNotEmpty()) {
                    $query->whereIn('id', $userIds);
                }
                if ($roles->isNotEmpty()) {
                    $method = $userIds->isNotEmpty() ? 'orWhereHas' : 'whereHas';
                    $query->{$method}('roles', fn ($rolesQuery) => $rolesQuery->whereIn('name', $roles));
                }
            })
            ->get(['id']);

        if ($recipients->isEmpty() || $recipients->count() > 100) {
            throw new \InvalidArgumentException('Selecciona entre 1 y 100 destinatarios activos.');
        }

        return DB::transaction(function () use ($sender, $data, $key, $roles, $userIds, $recipients): NotificationAnnouncement {
            $announcement = NotificationAnnouncement::query()->create([
                'sender_user_id' => $sender->id,
                'title' => trim($data['title']),
                'message' => trim($data['message']),
                'category' => 'administrative',
                'priority' => $data['priority'] ?? 'normal',
                'audience' => ['roles' => $roles->all(), 'user_ids' => $userIds->all()],
                'recipient_count' => $recipients->count(),
                'idempotency_key' => $key,
            ]);

            $this->publishToUsers(
                $recipients->pluck('id'), 'administrative', $announcement->title,
                $announcement->message, 'announcement', $announcement->id,
                '/notifications', $announcement->priority,
                'announcement-'.$key, $sender->id,
            );

            return $announcement;
        });
    }

    public function refreshInventory(ProductVariant $variant, bool $notifyOnFirstSeen = true): void
    {
        $threshold = max(0, (int) config('firebase.low_stock_threshold', 3));
        $stock = max(0, (int) $variant->stock);
        $currentState = $stock === 0 ? 'out' : ($stock <= $threshold ? 'low' : 'normal');

        $state = NotificationInventoryState::query()
            ->where('product_variant_id', $variant->id)
            ->lockForUpdate()
            ->first();

        if (! $state) {
            $state = NotificationInventoryState::query()->create([
                'product_variant_id' => $variant->id,
                'state' => $notifyOnFirstSeen ? 'normal' : $currentState,
                'transition_count' => 0,
                'updated_at' => now(),
            ]);
            if (! $notifyOnFirstSeen) {
                return;
            }
        }

        if ($state->state === $currentState) {
            return;
        }

        $transition = (int) $state->transition_count + 1;
        $state->update(['state' => $currentState, 'transition_count' => $transition, 'updated_at' => now()]);
        if ($currentState === 'normal') {
            return;
        }

        $variant->loadMissing(['product', 'size', 'color']);
        $name = $variant->product?->name ?? 'Producto';
        $options = collect([$variant->size?->name, $variant->color?->name])->filter()->implode(' · ');
        $label = $options !== '' ? " ({$options})" : '';
        $isOut = $currentState === 'out';
        $category = $isOut ? 'product_out_of_stock' : 'inventory_low';
        $title = $isOut ? 'Producto agotado' : 'Inventario bajo';
        $message = $isOut
            ? "{$name}{$label} se quedó sin existencias."
            : "{$name}{$label} tiene {$stock} unidades disponibles.";

        $this->forManagers(
            $category, $title, $message, 'product', (int) $variant->product_id,
            '/products', $isOut ? 'high' : 'normal',
            "inventory:{$variant->id}:{$currentState}:{$transition}",
        );
    }

    private function safeInternalUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : null;
    }
}
