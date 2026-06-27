<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('trainers')->onDelete('cascade');
            // Optionally linked to an approved level
            $table->foreignId('level_id')->nullable()->constrained('trainer_levels')->nullOnDelete();
            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            // Session parameters
            $table->unsignedTinyInteger('hours_per_session');           // 1-8
            $table->unsignedSmallInteger('total_sessions');             // 1, 5, 10 …
            $table->date('session_date')->nullable();                   // optional fixed date
            $table->time('session_time')->nullable();                   // optional fixed time
            // Pricing
            $table->decimal('original_price', 10, 2);
            $table->decimal('promo_price', 10, 2)->nullable();
            // Location
            $table->foreignId('location_id')->nullable()->constrained('trainer_locations')->nullOnDelete();
            $table->boolean('can_travel')->default(false);
            // Status
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_courses');
    }
};
