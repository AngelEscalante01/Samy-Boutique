<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Neubox already has created_by (including its foreign key) and
        // observacion as TEXT. Preserve those columns and their values.
        if (! Schema::hasColumn('layaway_payments', 'created_by')) {
            Schema::table('layaway_payments', fn (Blueprint $table) => $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete());
        }
        if (! Schema::hasColumn('layaway_payments', 'observacion')) {
            Schema::table('layaway_payments', fn (Blueprint $table) => $table->string('observacion', 500)->nullable());
        }
        if (! Schema::hasColumn('layaway_payments', 'payer_name')) {
            Schema::table('layaway_payments', fn (Blueprint $table) => $table->string('payer_name', 180)->nullable());
        }
        Schema::table('layaways', function (Blueprint $table) {
            $table->boolean('delivery_tracking_enabled')->default(false);
            $table->decimal('loyalty_discount_total', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('layaways', fn (Blueprint $table) => $table->dropColumn(['delivery_tracking_enabled', 'loyalty_discount_total']));
        Schema::table('layaway_payments', fn (Blueprint $table) => $table->dropColumn('payer_name'));
    }
};
