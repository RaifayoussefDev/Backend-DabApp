<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push tokens for guest (not-logged-in) mobile devices. Kept separate from
 * `notification_tokens` on purpose: that table's `user_id` is a hard FK and a
 * guest has no user. A guest row is keyed by the app's stable per-install
 * `device_id`. When the guest later registers/logs in, the row is deactivated
 * and `converted_user_id` records who it became.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_notification_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->unique();
            $table->text('fcm_token');
            $table->enum('device_type', ['ios', 'android', 'web', 'huawei']);

            // Device info
            $table->string('device_name')->nullable();
            $table->string('device_model')->nullable();
            $table->string('os_version')->nullable();
            $table->string('app_version')->nullable();
            $table->string('locale', 10)->nullable();

            // Location (sent by the app; no server-side geo-IP)
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->integer('failed_attempts')->default(0);
            $table->timestamp('last_failed_at')->nullable();

            // Set once the guest registers / logs in on this device
            $table->unsignedBigInteger('converted_user_id')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'device_type'], 'idx_guest_active_device');
            $table->index(['is_active', 'country_id'], 'idx_guest_active_country');
            $table->index('last_active_at', 'idx_guest_last_active');
            $table->index('fcm_token', 'idx_guest_fcm_token');

            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
            $table->foreign('city_id')->references('id')->on('cities')->nullOnDelete();
            $table->foreign('converted_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_notification_tokens');
    }
};
