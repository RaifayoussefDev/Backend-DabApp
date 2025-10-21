<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('rating'); // 1-5
            $table->text('comment')->nullable();
            $table->tinyInteger('is_approved')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'is_approved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reviews');
    }
};
