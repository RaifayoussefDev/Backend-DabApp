<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('spare_part_motorcycles', function (Blueprint $table) {
            $table->foreignId('bike_part_brand_id')->after('year_id')->constrained('bike_part_brands')->onDelete('cascade');
            $table->foreignId('bike_part_category_id')->after('bike_part_brand_id')->constrained('bike_part_categories')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('spare_part_motorcycles', function (Blueprint $table) {
            $table->dropForeign(['bike_part_brand_id']);
            $table->dropForeign(['bike_part_category_id']);
            $table->dropColumn(['bike_part_brand_id', 'bike_part_category_id']);
        });
    }
};
