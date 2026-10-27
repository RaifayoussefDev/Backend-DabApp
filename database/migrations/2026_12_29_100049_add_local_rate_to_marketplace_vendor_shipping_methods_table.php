<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_vendor_shipping_methods', function (Blueprint $table) {
            // Null = no discount, every order uses flat_rate. Set = applied instead of flat_rate
            // when the order ships to the vendor's own city (Vendor.city_id), flat_rate stays the
            // "rest of the country" rate — a simple 2-tier model, not a full per-city rate table.
            $table->decimal('local_rate', 10, 2)->nullable()->after('flat_rate');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_vendor_shipping_methods', function (Blueprint $table) {
            $table->dropColumn('local_rate');
        });
    }
};
