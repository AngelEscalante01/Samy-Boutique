<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Neubox already has this table from a migration that is not present
        // in this checkout. Preserve its tokens and add only the missing index.
        if (Schema::hasTable('personal_access_tokens')) {
            if (! Schema::hasColumn('personal_access_tokens', 'expires_at')) {
                Schema::table('personal_access_tokens', function (Blueprint $table) {
                    $table->timestamp('expires_at')->nullable();
                });
            }
            if (! Schema::hasIndex('personal_access_tokens', 'personal_access_tokens_expires_at_index')) {
                Schema::table('personal_access_tokens', function (Blueprint $table) {
                    $table->index('expires_at');
                });
            }

            return;
        }

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('migrations')->where('migration', '2026_04_14_120000_create_personal_access_tokens_table')->exists()) {
            return;
        }

        Schema::dropIfExists('personal_access_tokens');
    }
};
