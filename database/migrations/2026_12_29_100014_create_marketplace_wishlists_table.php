<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('marketplace_products')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'product_id'], 'mp_wishlists_user_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_wishlists');
    }
};
