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
            // Performance
            $table->string('acceleration_60_140')->nullable();
            
            // Engine Specs
            $table->string('fuel_control')->nullable();
            $table->string('ignition')->nullable();
            $table->string('lubrication_system')->nullable();
            $table->string('cooling_system')->nullable();
            
            // Transmission
            $table->string('clutch')->nullable();
            $table->string('driveline')->nullable();
            
            // Emissions & Efficiency
            $table->string('fuel_consumption')->nullable();
            $table->string('greenhouse_gases')->nullable();
            $table->string('emission_details')->nullable();
            $table->string('exhaust_system')->nullable();
            
            // Chassis & Suspension
            $table->string('frame_type')->nullable();
            $table->string('rake')->nullable();
            $table->string('trail')->nullable();
            $table->string('front_wheel_travel')->nullable();
            $table->string('rear_wheel_travel')->nullable();
            
            // Brakes & Wheels
            $table->string('front_brakes_diameter')->nullable(); // stored as string to allow units or ranges
            $table->string('rear_brakes_diameter')->nullable();
            $table->string('wheels')->nullable();
            $table->string('seat')->nullable();
            
            // Weight & Dimensions Details
            $table->string('power_weight_ratio')->nullable();
            $table->string('front_weight_percentage')->nullable();
            $table->string('rear_weight_percentage')->nullable();
            $table->string('alternate_seat_height')->nullable();
            $table->string('carrying_capacity')->nullable();
            
            // Equiment & Others
            $table->string('color_options')->nullable();
            $table->string('starter')->nullable();
            $table->string('instruments')->nullable();
            $table->string('electrical')->nullable();
            $table->string('light')->nullable();
            $table->string('factory_warranty')->nullable();
            $table->text('comments')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('motorcycle_details', function (Blueprint $table) {
            $table->dropColumn([
                'acceleration_60_140',
                'fuel_control',
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
                'power_weight_ratio',
                'front_weight_percentage',
                'rear_weight_percentage',
                'alternate_seat_height',
                'carrying_capacity',
                'color_options',
                'starter',
                'instruments',
                'electrical',
                'light',
                'factory_warranty',
                'comments'
            ]);
        });
    }
};
