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
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('base_price', 10, 2)->nullable()->after('price');
            $table->foreignId('origin_city_id')->nullable()->after('base_price')->constrained('cities')->onDelete('set null');
            $table->foreignId('destination_city_id')->nullable()->after('origin_city_id')->constrained('cities')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['origin_city_id']);
            $table->dropForeign(['destination_city_id']);
            $table->dropColumn(['base_price', 'origin_city_id', 'destination_city_id']);
        });
    }
};
