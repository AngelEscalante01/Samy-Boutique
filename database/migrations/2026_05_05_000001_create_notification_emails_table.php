<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_emails', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191)->unique();
            $table->string('label', 100)->nullable()->comment('Nombre descriptivo del destinatario');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_emails');
    }
};
