<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('service_providers')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('service_categories')->onDelete('cascade');
            $table->string('name');
            $table->string('name_ar');
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->enum('price_type', ['fixed', 'per_hour', 'per_km', 'custom'])->default('fixed');
            $table->string('currency', 3)->default('SAR');
            $table->integer('duration_minutes')->nullable()->comment('Estimated duration in minutes');
            $table->boolean('is_available')->default(true);
            $table->boolean('requires_booking')->default(true);
            $table->integer('max_capacity')->nullable()->comment('Max simultaneous customers');
            $table->string('image')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'is_available']);
            $table->index(['category_id', 'is_available']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('services');
    }
};