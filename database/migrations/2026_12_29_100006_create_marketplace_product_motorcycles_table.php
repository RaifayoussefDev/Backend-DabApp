<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bike compatibility. A universal part = one row with is_universal = true and no moto ids.
        Schema::create('marketplace_product_motorcycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('marketplace_products')->cascadeOnDelete();
            $table->foreignId('moto_brand_id')->nullable()->constrained('motorcycle_brands')->cascadeOnDelete();
            $table->foreignId('moto_model_id')->nullable()->constrained('motorcycle_models')->cascadeOnDelete();
            $table->foreignId('moto_year_id')->nullable()->constrained('motorcycle_years')->cascadeOnDelete();
            $table->boolean('is_universal')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'is_universal'], 'mp_prod_moto_universal_idx');
            $table->index(['moto_brand_id', 'moto_model_id', 'moto_year_id'], 'mp_prod_moto_bike_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_product_motorcycles');
    }
};
