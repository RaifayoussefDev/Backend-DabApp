<?php
// database/migrations/2024_12_08_000005_create_notification_campaigns_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_campaigns', function (Blueprint $table) {
            $table->id();

            // Info
            $table->string('name');
            $table->enum('type', ['individual', 'group', 'broadcast']);

            // Contenu
            $table->string('title');
            $table->text('message');
            $table->string('image_url')->nullable();
            $table->string('action_url')->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 20)->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            // Cible
            $table->foreignId('target_user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('target_group_id')->nullable()->constrained('notification_groups')->onDelete('set null');
            $table->json('custom_filters')->nullable();

            // Planification
            $table->enum('schedule_type', ['immediate', 'scheduled'])->default('immediate');
            $table->timestamp('scheduled_at')->nullable();

            // Statut
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'failed', 'cancelled'])->default('draft');

            // Stats
            $table->integer('total_recipients')->default(0);
            $table->integer('push_sent_count')->default(0);
            $table->integer('push_delivered_count')->default(0);
            $table->integer('push_failed_count')->default(0);
            $table->integer('read_count')->default(0);
            $table->integer('clicked_count')->default(0);

            // Timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            $table->timestamps();

            $table->index('status', 'idx_campaign_status');
            $table->index('scheduled_at', 'idx_campaign_scheduled');
            $table->index('created_by', 'idx_campaign_creator');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_campaigns');
    }
};
