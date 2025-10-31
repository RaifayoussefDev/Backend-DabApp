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
        Schema::create('poi_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Garage/Maintenance, Dealer/Seller, Spare Parts Shop, Parking, Gas Station');
            $table->string('icon')->nullable()->comment('Icon name to display on map');
            $table->string('color', 50)->nullable()->comment('Marker color on map');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poi_types');
    }
};
