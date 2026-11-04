<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_import_batches', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained();
            $t->string('filename');
            $t->char('file_hash', 64);
            $t->string('mode')->default('create');
            $t->timestamps();
            $t->unique(['user_id', 'file_hash', 'mode']);
        });
        Schema::create('catalog_import_groups', function (Blueprint $t) {
            $t->id();
            $t->uuid('batch_id');
            $t->foreign('batch_id')->references('id')->on('catalog_import_batches');
            $t->string('group_key', 100);
            $t->json('rows');
            $t->json('errors');
            $t->string('status')->default('pending');
            $t->unsignedBigInteger('product_id')->nullable();
            $t->timestamps();
            $t->unique(['batch_id', 'group_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_import_groups');
        Schema::dropIfExists('catalog_import_batches');
    }
};
