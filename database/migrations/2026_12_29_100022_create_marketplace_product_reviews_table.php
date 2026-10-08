<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('marketplace_products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('marketplace_order_items')->nullOnDelete();
            $table->unsignedTinyInteger('rating'); // 1..5, validated in code
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('verified_purchase')->default(false);
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'is_approved'], 'mp_reviews_product_approved_idx');
            $table->index(['user_id', 'product_id'], 'mp_reviews_user_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_product_reviews');
    }
};
