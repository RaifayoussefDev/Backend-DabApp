<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transport_route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('transport_routes')->onDelete('cascade');
            $table->string('stop_name');
            $table->string('stop_name_ar');
            $table->integer('stop_order')->comment('Order: 1, 2, 3...');
            $table->time('arrival_time');
            $table->time('departure_time');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();

            $table->index(['route_id', 'stop_order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('transport_route_stops');
    }
};