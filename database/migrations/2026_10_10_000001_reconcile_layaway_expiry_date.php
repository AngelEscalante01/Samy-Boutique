<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DEV installations may have run the former notifications migration,
        // which added expires_at. Preserve those values while switching all
        // application reads and writes to the production DATE column.
        if (! Schema::hasColumn('layaways', 'fecha_vencimiento')) {
            Schema::table('layaways', function (Blueprint $table): void {
                $table->date('fecha_vencimiento')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('layaways', 'vigencia_dias')) {
            Schema::table('layaways', function (Blueprint $table): void {
                $table->unsignedInteger('vigencia_dias')->nullable();
            });
        }

        if (! Schema::hasColumn('layaways', 'observaciones')) {
            Schema::table('layaways', function (Blueprint $table): void {
                $table->text('observaciones')->nullable();
            });
        }

        if (Schema::hasColumn('layaways', 'expires_at')) {
            DB::table('layaways')
                ->whereNull('fecha_vencimiento')
                ->whereNotNull('expires_at')
                ->update(['fecha_vencimiento' => DB::raw('DATE(expires_at)')]);
        }
    }

    public function down(): void
    {
        // Existing due dates, observations and legacy DEV data are business
        // records. A rollback must not erase them.
    }
};
