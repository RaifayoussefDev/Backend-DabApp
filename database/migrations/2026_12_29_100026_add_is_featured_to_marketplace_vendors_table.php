<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_vendors', function (Blueprint $table) {
            // Manually curated by an admin: the "Shops" row on the Marketplace home and the
            // "Featured" badge on the All Shops screen. Mirrors marketplace_brands.is_featured
            // and marketplace_products.is_featured.
            $table->boolean('is_featured')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_vendors', function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });
    }
};
