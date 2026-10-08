<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('marketplace_products')->cascadeOnDelete();
            $table->string('image_path');
            $table->boolean('is_cover')->default(false);
            $table->unsignedInteger('order_position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'order_position'], 'mp_product_images_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_product_images');
    }
};
