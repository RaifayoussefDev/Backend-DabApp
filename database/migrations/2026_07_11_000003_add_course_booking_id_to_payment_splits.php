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
        DB::statement('ALTER TABLE payment_splits MODIFY booking_id BIGINT UNSIGNED NULL');

        Schema::table('payment_splits', function (Blueprint $table) {
            $table->foreignId('course_booking_id')->nullable()->after('booking_id')
                ->constrained('trainer_course_bookings')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_splits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_booking_id');
        });

        DB::statement('ALTER TABLE payment_splits MODIFY booking_id BIGINT UNSIGNED NOT NULL');
    }
};
