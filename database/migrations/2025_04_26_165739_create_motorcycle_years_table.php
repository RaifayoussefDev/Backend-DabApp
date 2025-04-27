<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motorcycle_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('motorcycle_models')->onDelete('cascade');
            $table->integer('year'); // Example: 2015, 2016
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motorcycle_years');
    }
};
