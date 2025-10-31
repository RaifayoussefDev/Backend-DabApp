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
        Schema::create('poi_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poi_id')->constrained('points_of_interest')->onDelete('cascade');
            $table->string('image_url');
            $table->boolean('is_main')->default(false);
            $table->integer('order_position')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poi_images');
    }
};
