<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop submissions table (cascade handles FK)
        Schema::dropIfExists('publicite_submissions');

        // Remove ad columns added to banners table
        Schema::table('banners', function (Blueprint $table) {
            $columns = ['type', 'media_url', 'button_text', 'has_form', 'google_sheet_id'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('banners', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        // Irreversible cleanup — no down needed
    }
};
