<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->decimal('price_per_hour', 10, 2)->nullable()->after('longitude');
            $table->decimal('price_per_mission', 10, 2)->nullable()->after('price_per_hour');
        });
    }

    public function down()
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropColumn(['price_per_hour', 'price_per_mission']);
        });
    }
};
