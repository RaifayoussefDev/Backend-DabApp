<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            // Type of media: photo or video
            $table->enum('type', ['photo', 'video'])->default('photo')->after('image');
            // URL for video (or alternate media URL)
            $table->string('media_url')->nullable()->after('type');
            // Text for the call-to-action button shown on the ad
            $table->string('button_text')->nullable()->after('media_url');
            // Whether this ad shows a lead capture form on button click
            $table->boolean('has_form')->default(false)->after('button_text');
            // Google Sheets spreadsheet ID (extracted from the sheet URL)
            $table->string('google_sheet_id')->nullable()->after('has_form');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['type', 'media_url', 'button_text', 'has_form', 'google_sheet_id']);
        });
    }
};
