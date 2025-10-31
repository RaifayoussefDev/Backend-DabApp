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
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('Ex: Belle route de l\'Atlas');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('featured_image')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->comment('Route creator');
            $table->foreignId('category_id')->nullable()->constrained('route_categories')->onDelete('set null');
            $table->string('difficulty', 50)->nullable()->comment('easy, moderate, difficult, expert');
            $table->decimal('total_distance', 8, 2)->nullable()->comment('Total distance in km');
            $table->string('estimated_duration', 50)->nullable()->comment('Ex: 3-4 hours');
            $table->string('best_season')->nullable()->comment('Ex: Spring, Summer, All year');
            $table->string('road_condition', 50)->nullable()->comment('excellent, good, fair, poor');
            $table->boolean('is_verified')->default(false)->comment('Verified by admin');
            $table->boolean('is_featured')->default(false);
            $table->integer('views_count')->default(0);
            $table->integer('likes_count')->default(0);
            $table->integer('completed_count')->default(0)->comment('Times completed');
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
