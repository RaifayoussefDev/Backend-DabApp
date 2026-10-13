<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_cart_items', function (Blueprint $table) {
            // Null = the product has no variants (plain price/stock on the product itself).
            // Added after marketplace_product_variants existed - the original W1 schema predates variants.
            $table->foreignId('variant_id')->nullable()->after('product_id')
                ->constrained('marketplace_product_variants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_cart_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variant_id');
        });
    }
};
