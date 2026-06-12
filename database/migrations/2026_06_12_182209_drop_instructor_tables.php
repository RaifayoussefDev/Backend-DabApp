<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop FK-dependent table first
        Schema::dropIfExists('instructor_locations');
        Schema::dropIfExists('riding_instructors');
    }

    public function down(): void
    {
        Schema::create('riding_instructors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('service_providers')->onDelete('cascade');
            $table->string('instructor_name');
            $table->string('instructor_name_ar')->nullable();
            $table->text('bio')->nullable();
            $table->text('bio_ar')->nullable();
            $table->string('photo')->nullable();
            $table->json('certifications')->nullable();
            $table->integer('experience_years')->default(0);
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->integer('total_sessions')->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('instructor_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('riding_instructors')->onDelete('cascade');
            $table->string('location_name');
            $table->string('location_name_ar')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->onDelete('set null');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });
    }
};
