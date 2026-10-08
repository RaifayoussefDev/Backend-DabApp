<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // vendor_id set = discounts only that vendor's lines; null = platform-wide.
        // Rule (enforced in code): max 1 vendor row + 1 platform row per order.
        Schema::create('marketplace_order_promo_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('marketplace_orders')->cascadeOnDelete();
            $table->foreignId('promo_code_id')->constrained('promo_codes')->restrictOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('marketplace_vendors')->nullOnDelete();
            $table->string('code'); // snapshot
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_order_promo_codes');
    }
};
