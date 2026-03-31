<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('layaway_payments', function (Blueprint $table) {
            $table->foreignId('created_by')
                ->nullable()
                ->after('layaway_id')
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('layaway_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
