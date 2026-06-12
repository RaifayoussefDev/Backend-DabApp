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
        Schema::create('trainer_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('trainers')->onDelete('cascade');
            $table->unsignedTinyInteger('day_of_week'); // 0=Sun, 6=Sat
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['trainer_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_schedules');
    }
};
