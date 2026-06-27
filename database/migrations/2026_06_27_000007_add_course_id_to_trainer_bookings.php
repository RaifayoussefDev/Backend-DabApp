<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainer_bookings', function (Blueprint $table) {
            $table->foreignId('course_id')
                  ->nullable()
                  ->after('location_id')
                  ->constrained('trainer_courses')
                  ->nullOnDelete();

            $table->unsignedBigInteger('level_id')->nullable()->after('course_id');
            $table->foreign('level_id')->references('id')->on('trainer_levels')->nullOnDelete();

            $table->decimal('price_per_hour', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('trainer_bookings', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropForeign(['level_id']);
            $table->dropColumn(['course_id', 'level_id', 'price_per_hour']);
        });
    }
};
