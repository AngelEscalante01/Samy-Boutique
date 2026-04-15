<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('layaway_payments')) {
            return;
        }

        Schema::table('layaway_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('layaway_payments', 'observacion')) {
                $table->text('observacion')->nullable()->after('reference');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('layaway_payments')) {
            return;
        }

        Schema::table('layaway_payments', function (Blueprint $table) {
            if (Schema::hasColumn('layaway_payments', 'observacion')) {
                $table->dropColumn('observacion');
            }
        });
    }
};
