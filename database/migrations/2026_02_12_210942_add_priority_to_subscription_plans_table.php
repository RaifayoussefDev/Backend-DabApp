<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->integer('priority')->default(0)->after('price_yearly');
        });

        // Seed default priorities
        DB::table('subscription_plans')->where('name', 'Basic Plan')->update(['priority' => 1]);
        DB::table('subscription_plans')->where('name', 'Business Plan')->update(['priority' => 2]);
        DB::table('subscription_plans')->where('name', 'Enterprise Plan')->update(['priority' => 3]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
