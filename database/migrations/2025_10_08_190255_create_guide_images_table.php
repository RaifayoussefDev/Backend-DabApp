<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_id')->constrained('guides')->onDelete('cascade');
            $table->string('image_url');
            $table->text('caption')->nullable();
            $table->integer('order_position')->default(0);
            $table->timestamps();

            $table->index(['guide_id', 'order_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_images');
    }
};

