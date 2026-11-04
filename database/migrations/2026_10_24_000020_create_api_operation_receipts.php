<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_operation_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('operation_key', 100);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'operation_key']);
        });
    }

    public function down(): void
    {
        if (app()->runningUnitTests()) {
            Schema::dropIfExists('api_operation_receipts');
            return;
        }
        throw new RuntimeException('Operation receipts must survive rollback to prevent duplicate financial operations.');
    }
};
