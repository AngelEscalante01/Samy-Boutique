<?php

namespace App\Console\Commands;

use App\Services\CatalogCodeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FillMissingCatalogCodes extends Command
{
    protected $signature = 'catalog:fill-missing-codes {--apply : Emitir códigos faltantes; sin esta opción solo informa}';

    protected $description = 'Revisa SKU faltantes y conflictos sin renumerar códigos históricos.';

    public function handle(CatalogCodeService $codes): int
    {
        $conflicts = DB::table('catalog_code_conflicts')->whereNull('resolved_at')->count();
        if ($conflicts > 0) {
            $this->error("Hay {$conflicts} conflictos en catalog_code_conflicts. La emisión está bloqueada hasta conciliarlos.");

            return self::FAILURE;
        }

        $failed = false;
        foreach (['products' => 'product', 'product_variants' => 'variant'] as $table => $type) {
            $missing = DB::table($table)->where(fn ($query) => $query->whereNull('sku')->orWhereRaw("TRIM(sku) = ''"));
            $this->info("{$table}: {$missing->count()} códigos faltantes.");
            if (! $this->option('apply')) {
                continue;
            }

            $missing->orderBy('id')->chunkById(100, function ($rows) use ($table, $type, $codes, &$failed) {
                foreach ($rows as $row) {
                    try {
                        DB::transaction(function () use ($table, $type, $row, $codes) {
                            // Same lock order as normal product creation.
                            DB::table('catalog_code_state')->where('id', 1)->lockForUpdate()->firstOrFail();
                            $current = DB::table($table)->where('id', $row->id)->lockForUpdate()->first();
                            if (! $current || trim((string) $current->sku) !== '') {
                                return;
                            }
                            $categoryId = $type === 'product'
                                ? $current->category_id
                                : DB::table('products')->where('id', $current->product_id)->value('category_id');
                            $code = $codes->next((int) $categoryId, $type);
                            DB::table($table)->where('id', $current->id)->update(['sku' => $code, 'updated_at' => now()]);
                            $codes->bind($code, $type, (int) $current->id);
                        }, 3);
                    } catch (ValidationException $exception) {
                        $failed = true;
                        $this->error("{$type} #{$row->id}: ".collect($exception->errors())->flatten()->implode(' '));
                    }
                }
            });
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
