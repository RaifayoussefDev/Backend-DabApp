<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('response_code')->nullable()->after('resp_message');
            $table->text('payment_result')->nullable()->after('response_code');
            $table->timestamp('completed_at')->nullable()->after('payment_result');
            $table->timestamp('failed_at')->nullable()->after('completed_at');
            $table->string('currency', 3)->default('AED')->after('amount');
            $table->string('customer_name')->nullable()->after('currency');
            $table->string('customer_email')->nullable()->after('customer_name');
            $table->string('customer_phone')->nullable()->after('customer_email');
            $table->text('payment_url')->nullable()->after('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'response_code',
                'payment_result',
                'completed_at',
                'failed_at',
                'currency',
                'customer_name',
                'customer_email',
                'customer_phone',
                'payment_url'
            ]);
        });
    }
};
