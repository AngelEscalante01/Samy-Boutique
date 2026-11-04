<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('catalog_slides', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180)->nullable();
            $table->string('subtitle', 255)->nullable();
            $table->string('button_text', 80)->nullable();
            $table->string('destination', 2048)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->string('desktop_disk', 30);
            $table->string('desktop_path', 255);
            $table->string('mobile_disk', 30)->nullable();
            $table->string('mobile_path', 255)->nullable();
            $table->uuid('generation');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_slides');
    }
};
