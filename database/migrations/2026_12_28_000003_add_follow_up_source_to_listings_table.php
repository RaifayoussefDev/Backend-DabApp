<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who set the sale diagnostic on a listing: the seller (via the Day-7 follow-up
 * bottom sheet) or an admin (manual override in the admin panel).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Defensive: the Day-7 follow-up columns (`reason_not_sold` etc.) come from
        // migration 2026_08_26_120000 — add those first if a stale environment is missing them.
        if (!Schema::hasColumn('listings', 'reason_not_sold')) {
            Schema::table('listings', function (Blueprint $table) {
                $table->timestamp('follow_up_sent_at')->nullable();
                $table->timestamp('follow_up_responded_at')->nullable();
                $table->string('follow_up_response')->nullable();
                $table->string('sale_channel')->nullable();
                $table->string('reason_not_sold')->nullable();
            });
        }

        Schema::table('listings', function (Blueprint $table) {
            if (!Schema::hasColumn('listings', 'follow_up_source')) {
                $table->string('follow_up_source')->nullable();
            }
            if (!Schema::hasColumn('listings', 'follow_up_set_by')) {
                $table->unsignedBigInteger('follow_up_set_by')->nullable();
                $table->foreign('follow_up_set_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropForeign(['follow_up_set_by']);
            $table->dropColumn(['follow_up_source', 'follow_up_set_by']);
        });
    }
};
