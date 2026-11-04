<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('layaway_refunds', function (Blueprint $table) {
            $table->string('operation_key', 100)->nullable();
            $table->unique(['layaway_id', 'operation_key']);
        });
    }

    public function down(): void
    {
        if (! app()->runningUnitTests()) {
            throw new RuntimeException('Refund operation keys must survive rollback to prevent duplicate refunds.');
        }
        Schema::table('layaway_refunds', function (Blueprint $table) {
            $table->dropUnique(['layaway_id', 'operation_key']);
            $table->dropColumn('operation_key');
        });
    }
};
