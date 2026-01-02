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
        Schema::table('motorcycle_details', function (Blueprint $table) {
            $table->text('engine_details')->nullable()->change();
            $table->text('fuel_system')->nullable()->change();
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
                // Check if column exists before changing, or just change it.
                // Using change() requires doctrine/dbal.
                // Assuming it's installed or we are using newer Laravel/MariaDB.
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
            $table->string('rear_tire')->nullable()->change();
            $table->string('front_brakes')->nullable()->change();
            $table->string('rear_brakes')->nullable()->change();
        });
    }
};
