<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_batches', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('filters');
        });

        // No doctrine/dbal in this project, so enum changes go through a raw MODIFY.
        DB::statement("ALTER TABLE notification_batches MODIFY status ENUM('scheduled', 'pending', 'processing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE notification_batches MODIFY status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending'");

        Schema::table('notification_batches', function (Blueprint $table) {
            $table->dropColumn('scheduled_at');
        });
    }
};
