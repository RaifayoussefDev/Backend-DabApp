<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same shape as guide_comments
        Schema::create('marketplace_product_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('marketplace_products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('marketplace_product_comments')->cascadeOnDelete();
            $table->text('content');
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'parent_id'], 'mp_comments_product_parent_idx');
            $table->index('is_approved');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_product_comments');
    }
};
