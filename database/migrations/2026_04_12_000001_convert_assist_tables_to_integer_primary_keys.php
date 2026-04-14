<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert all Assist module tables from UUID primary keys to auto-increment integers.
     * Strategy: drop + recreate (all tables are empty / truncated first).
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Drop in reverse-dependency order
        Schema::dropIfExists('assist_notifications');
        Schema::dropIfExists('assist_ratings');
        Schema::dropIfExists('request_photos');
        Schema::dropIfExists('assistance_requests');
        Schema::dropIfExists('helper_expertises');
        Schema::dropIfExists('assist_motorcycles');
        Schema::dropIfExists('expertise_types');
        Schema::dropIfExists('helper_profiles');

        // ── Recreate with BIGINT AUTO_INCREMENT PKs ──────────────────────────

        Schema::create('helper_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('is_available')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->decimal('rating', 3, 2)->default(0.00);
            $table->integer('total_assists')->default(0);
            $table->integer('service_radius_km')->default(15);
            $table->string('level')->default('standard');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['is_available', 'latitude', 'longitude']);
        });

        Schema::create('expertise_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('icon');
            $table->timestamps();
        });

        Schema::create('helper_expertises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('helper_profile_id');
            $table->unsignedBigInteger('expertise_type_id');
            $table->timestamps();

            $table->foreign('helper_profile_id')
                ->references('id')->on('helper_profiles')
                ->cascadeOnDelete();

            $table->foreign('expertise_type_id')
                ->references('id')->on('expertise_types')
                ->cascadeOnDelete();

            $table->unique(['helper_profile_id', 'expertise_type_id']);
        });

        Schema::create('assist_motorcycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained('motorcycle_brands');
            $table->foreignId('model_id')->constrained('motorcycle_models');
            $table->foreignId('year_id')->constrained('motorcycle_years');
            $table->string('color');
            $table->string('plate_number');
            $table->string('plate_country')->default('SA');
            $table->timestamps();
        });

        Schema::create('assistance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seeker_id')->constrained('users');
            $table->foreignId('helper_id')->nullable()->constrained('users');
            $table->unsignedBigInteger('motorcycle_id')->nullable();
            $table->unsignedBigInteger('expertise_type_id');
            $table->enum('status', ['pending', 'accepted', 'en_route', 'arrived', 'completed', 'cancelled'])
                  ->default('pending');
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('location_label');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('motorcycle_id')
                ->references('id')->on('assist_motorcycles')
                ->nullOnDelete();

            $table->foreign('expertise_type_id')
                ->references('id')->on('expertise_types');

            $table->index(['status', 'seeker_id', 'helper_id']);
        });

        Schema::create('request_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->string('path');
            $table->timestamps();

            $table->foreign('request_id')
                ->references('id')->on('assistance_requests')
                ->cascadeOnDelete();
        });

        Schema::create('assist_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id')->unique();
            $table->foreignId('rater_id')->constrained('users');
            $table->foreignId('rated_id')->constrained('users');
            $table->tinyInteger('stars');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('request_id')
                ->references('id')->on('assistance_requests')
                ->cascadeOnDelete();
        });

        Schema::create('assist_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('request_id')->nullable();
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->foreign('request_id')
                ->references('id')->on('assistance_requests')
                ->nullOnDelete();
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Reverse: restore UUID primary keys.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        Schema::dropIfExists('assist_notifications');
        Schema::dropIfExists('assist_ratings');
        Schema::dropIfExists('request_photos');
        Schema::dropIfExists('assistance_requests');
        Schema::dropIfExists('helper_expertises');
        Schema::dropIfExists('assist_motorcycles');
        Schema::dropIfExists('expertise_types');
        Schema::dropIfExists('helper_profiles');

        Schema::create('helper_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('is_available')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->decimal('rating', 3, 2)->default(0.00);
            $table->integer('total_assists')->default(0);
            $table->integer('service_radius_km')->default(15);
            $table->string('level')->default('standard');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['is_available', 'latitude', 'longitude']);
        });

        Schema::create('expertise_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('icon');
            $table->timestamps();
        });

        Schema::create('helper_expertises', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('helper_profile_id');
            $table->uuid('expertise_type_id');
            $table->timestamps();
            $table->foreign('helper_profile_id')->references('id')->on('helper_profiles')->cascadeOnDelete();
            $table->foreign('expertise_type_id')->references('id')->on('expertise_types')->cascadeOnDelete();
            $table->unique(['helper_profile_id', 'expertise_type_id']);
        });

        Schema::create('assist_motorcycles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained('motorcycle_brands');
            $table->foreignId('model_id')->constrained('motorcycle_models');
            $table->foreignId('year_id')->constrained('motorcycle_years');
            $table->string('color');
            $table->string('plate_number');
            $table->string('plate_country')->default('SA');
            $table->timestamps();
        });

        Schema::create('assistance_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('seeker_id')->constrained('users');
            $table->foreignId('helper_id')->nullable()->constrained('users');
            $table->uuid('motorcycle_id')->nullable();
            $table->uuid('expertise_type_id');
            $table->enum('status', ['pending', 'accepted', 'en_route', 'arrived', 'completed', 'cancelled'])->default('pending');
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('location_label');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('motorcycle_id')->references('id')->on('assist_motorcycles')->nullOnDelete();
            $table->foreign('expertise_type_id')->references('id')->on('expertise_types');
            $table->index(['status', 'seeker_id', 'helper_id']);
        });

        Schema::create('request_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id');
            $table->string('path');
            $table->timestamps();
            $table->foreign('request_id')->references('id')->on('assistance_requests')->cascadeOnDelete();
        });

        Schema::create('assist_ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id')->unique();
            $table->foreignId('rater_id')->constrained('users');
            $table->foreignId('rated_id')->constrained('users');
            $table->tinyInteger('stars');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->foreign('request_id')->references('id')->on('assistance_requests')->cascadeOnDelete();
        });

        Schema::create('assist_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('request_id')->nullable();
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
            $table->foreign('request_id')->references('id')->on('assistance_requests')->nullOnDelete();
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
