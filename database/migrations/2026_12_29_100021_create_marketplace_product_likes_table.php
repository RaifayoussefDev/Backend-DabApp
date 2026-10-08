<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_product_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('marketplace_products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'user_id'], 'mp_likes_product_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_product_likes');
    }
};
