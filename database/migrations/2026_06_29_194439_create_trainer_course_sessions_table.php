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
        Schema::create('trainer_course_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('trainer_courses')->cascadeOnDelete();
            $table->unsignedTinyInteger('session_number');
            $table->string('title')->nullable();
            $table->string('title_ar')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->unsignedTinyInteger('duration_hours')->default(2);
            $table->timestamps();

            $table->unique(['course_id', 'session_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_course_sessions');
    }
};
