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
        Schema::create('trainer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->enum('payment_status', ['pending', 'paid', 'refunded', 'failed'])->default('pending');
            $table->string('tran_ref')->nullable()->unique();  // PayTabs transaction ref
            $table->string('cart_id')->nullable();             // PayTabs cart id
            $table->string('resp_code')->nullable();
            $table->string('resp_message')->nullable();
            $table->json('paytabs_response')->nullable();      // full PayTabs callback payload
            $table->string('currency', 3)->default('SAR');
            $table->timestamps();

            $table->index('user_id');
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_payments');
    }
};
