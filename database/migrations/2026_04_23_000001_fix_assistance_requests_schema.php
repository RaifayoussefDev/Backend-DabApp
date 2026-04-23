<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix assistance_requests table:
 *
 * 1. expertise_type_id – the design was migrated to a pivot table (assistance_request_expertise)
 *    for multiple expertise types. The old single column was left as NOT NULL, causing every
 *    AssistanceRequest::create() to throw a DB error. Make it nullable so inserts succeed.
 *
 * 2. motorcycle_id FK – the FK pointed to assist_motorcycles, but the codebase uses my_garage
 *    IDs (user's main garage). Drop the stale FK so valid my_garage IDs can be stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistance_requests', function (Blueprint $table) {
            // 1. Drop old FK and make expertise_type_id nullable
            $table->dropForeign(['expertise_type_id']);
            $table->unsignedBigInteger('expertise_type_id')->nullable()->change();

            // 2. Drop FK from assist_motorcycles (column stays, FK goes away)
            $table->dropForeign(['motorcycle_id']);
        });
    }

    public function down(): void
    {
        Schema::table('assistance_requests', function (Blueprint $table) {
            // Restore motorcycle_id FK to assist_motorcycles
            $table->foreign('motorcycle_id')
                ->references('id')->on('assist_motorcycles')
                ->nullOnDelete();

            // Restore expertise_type_id NOT NULL and FK
            $table->unsignedBigInteger('expertise_type_id')->nullable(false)->change();
            $table->foreign('expertise_type_id')
                ->references('id')->on('expertise_types');
        });
    }
};
