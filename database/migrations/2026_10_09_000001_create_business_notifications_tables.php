<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 48)->index();
            $table->string('title', 140);
            $table->string('message', 500);
            $table->string('priority', 16)->default('normal');
            $table->string('resource_type', 64)->nullable();
            $table->string('resource_id', 64)->nullable();
            $table->string('url', 255)->nullable();
            $table->string('event_key', 160)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'event_key']);
            $table->index(['user_id', 'read_at', 'created_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('preferences');
            $table->timestamps();
        });

        Schema::create('notification_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('installation_id')->unique();
            $table->string('platform', 16);
            $table->string('name', 120)->nullable();
            $table->text('token')->nullable();
            $table->char('token_hash', 64)->nullable()->index();
            $table->string('permission_status', 24)->default('not_determined');
            $table->boolean('active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('token_updated_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'active', 'permission_status']);
        });

        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->unique()->constrained('system_notifications')->cascadeOnDelete();
            $table->string('status', 24)->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });

        Schema::create('notification_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 140);
            $table->string('message', 500);
            $table->string('category', 48)->default('administrative');
            $table->string('priority', 16)->default('normal');
            $table->json('audience');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->string('idempotency_key', 100)->unique();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('notification_inventory_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->unique()->constrained('product_variants')->cascadeOnDelete();
            $table->string('state', 16)->default('normal');
            $table->unsignedInteger('transition_count')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        // Older production databases already have this DATE column. New
        // installations need the same canonical field without rewriting any
        // existing layaway dates.
        if (! Schema::hasColumn('layaways', 'fecha_vencimiento')) {
            Schema::table('layaways', function (Blueprint $table) {
                $table->date('fecha_vencimiento')->nullable()->index();
            });
        }
        if (! Schema::hasColumn('layaways', 'observaciones')) {
            Schema::table('layaways', function (Blueprint $table) {
                $table->text('observaciones')->nullable();
            });
        }

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('system_notifications')->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('notification_devices')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('token_snapshot');
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('fcm_message_id', 255)->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->timestamps();
            $table->unique(['notification_id', 'device_id']);
            $table->index(['status', 'next_attempt_at']);
        });

        Schema::create('notification_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('notification_deliveries')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 24);
            $table->string('error_code', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['delivery_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_attempts');
        // Never drop fecha_vencimiento or observaciones on rollback: they
        // predate this migration in production and contain business data.
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_dispatches');
        Schema::dropIfExists('notification_announcements');
        Schema::dropIfExists('notification_inventory_states');
        Schema::dropIfExists('notification_devices');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('system_notifications');
    }
};
