<?php
// database/migrations/2024_12_08_000007_create_notification_actions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->onDelete('cascade');

            $table->string('action_type', 50);
            $table->string('action_label', 100);
            $table->string('action_url')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 20)->nullable();
            $table->integer('order_position')->default(0);

            $table->timestamps();

            $table->index('notification_id', 'idx_action_notification');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_actions');
    }
};
