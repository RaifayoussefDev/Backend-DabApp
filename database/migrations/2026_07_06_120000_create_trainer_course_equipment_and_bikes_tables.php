<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_course_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('trainer_courses')->cascadeOnDelete();
            $table->foreignId('trainer_equipment_id')->constrained('trainer_equipment')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'trainer_equipment_id'], 'trainer_course_equipment_unique');
        });

        Schema::create('trainer_course_training_bikes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('trainer_courses')->cascadeOnDelete();
            $table->foreignId('trainer_training_bike_id')->constrained('trainer_training_bikes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'trainer_training_bike_id'], 'trainer_course_training_bikes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_course_training_bikes');
        Schema::dropIfExists('trainer_course_equipment');
    }
};
