<?php
// database/migrations/2024_12_08_000008_create_notification_templates_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();

            $table->string('type', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Templates
            $table->string('title_template');
            $table->text('message_template');
            $table->text('email_template')->nullable();
            $table->string('sms_template', 160)->nullable();

            // Variables
            $table->json('variables')->nullable();

            // Apparence
            $table->string('icon')->nullable();
            $table->string('color', 20)->nullable();
            $table->string('sound', 50)->default('default');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('type', 'idx_template_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
