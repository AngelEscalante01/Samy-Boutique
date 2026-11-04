<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('disk', 32)->default('public');
            $table->uuid('generation')->nullable()->unique();
            $table->string('sha256', 64)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->string('content_type', 100)->nullable();
            $table->string('source_disk', 32)->nullable();
            $table->text('source_path')->nullable();
            $table->timestamp('storage_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        if (app()->runningUnitTests()) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->dropUnique(['generation']);
                $table->dropColumn(['disk', 'generation', 'sha256', 'bytes', 'content_type', 'source_disk', 'source_path', 'storage_verified_at']);
            });
            return;
        }
        // Reverting this mapping would lose the location of migrated objects.
        throw new RuntimeException('Restore and reconcile media references before reverting this migration.');
    }
};
