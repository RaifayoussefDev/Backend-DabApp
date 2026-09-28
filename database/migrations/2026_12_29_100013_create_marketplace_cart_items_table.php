<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('marketplace_carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('marketplace_products')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('marketplace_vendors')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price_snapshot', 12, 2); // price when added, lets us flag price changes
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cart_id', 'product_id'], 'mp_cart_items_cart_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_cart_items');
    }
};
