<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bike_part_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., Oil, Brake, Exhaust
            $table->timestamps();
        });

        // ✅ Insert 10 default categories
        DB::table('bike_part_categories')->insert([
            ['name' => 'Oil', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Brake', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Exhaust', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Suspension', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Filter', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Battery', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Spark Plug', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Chain', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Clutch', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Fairing', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Tire', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Brake Pad', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Headlight', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Tail Light', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mirror', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Handlebar', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Footpeg', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Windshield', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Seat', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Fender', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Radiator', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Brake Disc', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Gear Lever', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Foot Brake', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Throttle', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Starter Motor', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Fuel Pump', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Ignition Coil', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Voltage Regulator', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Starter Relay', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Wiring Harness', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Brake Line', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Clutch Cable', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Throttle Cable', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Speedometer', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Tachometer', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Odometer', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Fuel Gauge', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Temperature Gauge', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Oil Pressure Gauge', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Gear Indicator', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Neutral Switch', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Kickstand Switch', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Side Stand Switch', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Clutch Switch', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Brake Switch', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Turn Signal Switch', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Horn', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Starter Button', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Kill Switch', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Headlight Switch', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Tail Light Switch', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Hazard Light Switch', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Horn Button', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'High Beam Switch', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Low Beam Switch', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Fog Light Switch', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Engine Kill Switch', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Fuel Tap', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Choke Cable', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Choke Lever', 'created_at' => now(), 'updated_at' => now()],
        ]);        
    }

    public function down(): void
    {
        Schema::dropIfExists('bike_part_categories');
    }
};
