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
        Schema::create('auction_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_id');
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('buyer_id')->nullable(); // ← Ajout de nullable()
            $table->decimal('bid_amount', 10, 2);
            $table->timestamp('bid_date');
            $table->boolean('validated')->default(false);
            $table->timestamps();
            $table->timestamp('validated_at')->nullable();
            $table->unsignedBigInteger('validator_id')->nullable();

            // Foreign keys
            $table->foreign('listing_id')->references('id')->on('listings');
            $table->foreign('seller_id')->references('id')->on('users');
            $table->foreign('buyer_id')->references('id')->on('users');
            $table->foreign('validator_id')->references('id')->on('users');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auction_histories');
    }
};
