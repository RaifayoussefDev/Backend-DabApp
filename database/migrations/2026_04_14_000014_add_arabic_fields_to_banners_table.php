<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('button_ar')->nullable()->after('button_text');
            $table->string('title_ar')->nullable()->after('button_ar');
            $table->text('description_ar')->nullable()->after('title_ar');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['button_ar', 'title_ar', 'description_ar']);
        });
    }
};
