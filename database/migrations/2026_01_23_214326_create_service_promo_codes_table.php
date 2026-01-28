<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('service_promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed'])->comment('percentage or fixed amount');
            $table->decimal('discount_value', 8, 2);
            $table->decimal('min_booking_price', 8, 2)->nullable()->comment('Minimum booking price required');
            $table->decimal('max_discount', 8, 2)->nullable()->comment('Maximum discount amount');
            $table->foreignId('service_category_id')->nullable()->constrained('service_categories')->onDelete('set null')->comment('NULL = all categories');
            $table->integer('usage_limit')->nullable()->comment('Total usage limit');
            $table->integer('per_user_limit')->default(1)->comment('Usage limit per user');
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('code');
            $table->index(['is_active', 'valid_from', 'valid_until']);
            $table->index('service_category_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_promo_codes');
    }
};