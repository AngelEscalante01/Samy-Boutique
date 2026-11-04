<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->string('sku_prefix', 12)->nullable());
        Schema::create('catalog_code_state', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
        });
        DB::table('catalog_code_state')->insert(['id' => 1]);
        Schema::create('catalog_code_prefixes', function (Blueprint $table) {
            $table->string('prefix', 12)->primary();
            // Deliberately no cascading FK: retired prefixes remain reserved forever.
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('last_number')->default(0);
        });
        Schema::create('catalog_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80);
            $table->char('code_key', 64)->unique();
            $table->string('owner_type', 12);
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->boolean('is_alias')->default(false);
            $table->timestamps();
            $table->index(['owner_type', 'owner_id']);
        });
        Schema::create('catalog_code_conflicts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80);
            $table->string('owner_type', 12);
            $table->unsignedBigInteger('owner_id');
            $table->text('reason');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['owner_type', 'owner_id']);
        });
        foreach (['products' => 'product', 'product_variants' => 'variant'] as $table => $type) {
            DB::table($table)->whereNotNull('sku')->orderBy('id')->chunkById(500, function ($rows) use ($type) {
                foreach ($rows as $row) {
                    $code = trim((string) $row->sku);
                    if ($code === '') {
                        continue;
                    }
                    $key = hash('sha256', mb_strtoupper($code, 'UTF-8'));
                    if (DB::table('catalog_codes')->where('code_key', $key)->exists()) {
                        DB::table('catalog_code_conflicts')->insert(['code' => $row->sku, 'owner_type' => $type, 'owner_id' => $row->id, 'reason' => 'Código previo compartido entre entidades; requiere conciliación manual sin renumerar historial.', 'created_at' => now(), 'updated_at' => now()]);
                    } else {
                        DB::table('catalog_codes')->insert(['code' => $row->sku, 'code_key' => $key, 'owner_type' => $type, 'owner_id' => $row->id, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        if (app()->runningUnitTests()) {
            Schema::dropIfExists('catalog_code_conflicts');
            Schema::dropIfExists('catalog_codes');
            Schema::dropIfExists('catalog_code_prefixes');
            Schema::dropIfExists('catalog_code_state');
            Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('sku_prefix'));

            return;
        }
        // Issued codes must never become reusable through a routine rollback.
        throw new RuntimeException('El registro de códigos es permanente; restaura un respaldo validado para revertir esta migración.');
    }
};
