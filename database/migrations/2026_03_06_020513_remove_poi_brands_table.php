<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('poi_brands');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('poi_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poi_id')->constrained('points_of_interests')->onDelete('cascade');
            $table->foreignId('brand_id')->constrained('motorcycle_brands')->onDelete('cascade');
            $table->timestamps();
        });
    }
};
