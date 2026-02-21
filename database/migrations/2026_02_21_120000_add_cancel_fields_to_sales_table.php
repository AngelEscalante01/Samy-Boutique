<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'canceled_at')) {
                $table->dateTime('canceled_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('sales', 'canceled_by')) {
                $table->foreignId('canceled_by')->nullable()->after('canceled_at')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('sales', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('canceled_by');
            }

            if (!Schema::hasColumn('sales', 'cancel_type')) {
                $table->string('cancel_type', 20)->nullable()->after('cancel_reason');
            }

            if (!Schema::hasColumn('sales', 'inventory_action')) {
                $table->string('inventory_action', 30)->nullable()->after('cancel_type');
            }

            if (!Schema::hasColumn('sales', 'return_condition')) {
                $table->string('return_condition', 20)->nullable()->after('inventory_action');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'return_condition')) {
                $table->dropColumn('return_condition');
            }

            if (Schema::hasColumn('sales', 'inventory_action')) {
                $table->dropColumn('inventory_action');
            }

            if (Schema::hasColumn('sales', 'cancel_type')) {
                $table->dropColumn('cancel_type');
            }

            if (Schema::hasColumn('sales', 'cancel_reason')) {
                $table->dropColumn('cancel_reason');
            }

            if (Schema::hasColumn('sales', 'canceled_by')) {
                $table->dropConstrainedForeignId('canceled_by');
            }

            if (Schema::hasColumn('sales', 'canceled_at')) {
                $table->dropColumn('canceled_at');
            }
        });
    }
};
