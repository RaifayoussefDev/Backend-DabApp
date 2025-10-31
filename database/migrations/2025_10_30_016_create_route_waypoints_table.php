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
        Schema::create('route_waypoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->onDelete('cascade');
            $table->integer('order_position')->comment('Point order: 1, 2, 3, 4...');
            $table->string('name')->comment('Point name (Point A, Viewpoint, Gas Station)');
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('waypoint_type', 50)->nullable()->comment('start, waypoint, poi, rest_stop, gas_station, viewpoint, end');
            $table->foreignId('poi_id')->nullable()->constrained('points_of_interest')->onDelete('set null')->comment('Optional link to existing POI');
            $table->integer('stop_duration')->nullable()->comment('Suggested stop duration in minutes');
            $table->decimal('distance_from_previous', 8, 2)->nullable()->comment('Distance from previous point (km)');
            $table->integer('elevation')->nullable()->comment('Elevation in meters');
            $table->text('notes')->nullable()->comment('Important notes (attention curves, narrow road, etc.)');
            $table->timestamps();
            
            $table->index(['route_id', 'order_position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_waypoints');
    }
};
