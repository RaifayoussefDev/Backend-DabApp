<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assist_ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id')->unique();
            $table->foreignId('rater_id')->constrained('users');
            $table->foreignId('rated_id')->constrained('users');
            $table->tinyInteger('stars'); // 1-5
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('request_id')
                ->references('id')->on('assistance_requests')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assist_ratings');
    }
};
