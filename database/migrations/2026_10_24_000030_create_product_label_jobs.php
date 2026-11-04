<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_label_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('width_mm');
            $table->unsignedSmallInteger('height_mm');
            $table->boolean('show_price');
            $table->json('labels');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        if (! app()->runningUnitTests()) {
            throw new RuntimeException('Preserve label job snapshots; rollback requires an explicit archival procedure.');
        }
        Schema::dropIfExists('product_label_jobs');
    }
};
