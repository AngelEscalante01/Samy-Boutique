<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Present already in the Neubox schema, although this migration is
        // absent from its migrations table. Keep the existing data and type.
        if (Schema::hasColumn('sales', 'cash_received')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('cash_received', 10, 2)->nullable()->after('total');
        });
    }

    public function down(): void
    {
        // The column may predate this migration and contain historical money.
        // Never drop it during an automated rollback.
    }
};
