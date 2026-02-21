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
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')
                ->constrained('sales')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Validar en backend: cash|card|transfer|other
            $table->string('method', 20);
            $table->decimal('amount', 10, 2);
            $table->string('reference', 120)->nullable();
            $table->timestamps();

            $table->index(['sale_id', 'method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
