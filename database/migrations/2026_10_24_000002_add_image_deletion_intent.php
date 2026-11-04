<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('deletion_state', 24)->nullable()->index();
            $table->timestamp('object_deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! app()->runningUnitTests()) {
            throw new RuntimeException('Reconcile outstanding object deletion intents before reverting.');
        }
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex(['deletion_state']);
            $table->dropColumn(['deletion_state', 'object_deleted_at']);
        });
    }
};
