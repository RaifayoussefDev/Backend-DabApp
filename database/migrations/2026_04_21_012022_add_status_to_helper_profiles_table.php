<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helper_profiles', function (Blueprint $table) {
            $table->enum('status', ['pending', 'accepted', 'rejected'])
                  ->default('pending')
                  ->after('is_available');
        });

        // Migrate existing is_verified data → status
        DB::statement("UPDATE helper_profiles SET status = 'accepted' WHERE is_verified = 1");

        Schema::table('helper_profiles', function (Blueprint $table) {
            $table->dropColumn('is_verified');
        });
    }

    public function down(): void
    {
        Schema::table('helper_profiles', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false)->after('is_available');
        });

        DB::statement("UPDATE helper_profiles SET is_verified = 1 WHERE status = 'accepted'");

        Schema::table('helper_profiles', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
