<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->string('price_type'); // 'fixed' or 'auction'
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->timestamps();
            $table->string('status'); // 'active', 'sold', 'deleted'
            $table->boolean('auction_enabled')->default(false);
            $table->decimal('minimum_bid', 10, 2)->nullable();
            $table->unsignedBigInteger('product_state_id');
            $table->unsignedBigInteger('finish_id')->nullable();
            $table->unsignedBigInteger('color_id')->nullable();
            $table->boolean('allow_submission')->default(false);
            $table->unsignedBigInteger('listing_type_id');

            // Foreign keys
            $table->foreign('seller_id')->references('id')->on('users');
            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('finish_id')->references('id')->on('finishes');
            $table->foreign('color_id')->references('id')->on('colors');
            $table->foreign('listing_type_id')->references('id')->on('listing_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
