<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('layaways')) {
            return;
        }

        Schema::table('layaways', function (Blueprint $table) {
            if (! Schema::hasColumn('layaways', 'observaciones')) {
                $table->text('observaciones')->nullable()->after('fecha_vencimiento');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('layaways')) {
            return;
        }

        Schema::table('layaways', function (Blueprint $table) {
            if (Schema::hasColumn('layaways', 'observaciones')) {
                $table->dropColumn('observaciones');
            }
        });
    }
};
