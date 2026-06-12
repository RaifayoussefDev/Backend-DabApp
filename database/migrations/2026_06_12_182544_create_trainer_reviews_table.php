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
        Schema::create('trainer_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('trainer_bookings')->onDelete('cascade');
            $table->foreignId('trainer_id')->constrained('trainers')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedTinyInteger('rating'); // 1–5
            $table->text('comment')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamps();

            $table->unique('booking_id'); // one review per booking
            $table->index(['trainer_id', 'is_approved']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_reviews');
    }
};
