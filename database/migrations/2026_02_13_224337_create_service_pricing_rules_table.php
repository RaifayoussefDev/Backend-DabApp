<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['per_km', 'fixed_route'])->default('fixed_route');
            $table->decimal('price', 10, 2);
            $table->foreignId('origin_city_id')->nullable()->constrained('cities')->onDelete('cascade');
            $table->foreignId('destination_city_id')->nullable()->constrained('cities')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_pricing_rules');
    }
};
