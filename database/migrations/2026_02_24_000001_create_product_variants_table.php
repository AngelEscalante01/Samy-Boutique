<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('size_id')
                ->constrained('sizes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('color_id')
                ->constrained('colors')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('sku', 80)->nullable()->unique();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'size_id', 'color_id']);
            $table->index(['product_id', 'active']);
            $table->index(['stock', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
