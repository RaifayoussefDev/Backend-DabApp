<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('motorcycle_brands', function (Blueprint $table) {
            $table->boolean('is_displayed')->default(false)->after('name');
            // ou si vous préférez 'is_active'
            // $table->boolean('is_active')->default(true)->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('motorcycle_brands', function (Blueprint $table) {
            $table->dropColumn('is_displayed');
            // ou $table->dropColumn('is_active');
        });
    }
};
