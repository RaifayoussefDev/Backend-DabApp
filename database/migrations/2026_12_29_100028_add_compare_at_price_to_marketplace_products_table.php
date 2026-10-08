<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table) {
            // Strikethrough original price for a simple product (one with no rows in marketplace_product_variants).
            $table->decimal('compare_at_price', 12, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table) {
            $table->dropColumn('compare_at_price');
        });
    }
};
