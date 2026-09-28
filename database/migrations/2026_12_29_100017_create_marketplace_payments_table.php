<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('marketplace_orders')->restrictOnDelete();
            $table->foreignId('card_type_id')->nullable()->constrained('card_types')->nullOnDelete();
            $table->foreignId('bank_card_id')->nullable()->constrained('bank_cards')->nullOnDelete();
            $table->string('gateway')->default('paytabs');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('SAR');
            $table->enum('payment_status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('cart_id')->nullable(); // gateway-side cart reference
            $table->string('tran_ref')->nullable();
            $table->string('payment_url', 1000)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'payment_status']);
            $table->index('tran_ref');
            $table->index('cart_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_payments');
    }
};
