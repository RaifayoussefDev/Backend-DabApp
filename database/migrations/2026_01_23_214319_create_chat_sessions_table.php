<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('service_bookings')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('provider_id')->constrained('service_providers')->onDelete('cascade');
            $table->decimal('session_price', 10, 2);
            $table->enum('session_status', ['pending', 'active', 'completed', 'expired'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'session_status']);
            $table->index(['user_id', 'session_status']);
            $table->index(['provider_id', 'session_status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_sessions');
    }
};