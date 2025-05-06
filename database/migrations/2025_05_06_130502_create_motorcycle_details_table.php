<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('motorcycle_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('year_id')->constrained('motorcycle_years')->onDelete('cascade')->unique();

            // Spécifications du moteur
            $table->float('displacement')->nullable(); // en cc
            $table->string('engine_type')->nullable();
            $table->string('engine_details')->nullable();
            $table->float('power')->nullable(); // en HP
            $table->float('torque')->nullable(); // en Nm
            $table->float('top_speed')->nullable(); // en km/h
            $table->string('quarter_mile')->nullable();
            $table->string('acceleration_0_100')->nullable();
            $table->integer('max_rpm')->nullable();
            $table->float('compression')->nullable();
            $table->string('bore_stroke')->nullable();
            $table->integer('valves_per_cylinder')->nullable();
            $table->string('fuel_system')->nullable();

            // Transmission
            $table->string('gearbox')->nullable();
            $table->string('transmission_type')->nullable();

            // Suspension et pneus
            $table->string('front_suspension')->nullable();
            $table->string('rear_suspension')->nullable();
            $table->string('front_tire')->nullable();
            $table->string('rear_tire')->nullable();

            // Freins
            $table->string('front_brakes')->nullable();
            $table->string('rear_brakes')->nullable();

            // Dimensions et poids
            $table->float('dry_weight')->nullable(); // en kg
            $table->float('wet_weight')->nullable(); // en kg
            $table->float('seat_height')->nullable(); // en mm
            $table->float('overall_length')->nullable(); // en mm
            $table->float('overall_width')->nullable(); // en mm
            $table->float('overall_height')->nullable(); // en mm
            $table->float('ground_clearance')->nullable(); // en mm
            $table->float('wheelbase')->nullable(); // en mm
            $table->float('fuel_capacity')->nullable(); // en litres

            // Autres informations
            $table->float('rating')->nullable();
            $table->float('price')->nullable(); // MSRP

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('motorcycle_details');
    }
};
