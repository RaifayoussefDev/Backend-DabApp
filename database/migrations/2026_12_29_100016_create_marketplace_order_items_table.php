<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('marketplace_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('marketplace_products')->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('marketplace_vendors')->restrictOnDelete();
            $table->foreignId('shipping_method_id')->nullable()->constrained('marketplace_vendor_shipping_methods')->nullOnDelete();

            // Snapshots: the order must not change when the product does
            $table->string('product_name');
            $table->string('product_reference');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->decimal('shipping_fee', 12, 2)->default(0); // vendor flat rate, carried by that vendor's first line
            $table->decimal('discount_amount', 12, 2)->default(0);

            // Commission frozen at order time (vendor override > category > global)
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('vendor_net', 12, 2)->default(0);
            $table->foreignId('payout_id')->nullable()->constrained('marketplace_vendor_payouts')->nullOnDelete();

            $table->enum('fulfillment_status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_id', 'fulfillment_status'], 'mp_order_items_vendor_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_order_items');
    }
};
