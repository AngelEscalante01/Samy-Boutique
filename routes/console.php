<?php

use App\Models\ProductVariant;
use App\Services\LayawayExpiryScanner;
use App\Services\NotificationDeliveryProcessor;
use App\Services\NotificationEventService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('notifications:process-push', function (NotificationDeliveryProcessor $processor): void {
    $count = $processor->processBatch(5);
    $this->info("Procesados {$count} intentos push.");
})->purpose('Procesa una tanda pequeña de entregas push pendientes');

// Durable rows also recover if queue dispatch was interrupted or FCM requested
// a later retry. The immediate queue worker and this bounded pass share locks.
ScheduleFacade::call(function (NotificationDeliveryProcessor $processor): void {
    $processor->processBatch(5);
})->name('notifications:recover-push')->everyMinute()->withoutOverlapping(5)
    ->when(fn (): bool => (bool) config('firebase.push_enabled', false));

Artisan::command('notifications:scan-layaways {--dry-run : Solo cuenta candidatos; no crea avisos ni envía FCM} {--include-historical : Incluye apartados previos al corte solo en simulación}', function (LayawayExpiryScanner $scanner): void {
    $dryRun = (bool) $this->option('dry-run');
    $includeHistorical = (bool) $this->option('include-historical');
    if ($includeHistorical && ! $dryRun) {
        $this->error('--include-historical solo se permite con --dry-run.');

        return;
    }
    $summary = $scanner->run($dryRun, $includeHistorical);
    $this->line(($dryRun ? 'DRY_RUN_APARTADOS ' : 'APARTADOS ').json_encode($summary, JSON_UNESCAPED_SLASHES));
})->purpose('Escanea vencimientos de apartados con corte histórico y simulación segura');

Artisan::command('notifications:scan-inventory', function (NotificationEventService $events): void {
    $count = 0;
    ProductVariant::query()->where('active', true)->with(['product', 'size', 'color'])
        ->chunkById(100, function ($variants) use ($events, &$count): void {
            foreach ($variants as $variant) {
                $events->refreshInventory($variant, false);
                $count++;
            }
        });
    $this->info("Revisadas {$count} variantes de inventario.");
})->purpose('Detecta transiciones de inventario bajo y productos agotados');

ScheduleFacade::command('notifications:scan-layaways')->dailyAt('08:00')->withoutOverlapping();
ScheduleFacade::command('notifications:scan-inventory')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('images:process-deletions', function (): void {
    \App\Models\ProductImage::query()->whereNotNull('deleted_at')->where('deletion_state', 'pending')
        ->orderBy('id')->limit(100)->get()->each(function ($image) {
            \App\Jobs\DeleteRequestedProductImage::dispatch($image->id, \App\Jobs\DeleteRequestedProductImage::identity($image))
                ->onConnection('database')->onQueue('default');
        });
})->purpose('Enqueue durable explicit image-removal requests; not sale retention');

ScheduleFacade::command('images:process-deletions')->everyMinute()->withoutOverlapping();
ScheduleFacade::command('images:retry-shared')->dailyAt('03:00')->withoutOverlapping();
ScheduleFacade::command('catalog:resume-imports')->everyFiveMinutes()->withoutOverlapping();
