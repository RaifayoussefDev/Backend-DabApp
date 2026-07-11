<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No doctrine/dbal installed — use a raw MODIFY instead of ->nullable()->change().
        DB::statement('ALTER TABLE trainer_reviews MODIFY booking_id BIGINT UNSIGNED NULL');

        Schema::table('trainer_reviews', function (Blueprint $table) {
            $table->foreignId('course_booking_id')->nullable()->after('booking_id')
                ->constrained('trainer_course_bookings')->cascadeOnDelete();
            $table->unique('course_booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('trainer_reviews', function (Blueprint $table) {
            $table->dropUnique(['course_booking_id']);
            $table->dropConstrainedForeignId('course_booking_id');
        });

        DB::statement('ALTER TABLE trainer_reviews MODIFY booking_id BIGINT UNSIGNED NOT NULL');
    }
};
