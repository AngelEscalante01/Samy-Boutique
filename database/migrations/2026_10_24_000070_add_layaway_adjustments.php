<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('layaways', function (Blueprint $table) {
            $table->string('global_discount_type', 20)->nullable();
            $table->decimal('global_discount_value', 10, 2)->nullable();
            $table->decimal('manual_discount_total', 10, 2)->default(0);
            $table->decimal('refunded_total', 10, 2)->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('delivered_at')->nullable();
        });

        Schema::create('layaway_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('layaway_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version_before');
            $table->unsignedInteger('version_after');
            $table->json('before_snapshot');
            $table->json('after_snapshot');
            $table->string('reason', 500);
            $table->timestamps();
        });

        Schema::create('layaway_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('layaway_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method', 20);
            $table->string('reference', 120)->nullable();
            $table->string('reason', 500);
            $table->string('status', 20)->default('confirmed');
            $table->timestamp('refunded_at');
            $table->timestamps();
            $table->index(['refunded_at', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layaway_refunds');
        Schema::dropIfExists('layaway_adjustments');
        Schema::table('layaways', fn (Blueprint $table) => $table->dropColumn(['global_discount_type', 'global_discount_value', 'manual_discount_total', 'refunded_total', 'version', 'delivered_at']));
    }
};
