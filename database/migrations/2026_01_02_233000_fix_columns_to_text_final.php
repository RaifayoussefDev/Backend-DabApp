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
        Schema::table('motorcycle_details', function (Blueprint $table) {
            $columnsToChange = [
                'ignition',
                'lubrication_system',
                'cooling_system',
                'clutch',
                'driveline',
                'fuel_consumption',
                'greenhouse_gases',
                'emission_details',
                'exhaust_system',
                'frame_type',
                'rake',
                'trail',
                'front_wheel_travel',
                'rear_wheel_travel',
                'front_brakes_diameter',
                'rear_brakes_diameter',
                'wheels',
                'seat',
                'light',
                'comments',
                'color_options',
                'factory_warranty'
            ];

            foreach ($columnsToChange as $column) {
                // We assume columns exist now. Force change to TEXT.
                // We skip the hasColumn check to ensure it tries to run.
                // You might need doctrine/dbal package installed for this to work.
                if (Schema::hasColumn('motorcycle_details', $column)) {
                     $table->text($column)->nullable()->change();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('motorcycle_details', function (Blueprint $table) {
             $columnsToChange = [
                'ignition',
                'lubrication_system',
                'cooling_system',
                'clutch',
                'driveline',
                'fuel_consumption',
                'greenhouse_gases',
                'emission_details',
                'exhaust_system',
                'frame_type',
                'rake',
                'trail',
                'front_wheel_travel',
                'rear_wheel_travel',
                'front_brakes_diameter',
                'rear_brakes_diameter',
                'wheels',
                'seat',
                'light',
                'comments',
                'color_options',
                'factory_warranty'
            ];
             foreach ($columnsToChange as $column) {
                if (Schema::hasColumn('motorcycle_details', $column)) {
                     $table->string($column, 255)->nullable()->change();
                }
            }
        });
    }
};
