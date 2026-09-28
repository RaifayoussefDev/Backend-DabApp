<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_product_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('marketplace_products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // who asked
            $table->text('question');
            $table->text('answer')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_approved')->default(true); // admin moderation
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'is_approved'], 'mp_questions_product_approved_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_product_questions');
    }
};
