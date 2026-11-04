<?php

namespace App\Http\Controllers\Api;

use App\Models\NotificationAnnouncement;
use App\Models\NotificationDelivery;
use App\Models\NotificationDevice;
use App\Models\NotificationPreference;
use App\Models\SystemNotification;
use App\Services\NotificationEventService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NotificationController extends ApiController
{
    // The deployed POS ApiController has a different response API from the local
    // branch. Keep notification responses self-contained in the overlay.
    protected function success(mixed $data, string $message = 'Operación completada.', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    protected function paginated(LengthAwarePaginator $paginator, callable $transform, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->getCollection()->map($transform)->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    private const DEFAULT_PREFERENCES = [
        'sale' => true,
        'sale_cancelled' => true,
        'inventory_low' => true,
        'product_out_of_stock' => true,
        'layaway' => true,
        'layaway_payment' => true,
        'layaway_liquidated' => true,
        'layaway_due' => true,
        'layaway_expired' => true,
        'cash_cut' => true,
        'administrative' => true,
        'all_sales' => true,
        'push_enabled' => true,
    ];

    public function index(Request $request)
    {
        $rows = SystemNotification::query()
            ->where('user_id', $request->user()->id)
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 50));

        return $this->paginated($rows, fn (SystemNotification $notification) => $this->notificationData($notification), 'Notificaciones obtenidas.');
    }

    public function count(Request $request)
    {
        $count = SystemNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return $this->success(['unread_count' => $count], 'Contador obtenido.');
    }

    public function show(Request $request, int $notification)
    {
        $row = SystemNotification::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($notification);

        return $this->success(['notification' => $this->notificationData($row)], 'Notificación obtenida.');
    }

    public function markRead(Request $request, int $notification)
    {
        $row = SystemNotification::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($notification);
        $row->update(['read_at' => $row->read_at ?? now()]);

        return $this->success(['notification' => $this->notificationData($row->fresh())], 'Notificación marcada como leída.');
    }

    public function markAllRead(Request $request)
    {
        $updated = SystemNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->success(['updated' => $updated], 'Notificaciones marcadas como leídas.');
    }

    public function preferences(Request $request)
    {
        $stored = NotificationPreference::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['preferences' => self::DEFAULT_PREFERENCES],
        );

        return $this->success(['preferences' => array_merge(self::DEFAULT_PREFERENCES, $stored->preferences ?? [])], 'Preferencias obtenidas.');
    }

    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.sale' => ['sometimes', 'boolean'],
            'preferences.sale_cancelled' => ['sometimes', 'boolean'],
            'preferences.inventory_low' => ['sometimes', 'boolean'],
            'preferences.product_out_of_stock' => ['sometimes', 'boolean'],
            'preferences.layaway' => ['sometimes', 'boolean'],
            'preferences.layaway_payment' => ['sometimes', 'boolean'],
            'preferences.layaway_liquidated' => ['sometimes', 'boolean'],
            'preferences.layaway_due' => ['sometimes', 'boolean'],
            'preferences.layaway_expired' => ['sometimes', 'boolean'],
            'preferences.cash_cut' => ['sometimes', 'boolean'],
            'preferences.administrative' => ['sometimes', 'boolean'],
            'preferences.all_sales' => ['sometimes', 'boolean'],
            'preferences.push_enabled' => ['sometimes', 'boolean'],
        ]);

        $existing = NotificationPreference::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['preferences' => self::DEFAULT_PREFERENCES],
        );
        $preferences = array_merge(self::DEFAULT_PREFERENCES, $existing->preferences ?? [], $validated['preferences']);
        $existing->update(['preferences' => $preferences]);

        return $this->success(['preferences' => $preferences], 'Preferencias guardadas.');
    }

    public function registerDevice(Request $request)
    {
        $data = $request->validate([
            'installation_id' => ['required', 'uuid'],
            'platform' => ['required', Rule::in(['web', 'android'])],
            'name' => ['nullable', 'string', 'max:120'],
            'token' => ['nullable', 'string', 'max:4096'],
            'permission_status' => ['required', Rule::in(['granted', 'denied', 'not_determined', 'provisional'])],
        ]);

        $userId = (int) $request->user()->id;
        $token = $data['permission_status'] === 'granted' ? ($data['token'] ?? null) : null;

        $device = DB::transaction(function () use ($data, $userId, $token): NotificationDevice {
            $device = NotificationDevice::query()
                ->where('installation_id', $data['installation_id'])
                ->lockForUpdate()
                ->first();

            if ($device && (int) $device->user_id !== $userId) {
                NotificationDelivery::query()
                    ->where('device_id', $device->id)
                    ->whereIn('status', ['pending', 'error_temporal', 'sending'])
                    ->update(['status' => 'cancelled', 'next_attempt_at' => null, 'last_error_code' => 'device_account_changed']);
            }

            $device ??= new NotificationDevice(['installation_id' => $data['installation_id']]);
            $device->fill([
                'user_id' => $userId,
                'platform' => $data['platform'],
                'name' => $data['name'] ?? null,
                'token' => $token,
                'token_hash' => $token ? hash('sha256', $token) : null,
                'permission_status' => $data['permission_status'],
                'active' => true,
                'last_seen_at' => now(),
                'token_updated_at' => $token ? now() : $device->token_updated_at,
            ])->save();

            return $device->fresh();
        });

        return $this->success(['device' => $this->deviceData($device)], 'Dispositivo registrado.');
    }

    public function unregisterDevice(Request $request, string $installationId)
    {
        $device = NotificationDevice::query()
            ->where('installation_id', $installationId)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($device) {
            DB::transaction(function () use ($device): void {
                NotificationDelivery::query()
                    ->where('device_id', $device->id)
                    ->whereIn('status', ['pending', 'error_temporal', 'sending'])
                    ->update(['status' => 'cancelled', 'next_attempt_at' => null, 'last_error_code' => 'device_logged_out']);
                $device->update([
                    'user_id' => null,
                    'token' => null,
                    'token_hash' => null,
                    'active' => false,
                    'permission_status' => 'not_determined',
                ]);
            });
        }

        return $this->success(null, 'Dispositivo desvinculado.');
    }

    public function sendManual(Request $request, NotificationEventService $events)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'message' => ['required', 'string', 'max:500'],
            'user_ids' => ['sometimes', 'array', 'max:100'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'roles' => ['sometimes', 'array', 'max:2'],
            'roles.*' => ['string', Rule::in(['gerente', 'administrador', 'cajero'])],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high'])],
            'idempotency_key' => ['required', 'uuid'],
            'confirm' => ['required', 'accepted'],
        ]);

        try {
            $announcement = $events->sendAnnouncement($request->user(), $data);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage(), 'errors' => (object) []], 422);
        } catch (QueryException $exception) {
            $announcement = NotificationAnnouncement::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();
            if (! $announcement) {
                throw $exception;
            }
        }

        return $this->success([
            'announcement' => [
                'id' => $announcement->id,
                'recipient_count' => $announcement->recipient_count,
                'created_at' => $announcement->created_at?->toISOString(),
            ],
        ], 'Aviso enviado a la bandeja de notificaciones.');
    }

    public function sentHistory(Request $request)
    {
        $history = NotificationAnnouncement::query()
            ->with('sender:id,name')
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 50));

        return $this->paginated($history, fn (NotificationAnnouncement $row) => [
            'id' => $row->id,
            'title' => $row->title,
            'message' => $row->message,
            'priority' => $row->priority,
            'audience' => $row->audience,
            'recipient_count' => $row->recipient_count,
            'sender' => $row->sender?->name,
            'created_at' => $row->created_at?->toISOString(),
        ], 'Historial de avisos obtenido.');
    }

    public function recipients(Request $request)
    {
        $recipients = \App\Models\User::query()
            ->where('active', true)
            ->with('roles:name')
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 100), 1), 100));

        return $this->paginated($recipients, fn (\App\Models\User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'roles' => $user->getRoleNames()->values(),
        ], 'Destinatarios activos obtenidos.');
    }

    private function notificationData(SystemNotification $row): array
    {
        return [
            'id' => $row->id,
            'category' => $row->category,
            'title' => $row->title,
            'message' => $row->message,
            'priority' => $row->priority,
            'resource_type' => $row->resource_type,
            'resource_id' => $row->resource_id,
            'url' => $row->url,
            'read_at' => $row->read_at?->toISOString(),
            'created_at' => $row->created_at?->toISOString(),
        ];
    }

    private function deviceData(NotificationDevice $device): array
    {
        return [
            'installation_id' => $device->installation_id,
            'platform' => $device->platform,
            'name' => $device->name,
            'permission_status' => $device->permission_status,
            'active' => $device->active,
            'token_updated_at' => $device->token_updated_at?->toISOString(),
        ];
    }
}
