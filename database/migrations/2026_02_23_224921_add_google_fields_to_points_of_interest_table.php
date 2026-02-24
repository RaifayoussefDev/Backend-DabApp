<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('points_of_interest', function (Blueprint $table) {
            $table->string('google_place_id')->nullable()->after('website');
            $table->decimal('google_rating', 3, 1)->nullable()->after('google_place_id');
            $table->unsignedInteger('google_reviews_count')->nullable()->after('google_rating');
        });
    }

    public function down(): void
    {
        Schema::table('points_of_interest', function (Blueprint $table) {
            $table->dropColumn(['google_place_id', 'google_rating', 'google_reviews_count']);
        });
    }
};
