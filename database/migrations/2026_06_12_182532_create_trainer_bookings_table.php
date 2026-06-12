<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trainer_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('trainers')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('location_id')->constrained('trainer_locations')->onDelete('cascade');
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedTinyInteger('duration_hours')->default(1);
            $table->enum('session_type', ['beginner', 'intermediate', 'advanced', 'custom'])->default('beginner');
            $table->enum('status', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'rejected'])->default('pending');
            $table->decimal('price', 10, 2);
            $table->unsignedBigInteger('payment_id')->nullable(); // FK added after trainer_payments is created
            $table->enum('payment_status', ['pending', 'paid', 'refunded', 'failed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['trainer_id', 'booking_date', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_bookings');
    }
};
