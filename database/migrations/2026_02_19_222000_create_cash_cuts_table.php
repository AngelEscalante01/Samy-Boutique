<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_cuts', function (Blueprint $table) {
            $table->id();

            $table->date('cut_date')->index();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->json('totals_json');

            $table->timestamps();

            // Corte diario: 1 por fecha (simple)
            $table->unique(['cut_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_cuts');
    }
};
