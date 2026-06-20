<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainer_locations', function (Blueprint $table) {
            if (!Schema::hasColumn('trainer_locations', 'price_per_hour')) {
                $table->decimal('price_per_hour', 10, 2)->nullable()->after('is_available');
            }
            if (!Schema::hasColumn('trainer_locations', 'price_per_mission')) {
                $table->decimal('price_per_mission', 10, 2)->nullable()->after('price_per_hour');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trainer_locations', function (Blueprint $table) {
            $table->dropColumn(array_filter(['price_per_hour', 'price_per_mission'], function ($col) {
                return Schema::hasColumn('trainer_locations', $col);
            }));
        });
    }
};
