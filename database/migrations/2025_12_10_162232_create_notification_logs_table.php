<?php
// database/migrations/2024_12_08_000006_create_notification_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('notification_id')->nullable()->constrained('notifications')->onDelete('cascade');
            $table->foreignId('campaign_id')->nullable()->constrained('notification_campaigns')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->enum('channel', ['push', 'in_app', 'email', 'sms']);
            $table->enum('status', ['queued', 'sending', 'sent', 'delivered', 'failed', 'clicked'])->default('queued');

            // Firebase details
            $table->text('fcm_token')->nullable();
            $table->string('fcm_message_id')->nullable();
            $table->json('fcm_response')->nullable();

            // Error
            $table->text('error_message')->nullable();
            $table->string('error_code')->nullable();
            $table->integer('retry_count')->default(0);

            // Device
            $table->enum('device_type', ['ios', 'android', 'web', 'huawei'])->nullable();
            $table->string('device_id')->nullable();

            // Timestamps
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->index('notification_id', 'idx_log_notification');
            $table->index('campaign_id', 'idx_log_campaign');
            $table->index(['user_id', 'status'], 'idx_log_user_status');
            $table->index('status', 'idx_log_status');
            $table->index('sent_at', 'idx_log_sent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
