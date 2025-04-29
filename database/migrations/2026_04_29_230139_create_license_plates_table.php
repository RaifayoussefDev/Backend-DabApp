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
        Schema::create('license_plates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->onDelete('cascade');
            $table->string('characters')->unique();
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
            $table->foreignId('type_id')->constrained('plate_types')->onDelete('cascade');
            $table->foreignId('color_id')->constrained('plate_colors')->onDelete('cascade');
            $table->unsignedInteger('digits_count');
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_plates');
    }
};
