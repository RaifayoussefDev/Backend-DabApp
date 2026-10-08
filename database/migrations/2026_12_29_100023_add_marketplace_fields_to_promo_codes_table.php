<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            // null = DabApp code; set = created by that vendor
            $table->foreignId('vendor_id')->nullable()->after('id')->constrained('marketplace_vendors')->nullOnDelete();
            $table->enum('funded_by', ['platform', 'vendor'])->default('platform')->after('vendor_id');
            // Existing codes discount listing payments; keep them there by default.
            $table->enum('applies_to', ['listings', 'marketplace', 'all'])->default('listings')->after('funded_by');
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn(['vendor_id', 'funded_by', 'applies_to']);
        });
    }
};
