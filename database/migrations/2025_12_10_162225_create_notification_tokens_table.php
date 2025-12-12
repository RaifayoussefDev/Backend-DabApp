<?php
// database/migrations/2024_12_08_000002_create_notification_tokens_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('device_type', ['ios', 'android', 'web', 'huawei']);
            $table->text('fcm_token');

            // Device info
            $table->string('device_name')->nullable();
            $table->string('device_id')->nullable();
            $table->string('device_model')->nullable();
            $table->string('os_version')->nullable();
            $table->string('app_version')->nullable();

            // Statut
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->integer('failed_attempts')->default(0);
            $table->timestamp('last_failed_at')->nullable();

            // Firebase topics
            $table->json('subscribed_topics')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'is_active'], 'idx_user_active');
            $table->index('fcm_token', 'idx_fcm_token');
            $table->index(['device_type', 'is_active'], 'idx_device_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_tokens');
    }
};
