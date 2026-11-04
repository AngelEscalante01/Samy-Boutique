<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('web_sale_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('operation_key', 100);
            $table->char('request_hash', 64);
            $table->foreignId('sale_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'operation_key']);
        });
    }

    public function down(): void
    {
        if (app()->runningUnitTests()) {
            Schema::dropIfExists('web_sale_attempts');

            return;
        }

        throw new RuntimeException('Web sale attempts must survive rollback to prevent duplicate sales.');
    }
};
