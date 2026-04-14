<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            // Nullable link to an ad (has_form=true banner) attached to this visual banner
            $table->unsignedBigInteger('ad_id')->nullable()->after('link');
            $table->foreign('ad_id')->references('id')->on('banners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropForeign(['ad_id']);
            $table->dropColumn('ad_id');
        });
    }
};
