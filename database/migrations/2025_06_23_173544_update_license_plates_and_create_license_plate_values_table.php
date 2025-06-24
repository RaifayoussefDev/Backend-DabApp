<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateLicensePlatesAndCreateLicensePlateValuesTable extends Migration
{
    public function up(): void
    {
        // Modifier license_plates
        Schema::table('license_plates', function (Blueprint $table) {
            if (Schema::hasColumn('license_plates', 'first_letter')) {
                $table->dropColumn('first_letter');
            }
            if (Schema::hasColumn('license_plates', 'second_letter')) {
                $table->dropColumn('second_letter');
            }
            if (Schema::hasColumn('license_plates', 'third_letter')) {
                $table->dropColumn('third_letter');
            }
            if (Schema::hasColumn('license_plates', 'numbers')) {
                $table->dropColumn('numbers');
            }
            if (Schema::hasColumn('license_plates', 'digits_count')) {
                $table->dropColumn('digits_count');
            }

            // Ajout des nouvelles colonnes
            if (!Schema::hasColumn('license_plates', 'city_id')) {
                $table->foreignId('city_id')->nullable()->constrained()->onDelete('cascade');
            }

            if (!Schema::hasColumn('license_plates', 'plate_format_id')) {
                $table->foreignId('plate_format_id')->nullable()->constrained()->onDelete('cascade');
            }
        });
 

        // Créer license_plate_values
        Schema::create('license_plate_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_plate_id')->constrained('license_plates')->onDelete('cascade');
            $table->foreignId('plate_format_field_id')->constrained('plate_format_fields')->onDelete('cascade');
            $table->string('field_value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_plate_values');

        Schema::table('license_plates', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropForeign(['plate_format_id']);
            $table->dropColumn(['city_id', 'plate_format_id']);

            $table->string('first_letter')->nullable();
            $table->string('second_letter')->nullable();
            $table->string('third_letter')->nullable();
            $table->string('numbers')->nullable();
            $table->unsignedInteger('digits_count');
        });
    }
}

