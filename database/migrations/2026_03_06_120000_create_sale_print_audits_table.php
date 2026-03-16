<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_print_audits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sale_id')
                ->constrained('sales')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('ticket_type', 40)->default('sale');
            $table->boolean('print_attempted')->default(true);
            $table->boolean('print_success')->default(false);
            $table->text('error_message')->nullable();
            $table->string('connection_method', 40)->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['sale_id', 'printed_at']);
            $table->index(['sale_id', 'print_success']);
            $table->index('ticket_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_print_audits');
    }
};
