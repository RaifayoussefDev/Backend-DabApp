<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['text', 'image', 'text_image', 'gallery', 'video']);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->enum('image_position', ['top', 'right', 'left', 'bottom'])->default('top');
            $table->json('media')->nullable();
            $table->integer('order_position')->default(0);
            $table->timestamps();

            $table->index(['guide_id', 'order_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_sections');
    }
};
