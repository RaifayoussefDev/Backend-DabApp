<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_product_motorcycles', function (Blueprint $table) {
            // Independent of moto_model_id on purpose: lets a vendor tag "fits all Sport bikes"
            // without picking an exact model. Reuses the existing motorcycle_types table, same
            // one the Home "Bike Type" section already reads from.
            $table->foreignId('bike_type_id')->nullable()->after('moto_brand_id')
                ->constrained('motorcycle_types')->cascadeOnDelete();

            $table->index(['bike_type_id'], 'mp_prod_moto_bike_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_product_motorcycles', function (Blueprint $table) {
            $table->dropIndex('mp_prod_moto_bike_type_idx');
            $table->dropConstrainedForeignId('bike_type_id');
        });
    }
};
