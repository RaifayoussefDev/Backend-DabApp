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
        Schema::create('payment_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('trainer_payments')->onDelete('cascade');
            $table->foreignId('booking_id')->constrained('trainer_bookings')->onDelete('cascade');
            $table->foreignId('trainer_id')->constrained('trainers')->onDelete('cascade');
            $table->unsignedBigInteger('commission_setting_id')->nullable(); // FK added after commission_settings is created
            $table->decimal('total_amount', 10, 2);
            $table->decimal('commission_percentage', 5, 2);  // snapshotted at time of transaction
            $table->decimal('commission_amount', 10, 2);     // DabApp's cut
            $table->decimal('trainer_amount', 10, 2);        // trainer's payout
            $table->string('currency', 3)->default('SAR');
            $table->enum('status', ['pending', 'settled'])->default('pending');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_splits');
    }
};
