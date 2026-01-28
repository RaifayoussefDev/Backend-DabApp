<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tow_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar');
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('icon')->nullable();
            $table->string('image')->nullable();
            $table->decimal('base_price', 10, 2)->nullable()->comment('Base price');
            $table->decimal('price_per_km', 10, 2)->nullable()->comment('Price per kilometer');
            $table->boolean('is_active')->default(true);
            $table->integer('order_position')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'order_position']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tow_types');
    }
};