<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('is_featured');
            $table->string('meta_title_ar')->nullable()->after('meta_title');
            $table->text('meta_description')->nullable()->after('meta_title_ar');
            $table->text('meta_description_ar')->nullable()->after('meta_description');
            $table->text('meta_keywords')->nullable()->after('meta_description_ar');
            $table->text('meta_keywords_ar')->nullable()->after('meta_keywords');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->dropColumn([
                'meta_title',
                'meta_title_ar',
                'meta_description',
                'meta_description_ar',
                'meta_keywords',
                'meta_keywords_ar'
            ]);
        });
    }
};
