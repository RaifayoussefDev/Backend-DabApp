<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeColorIdNullableInLicensePlatesTable extends Migration
{
    public function up(): void
    {
        Schema::table('license_plates', function (Blueprint $table) {
            $table->foreignId('color_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('license_plates', function (Blueprint $table) {
            $table->foreignId('color_id')->nullable(false)->change();
        });
    }
}

