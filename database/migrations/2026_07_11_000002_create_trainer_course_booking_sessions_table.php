<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_course_booking_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_booking_id')->constrained('trainer_course_bookings')->onDelete('cascade');
            $table->foreignId('trainer_id')->constrained('trainers')->onDelete('cascade');
            $table->foreignId('course_session_id')->nullable()->constrained('trainer_course_sessions')->nullOnDelete();
            $table->unsignedTinyInteger('session_number');
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->string('start_otp', 6)->nullable();
            $table->string('completion_otp', 6)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['course_booking_id', 'session_number'], 'tcbs_booking_session_unique');
            $table->unique(['course_booking_id', 'booking_date'], 'tcbs_booking_date_unique');
            $table->index(['trainer_id', 'booking_date', 'status'], 'tcbs_trainer_date_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_course_booking_sessions');
    }
};
