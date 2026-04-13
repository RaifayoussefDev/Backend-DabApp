<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helper_profiles', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('longitude')
                ->constrained('countries')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('country_id')
                ->constrained('cities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('helper_profiles', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['city_id']);
            $table->dropColumn(['country_id', 'city_id']);
        });
    }
};
