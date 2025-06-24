<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveCharactersFromLicensePlates extends Migration
{
    public function up(): void
    {
        Schema::table('license_plates', function (Blueprint $table) {
            if (Schema::hasColumn('license_plates', 'characters')) {
                $table->dropColumn('characters');
            }
        });
    }

    public function down(): void
    {
        Schema::table('license_plates', function (Blueprint $table) {
            $table->string('characters')->unique()->nullable();
        });
    }
}
