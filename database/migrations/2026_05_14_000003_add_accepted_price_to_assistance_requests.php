<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistance_requests', function (Blueprint $table) {
            $table->unsignedInteger('accepted_price')->nullable()->after('completion_token')
                ->comment('Price agreed upon when seeker accepted a helper proposal');
        });
    }

    public function down(): void
    {
        Schema::table('assistance_requests', function (Blueprint $table) {
            $table->dropColumn('accepted_price');
        });
    }
};
