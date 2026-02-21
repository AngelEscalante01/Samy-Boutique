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
        if (Schema::hasTable('sales')) {
            return;
        }

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Totales
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('coupon_discount_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2);

            // Descuento global manual (para auditoría)
            $table->string('global_discount_type', 20)->nullable();
            $table->decimal('global_discount_value', 10, 2)->nullable();

            // Cupón aplicado (snapshot de código)
            $table->string('coupon_code', 60)->nullable()->index();
            $table->foreignId('coupon_id')
                ->nullable()
                ->constrained('coupons')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Estado (validar en backend): completed|cancelled
            $table->string('status', 20)->default('completed')->index();

            $table->timestamps();

            $table->index(['created_by', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
