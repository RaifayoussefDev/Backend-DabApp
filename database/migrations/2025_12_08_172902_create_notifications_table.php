<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type', 50);
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();

            // Linking
            $table->string('related_entity_type', 50)->nullable();
            $table->unsignedBigInteger('related_entity_id')->nullable();
            $table->string('action_url')->nullable();

            // Apparence
            $table->string('image_url')->nullable();
            $table->string('icon')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->string('color', 20)->nullable();
            $table->string('sound', 50)->default('default');

            // Statut
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();

            // Push tracking
            $table->boolean('push_sent')->default(false);
            $table->timestamp('push_sent_at')->nullable();
            $table->boolean('push_delivered')->default(false);
            $table->timestamp('push_delivered_at')->nullable();

            // Admin info
            $table->foreignId('sent_by_admin')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_custom')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'is_read', 'created_at'], 'idx_user_unread');
            $table->index(['user_id', 'type'], 'idx_user_type');
            $table->index('created_at', 'idx_created');
            $table->index('push_sent', 'idx_push_sent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
