<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreatePromoCodesTable extends Migration
{
    public function up()
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->decimal('discount_value', 8, 2);
            $table->decimal('max_discount', 8, 2)->nullable();
            $table->decimal('min_listing_price', 8, 2)->nullable();
            $table->integer('usage_limit')->nullable(); // null = unlimited
            $table->integer('per_user_limit')->default(1);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ✅ Insert default promo code
        DB::table('promo_codes')->insert([
            'code' => 'WELCOME10',
            'description' => '10% discount for new users',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'max_discount' => null,
            'min_listing_price' => 0.00,
            'usage_limit' => null, // Unlimited usage
            'per_user_limit' => 1, // Each user can use it once
            'valid_from' => Carbon::now(),
            'valid_until' => Carbon::now()->addYears(1),
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('promo_codes');
    }
}


