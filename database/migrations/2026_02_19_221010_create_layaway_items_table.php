<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layaway_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('layaway_id')
                ->constrained('layaways')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->unsignedInteger('quantity')->default(1);

            // Snapshot para auditoría
            $table->string('sku', 80);
            $table->string('name', 160);
            $table->decimal('unit_price', 10, 2);

            $table->timestamps();

            $table->unique(['layaway_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layaway_items');
    }
};
