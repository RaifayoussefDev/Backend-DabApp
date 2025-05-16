<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateLicensePlatesAddDetails extends Migration
{
    public function up(): void
    {
        Schema::table('license_plates', function (Blueprint $table) {
            $table->string('first_letter', 1)->nullable()->after('characters');
            $table->string('second_letter', 1)->nullable()->after('first_letter');
            $table->string('third_letter', 1)->nullable()->after('second_letter');
            $table->string('numbers')->nullable()->after('third_letter');
            $table->foreignId('city_id')->nullable()->constrained()->onDelete('cascade')->after('country_id');
        });
    }

    public function down(): void
    {
        Schema::table('license_plates', function (Blueprint $table) {
            $table->dropColumn(['first_letter', 'second_letter', 'third_letter', 'number_direction']);
            $table->dropForeign(['city_id']);
            $table->dropColumn('city_id');
        });
    }
}
