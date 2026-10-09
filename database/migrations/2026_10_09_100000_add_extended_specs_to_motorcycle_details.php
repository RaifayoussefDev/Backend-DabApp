<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns introduced by the extended motorcycle spreadsheet (URL / engine /
 * transmission / body / chassis / wheels / other / "how it rides" / writing).
 */
return new class extends Migration
{
    private const STRING_COLUMNS = [
        'title',
        'spark_plugs',
        'battery_capacity',
        'motor_type',
        'electric_range',
        'charge_time',
        'sprockets',
        'chain_size',
        'acceleration_0_60',
        'reserve_fuel_capacity',
        'oil_capacity',
        'fork_tube_size',
        'brake_fluid',
        'abs_system',
        'tire_pressure_front',
        'tire_pressure_rear',
        'ride_offroad',
        'ride_cornering',
        'ride_city',
        'ride_daily',
        'ride_touring',
        'ride_two_up',
        'ride_track',
        'verified',
    ];

    private const TEXT_COLUMNS = [
        'source_url',
        'image_url',
        'image_url_thumbs',
        'modifications',
        'best_for',
        'built_for',
        'not_ideal_for',
        'summary',
    ];

    public function up(): void
    {
        Schema::table('motorcycle_details', function (Blueprint $table) {
            foreach (self::STRING_COLUMNS as $column) {
                if (!Schema::hasColumn('motorcycle_details', $column)) {
                    $table->string($column)->nullable();
                }
            }
            foreach (self::TEXT_COLUMNS as $column) {
                if (!Schema::hasColumn('motorcycle_details', $column)) {
                    $table->text($column)->nullable();
                }
            }
            if (!Schema::hasColumn('motorcycle_details', 'writing_all')) {
                $table->mediumText('writing_all')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('motorcycle_details', function (Blueprint $table) {
            $table->dropColumn(array_merge(self::STRING_COLUMNS, self::TEXT_COLUMNS, ['writing_all']));
        });
    }
};
