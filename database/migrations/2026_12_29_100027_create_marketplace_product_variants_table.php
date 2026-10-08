<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Up to 2 option dimensions per product (Color+Size, or Wheel Location+Tyre Size, ...). A product with
        // no rows here is a plain simple product and keeps using marketplace_products.price/stock_quantity directly.
        Schema::create('marketplace_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('marketplace_products')->cascadeOnDelete();
            $table->string('sku')->unique();

            $table->string('option1_name')->nullable();  // e.g. "Color"
            $table->string('option1_value')->nullable();  // e.g. "Black"
            $table->string('color_hex', 7)->nullable();   // swatch, only meaningful when option1/2 is a color
            $table->string('option2_name')->nullable();  // e.g. "Size"
            $table->string('option2_value')->nullable();  // e.g. "XS"

            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable(); // strikethrough original price
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->string('image_path')->nullable();
            $table->boolean('is_default')->default(false); // pre-selected on the product page
            $table->unsignedInteger('order_position')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'order_position'], 'mp_product_variants_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_product_variants');
    }
};
