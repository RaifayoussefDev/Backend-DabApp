<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('address_id')->nullable()->constrained('marketplace_addresses')->nullOnDelete();
            $table->json('shipping_address')->nullable(); // snapshot, survives address edits/deletes

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('SAR');

            $table->enum('status', [
                'pending_payment', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded',
            ])->default('pending_payment');
            $table->string('idempotency_key', 64)->nullable();
            $table->timestamp('expires_at')->nullable(); // stock reservation deadline while pending_payment
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->unique(['user_id', 'idempotency_key'], 'mp_orders_user_idem_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_orders');
    }
};
