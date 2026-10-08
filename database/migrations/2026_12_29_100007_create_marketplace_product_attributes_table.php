<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Generic key/value specs (tyre width, diameter, ...)
        Schema::create('marketplace_product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('marketplace_products')->cascadeOnDelete();
            $table->string('label');
            $table->string('label_ar')->nullable();
            $table->string('value');
            $table->string('value_ar')->nullable();
            $table->unsignedInteger('order_position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_product_attributes');
    }
};
