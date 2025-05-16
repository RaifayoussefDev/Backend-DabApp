<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeCharactersNullableInLicensePlatesTable extends Migration
{
    public function up(): void
    {
        Schema::table('license_plates', function (Blueprint $table) {
            $table->string('characters')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('license_plates', function (Blueprint $table) {
            $table->string('characters')->nullable(false)->change();
        });
    }
}

