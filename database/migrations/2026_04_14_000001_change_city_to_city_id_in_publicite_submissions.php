<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove test rows so FK constraints can be added cleanly
        DB::table('publicite_submissions')->truncate();

        Schema::table('publicite_submissions', function (Blueprint $table) {
            // Drop old plain-text city column if it still exists
            if (Schema::hasColumn('publicite_submissions', 'city')) {
                $table->dropColumn('city');
            }
        });

        Schema::table('publicite_submissions', function (Blueprint $table) {
            // city_id may already exist (partial previous run) — only add if missing
            if (!Schema::hasColumn('publicite_submissions', 'city_id')) {
                $table->foreignId('city_id')
                      ->after('phone')
                      ->constrained('cities')
                      ->onDelete('restrict');
            } else {
                // Column exists but FK may be missing — add the constraint
                $table->foreign('city_id')->references('id')->on('cities')->onDelete('restrict');
            }

            // user_id — only add if missing
            if (!Schema::hasColumn('publicite_submissions', 'user_id')) {
                $table->foreignId('user_id')
                      ->nullable()
                      ->after('banner_id')
                      ->constrained('users')
                      ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('publicite_submissions', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['city_id', 'user_id']);
            $table->string('city')->after('phone');
        });
    }
};
