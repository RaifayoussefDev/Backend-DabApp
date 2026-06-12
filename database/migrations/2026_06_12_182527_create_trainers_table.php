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
        Schema::create('trainers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('bio')->nullable();
            $table->text('bio_ar')->nullable();
            $table->string('photo')->nullable();
            $table->enum('specialty', ['coaching', 'competition', 'off-road', 'street', 'custom'])->default('coaching');
            $table->json('certifications')->nullable();
            $table->unsignedInteger('experience_years')->default(0);
            $table->decimal('price_per_hour', 10, 2)->default(0);
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->unsignedInteger('total_sessions')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->boolean('is_available')->default(true);
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainers');
    }
};
