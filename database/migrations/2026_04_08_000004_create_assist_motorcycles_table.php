<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assist_motorcycles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained('motorcycle_brands');
            $table->foreignId('model_id')->constrained('motorcycle_models');
            $table->foreignId('year_id')->constrained('motorcycle_years');
            $table->string('color');
            $table->string('plate_number');
            $table->string('plate_country')->default('SA'); // SA, AE, KW, BH, QA, OM
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assist_motorcycles');
    }
};
