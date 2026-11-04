<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('photo_disk', 30)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->uuid('photo_generation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn(['photo_disk', 'photo_path', 'photo_generation']));
    }
};
