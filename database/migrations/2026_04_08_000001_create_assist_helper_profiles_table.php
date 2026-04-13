<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helper_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('is_available')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->decimal('rating', 3, 2)->default(0.00);
            $table->integer('total_assists')->default(0);
            $table->integer('service_radius_km')->default(15);
            $table->string('level')->default('standard'); // standard, elite, vanguard
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['is_available', 'latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helper_profiles');
    }
};
