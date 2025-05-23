<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->decimal('discounted_amount', 10, 2)->after('total_amount')->nullable();
            $table->string('currency', 3)->after('discounted_amount')->default('SAR');
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->onDelete('set null');

        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['total_amount', 'discounted_amount', 'currency']);
        });
    }
};

