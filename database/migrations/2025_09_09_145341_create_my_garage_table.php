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
        Schema::create('my_garage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('year_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('picture')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('brand_id')->references('id')->on('motorcycle_brands');
            $table->foreign('model_id')->references('id')->on('motorcycle_models');
            $table->foreign('year_id')->references('id')->on('motorcycle_years');

            // Indexes
            $table->index('user_id');
            $table->index(['brand_id', 'model_id', 'year_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('my_garage');
    }
};
