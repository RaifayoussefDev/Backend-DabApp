<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motorcycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->onDelete('cascade');
            $table->foreignId('brand_id')->constrained('motorcycle_brands')->onDelete('cascade');
            $table->foreignId('model_id')->constrained('motorcycle_models')->onDelete('cascade');
            $table->foreignId('year_id')->constrained('motorcycle_years')->onDelete('cascade');
            $table->foreignId('type_id')->constrained('motorcycle_types')->onDelete('cascade');

            $table->string('engine')->nullable(); // Example: 650cc
            $table->integer('mileage')->nullable(); // in kilometers

            $table->enum('body_condition', ['As New', 'Used', 'Needs some fixes'])->nullable();
            $table->boolean('modified')->default(false);
            $table->boolean('insurance')->default(false);
            $table->enum('general_condition', ['New', 'Used'])->nullable();
            $table->enum('vehicle_care', ['Wakeel', 'USA', 'Europe', 'GCC', 'Customs License'])->nullable();
            $table->enum('transmission', ['Automatic', 'Manual', 'Semi-Automatic'])->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motorcycles');
    }
};
