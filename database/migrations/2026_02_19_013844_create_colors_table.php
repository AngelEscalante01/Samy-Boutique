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
        Schema::create('colors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            // Ej: #FF00AA (opcional, útil si el color viene solo por nombre)
            $table->string('hex', 7)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->unique('name');
            $table->index('hex');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colors');
    }
};
