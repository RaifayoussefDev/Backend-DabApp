<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveTypeAndColorFromLicensePlates extends Migration
{
    public function up(): void
    {
        Schema::table('license_plates', function (Blueprint $table) {
            if (Schema::hasColumn('license_plates', 'type_id')) {
                $table->dropForeign(['type_id']);
                $table->dropColumn('type_id');
            }

            if (Schema::hasColumn('license_plates', 'color_id')) {
                $table->dropForeign(['color_id']);
                $table->dropColumn('color_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('license_plates', function (Blueprint $table) {
            $table->foreignId('type_id')->nullable()->constrained('plate_types')->onDelete('cascade');
            $table->foreignId('color_id')->nullable()->constrained('plate_colors')->onDelete('cascade');
        });
    }
}

