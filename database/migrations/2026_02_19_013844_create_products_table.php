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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 80)->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();

            // Catálogos: en Samy Boutique se desactivan (active=false) en lugar de borrarse.
            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Guardamos "enums" como strings por compatibilidad (MySQL/MariaDB) y facilidad de cambios.
            // Valores esperados (validar en backend): dama|caballero|unisex
            $table->string('gender', 20)->index();

            $table->foreignId('size_id')
                ->constrained('sizes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('color_id')
                ->constrained('colors')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->decimal('purchase_price', 10, 2);
            $table->decimal('sale_price', 10, 2);

            // Valores esperados (validar en backend): disponible|apartado|vendido|cancelado
            $table->string('status', 20)->default('disponible')->index();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('sold_at')->nullable()->index();
            $table->timestamps();

            // Índices típicos para búsqueda/filtrado de inventario
            $table->index(['status', 'category_id']);
            $table->index(['status', 'gender']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
