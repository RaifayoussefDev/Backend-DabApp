<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trainer_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('trainers')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('trainer_comments')->onDelete('cascade');
            $table->text('content');
            $table->boolean('is_approved')->default(false);
            $table->timestamps();

            $table->index(['trainer_id', 'is_approved', 'parent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_comments');
    }
};
