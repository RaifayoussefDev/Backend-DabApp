<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_gallery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('trainers')->onDelete('cascade');
            $table->string('path');
            $table->string('caption')->nullable();
            $table->string('caption_ar')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['trainer_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_gallery');
    }
};
