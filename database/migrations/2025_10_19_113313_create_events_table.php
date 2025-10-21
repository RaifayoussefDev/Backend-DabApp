<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('short_description')->nullable();
            $table->string('featured_image')->nullable();
            $table->foreignId('category_id')->constrained('event_categories')->onDelete('cascade');
            $table->foreignId('organizer_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('event_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('venue_name')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->onDelete('set null');
            $table->foreignId('country_id')->nullable()->constrained('countries')->onDelete('set null');
            $table->integer('max_participants')->nullable();
            $table->timestamp('registration_deadline')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->tinyInteger('is_free')->default(1);
            $table->string('status', 50)->default('upcoming'); // upcoming, ongoing, completed, cancelled
            $table->tinyInteger('is_featured')->default(0);
            $table->tinyInteger('is_published')->default(1);
            $table->integer('views_count')->default(0);
            $table->integer('participants_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('status');
            $table->index('event_date');
            $table->index('is_featured');
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
