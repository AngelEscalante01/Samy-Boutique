<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_image_derivatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_image_id')->constrained('product_images')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('disk', 30);
            $table->string('path', 255);
            $table->uuid('generation');
            $table->char('sha256', 64);
            $table->unsignedBigInteger('bytes');
            $table->string('content_type', 80)->default('image/webp');
            $table->timestamps();
            $table->unique(['product_image_id', 'kind']);
            $table->unique(['disk', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image_derivatives');
    }
};
