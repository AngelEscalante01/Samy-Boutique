<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('sales')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'cash_received')) {
                $table->decimal('cash_received', 10, 2)->default(0)->after('total');
            }

            if (! Schema::hasColumn('sales', 'change')) {
                $table->decimal('change', 10, 2)->default(0)->after('cash_received');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('sales')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'change')) {
                $table->dropColumn('change');
            }

            if (Schema::hasColumn('sales', 'cash_received')) {
                $table->dropColumn('cash_received');
            }
        });
    }
};
