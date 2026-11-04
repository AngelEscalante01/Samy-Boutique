<?php

namespace App\Jobs;

use App\Services\CatalogImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ImportCatalogGroup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $groupId) {}

    public function handle(CatalogImportService $imports): void
    {
        $imports->process($this->groupId);
    }

    public function failed(?\Throwable $exception): void
    {
        DB::table('catalog_import_groups')->where('id', $this->groupId)->where('status', 'queued')->update(['status' => 'failed', 'errors' => json_encode(['No se pudo procesar el grupo. Reintenta desde el lote o consulta el registro del servidor.']), 'updated_at' => now()]);
    }
}
