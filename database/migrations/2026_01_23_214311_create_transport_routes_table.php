<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transport_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('service_providers')->onDelete('cascade');
            $table->foreignId('booking_id')->nullable()->constrained('service_bookings')->onDelete('set null');
            $table->date('route_date');
            $table->string('departure_point');
            $table->string('departure_point_ar');
            $table->string('arrival_point');
            $table->string('arrival_point_ar');
            $table->time('departure_time');
            $table->time('arrival_time');
            $table->integer('available_slots');
            $table->integer('booked_slots')->default(0);
            $table->decimal('price_per_slot', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['provider_id', 'route_date']);
            $table->index(['route_date', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('transport_routes');
    }
};