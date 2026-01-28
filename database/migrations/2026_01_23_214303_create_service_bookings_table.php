<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('service_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('provider_id')->constrained('service_providers')->onDelete('cascade');
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'rejected'])->default('pending');
            $table->decimal('price', 10, 2);
            $table->enum('payment_status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->onDelete('set null');
            
            // Pour transport/tow service
            $table->text('pickup_location')->nullable();
            $table->text('pickup_location_ar')->nullable();
            $table->decimal('pickup_latitude', 10, 8)->nullable();
            $table->decimal('pickup_longitude', 11, 8)->nullable();
            $table->text('dropoff_location')->nullable();
            $table->text('dropoff_location_ar')->nullable();
            $table->decimal('dropoff_latitude', 10, 8)->nullable();
            $table->decimal('dropoff_longitude', 11, 8)->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            
            $table->text('notes')->nullable();
            $table->text('notes_ar')->nullable();
            $table->text('provider_notes')->nullable();
            $table->text('provider_notes_ar')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('cancellation_reason_ar')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['service_id', 'booking_date']);
            $table->index(['user_id', 'status']);
            $table->index(['provider_id', 'status']);
            $table->index('payment_status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_bookings');
    }
};