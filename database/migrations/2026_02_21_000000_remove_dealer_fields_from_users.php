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
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_dealer',
                'dealer_title',
                'dealer_address',
                'dealer_phone',
                'latitude',
                'longitude'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_dealer')->default(false)->after('role_id');
            $table->string('dealer_title')->nullable()->after('is_dealer');
            $table->string('dealer_address')->nullable()->after('dealer_title');
            $table->string('dealer_phone')->nullable()->after('dealer_address');
            $table->decimal('latitude', 10, 8)->nullable()->after('dealer_phone');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });
    }
};
