<?php
// database/migrations/2024_12_08_000004_create_notification_groups_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('filters');

            // Stats
            $table->integer('members_count')->default(0);
            $table->timestamp('last_calculated_at')->nullable();

            // Firebase
            $table->string('firebase_topic')->nullable();

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            $table->timestamps();

            $table->index('slug', 'idx_group_slug');
            $table->index('is_active', 'idx_group_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_groups');
    }
};
