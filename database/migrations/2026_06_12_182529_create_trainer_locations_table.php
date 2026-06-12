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
        Schema::create('trainer_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('trainers')->onDelete('cascade');
            $table->string('location_name');
            $table->string('location_name_ar')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->onDelete('set null');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_locations');
    }
};
