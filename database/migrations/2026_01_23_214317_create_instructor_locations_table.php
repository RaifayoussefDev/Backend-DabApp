<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('instructor_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('riding_instructors')->onDelete('cascade');
            $table->string('location_name');
            $table->string('location_name_ar');
            $table->foreignId('city_id')->constrained('cities')->onDelete('cascade');
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['instructor_id', 'is_available']);
            $table->index('city_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('instructor_locations');
    }
};