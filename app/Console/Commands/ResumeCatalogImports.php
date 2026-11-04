<?php

namespace App\Console\Commands;

use App\Jobs\ImportCatalogGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResumeCatalogImports extends Command
{
    protected $signature = 'catalog:resume-imports';

    protected $description = 'Reencola grupos confirmados pendientes por más de cinco minutos; no duplica productos.';

    public function handle(): int
    {
        $count = 0;
        DB::table('catalog_import_groups')->where('status', 'queued')->where('updated_at', '<=', now()->subMinutes(5))
            ->orderBy('id')->chunkById(100, function ($groups) use (&$count) {
                foreach ($groups as $group) {
                    ImportCatalogGroup::dispatch($group->id)->onConnection('database')->onQueue('catalog-imports');
                    $count++;
                }
            });
        $this->info("Grupos reencolados: {$count}");

        return self::SUCCESS;
    }
}
