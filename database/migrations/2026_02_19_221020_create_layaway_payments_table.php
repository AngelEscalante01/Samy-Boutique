<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layaway_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('layaway_id')
                ->constrained('layaways')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('method', 20); // cash|card|transfer|other
            $table->decimal('amount', 10, 2);
            $table->string('reference', 120)->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['layaway_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layaway_payments');
    }
};
