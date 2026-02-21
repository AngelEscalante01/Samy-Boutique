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
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')
                ->constrained('coupons')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('sale_id')
                ->constrained('sales')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('redeemed_at')->useCurrent()->index();
            $table->timestamps();

            $table->unique(['coupon_id', 'sale_id']);
            $table->index(['coupon_id', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};
