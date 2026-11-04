<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_stock_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->foreignId('source_sale_id')->constrained('sales');
            $table->uuid('generation');
            $table->timestamp('started_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 100)->nullable();
            $table->timestamps();
            $table->unique(['product_variant_id', 'generation']);
            $table->index(['product_id', 'cancelled_at', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_episodes');
    }
};
