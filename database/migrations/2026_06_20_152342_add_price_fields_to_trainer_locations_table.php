<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trainer_locations', function (Blueprint $table) {
            $table->decimal('price_per_hour',    10, 2)->nullable()->after('is_available');
            $table->decimal('price_per_mission', 10, 2)->nullable()->after('price_per_hour');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainer_locations', function (Blueprint $table) {
            $table->dropColumn(['price_per_hour', 'price_per_mission']);
        });
    }
};
