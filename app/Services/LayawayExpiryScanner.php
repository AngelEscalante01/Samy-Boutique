<?php

namespace App\Services;

use App\Models\Layaway;
use App\Models\SystemNotification;
use Illuminate\Support\Carbon;

class LayawayExpiryScanner
{
    public function __construct(private readonly NotificationEventService $events) {}

    /**
     * Counts candidate events, not devices. Dry runs never write or dispatch.
     * Historical layaways are excluded from live scans by an explicit ID cut.
     *
     * @return array<string, int|string|bool|null>
     */
    public function run(bool $dryRun = false, bool $includeHistorical = false): array
    {
        $timezone = (string) config('firebase.layaway_timezone', 'America/Mexico_City');
        $today = Carbon::now($timezone)->toDateString();
        $threeDays = Carbon::now($timezone)->addDays(3)->toDateString();
        $rawMinId = config('firebase.layaway_scan_min_id');
        $minId = is_numeric($rawMinId) ? (int) $rawMinId : null;
        $enabled = (bool) config('firebase.layaway_scan_enabled', false);
        $canPublish = ! $dryRun && $enabled && $minId !== null;

        $summary = [
            'timezone' => $timezone,
            'today' => $today,
            'enabled' => $enabled,
            'min_id' => $minId,
            'historical_included' => $dryRun && $includeHistorical,
            'open_with_date' => 0,
            'historical_excluded' => 0,
            'due' => 0,
            'expired' => 0,
            'already_notified' => 0,
            'published' => 0,
        ];

        Layaway::query()
            ->where('status', 'open')
            ->whereNotNull('fecha_vencimiento')
            ->select(['id', 'fecha_vencimiento'])
            ->chunkById(100, function ($layaways) use (&$summary, $today, $threeDays, $minId, $dryRun, $includeHistorical, $canPublish): void {
                foreach ($layaways as $layaway) {
                    $summary['open_with_date']++;
                    if ($minId === null || $layaway->id <= $minId) {
                        $summary['historical_excluded']++;
                        if (! ($dryRun && $includeHistorical)) {
                            continue;
                        }
                    }

                    $due = $layaway->fecha_vencimiento->toDateString();
                    $category = $due < $today ? 'layaway_expired' : ($due <= $threeDays ? 'layaway_due' : null);
                    if ($category === null) {
                        continue;
                    }

                    $eventKey = "{$category}:{$layaway->id}:{$due}";
                    if (SystemNotification::query()->where('event_key', $eventKey)->exists()) {
                        $summary['already_notified']++;

                        continue;
                    }
                    $summary[$category === 'layaway_due' ? 'due' : 'expired']++;

                    if (! $canPublish || ($minId !== null && $layaway->id <= $minId)) {
                        continue;
                    }

                    $expired = $category === 'layaway_expired';
                    $published = $this->events->forManagers(
                        $category,
                        $expired ? "Apartado #{$layaway->id} vencido" : "Apartado #{$layaway->id} próximo a vencer",
                        $expired ? 'El apartado superó su fecha de vencimiento.' : 'El apartado vence en los próximos tres días.',
                        'layaway', (int) $layaway->id, "/layaways/{$layaway->id}",
                        $expired ? 'high' : 'normal', $eventKey,
                    );
                    $summary['published'] += $published->count();
                }
            });

        return $summary;
    }
}
