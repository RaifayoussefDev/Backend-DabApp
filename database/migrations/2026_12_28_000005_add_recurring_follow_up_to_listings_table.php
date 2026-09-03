<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the one-shot "did it sell?" check-in into a recurring reminder:
 * J+7, J+17, J+32, then every 30 days, for as long as the listing stays
 * `published` and the seller hasn't answered.
 *
 * Stateless: no schedule table — `next_follow_up_at` is (re)computed from
 * `published_at` + an offset that depends on how many reminders were already
 * sent (`follow_up_count`). See Listing::followUpOffsetDays().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->unsignedInteger('follow_up_count')->default(0)->after('follow_up_set_by');
            $table->timestamp('next_follow_up_at')->nullable()->after('follow_up_count');
            $table->index(['status', 'next_follow_up_at'], 'idx_listings_next_follow_up');
        });

        // Backfill the schedule for listings still eligible for a reminder.
        //
        // GREATEST(natural due date, now + (id % 21) days) spreads the already-overdue
        // backlog across the next 3 weeks (~1/21 per daily run) so the first cron after
        // deploy doesn't try to notify every old listing at once. Listings whose natural
        // date is still in the future keep it untouched.
        //
        // Already got the old single check-in → treat as 1 reminder sent (natural = J+17);
        // never followed up → 0 sent (natural = J+7).
        DB::statement("
            UPDATE listings
               SET follow_up_count = CASE WHEN follow_up_sent_at IS NOT NULL THEN 1 ELSE 0 END,
                   next_follow_up_at = GREATEST(
                       DATE_ADD(published_at, INTERVAL (CASE WHEN follow_up_sent_at IS NOT NULL THEN 17 ELSE 7 END) DAY),
                       DATE_ADD(NOW(), INTERVAL (id % 21) DAY)
                   )
             WHERE status = 'published'
               AND follow_up_responded_at IS NULL
               AND published_at IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('idx_listings_next_follow_up');
            $table->dropColumn(['follow_up_count', 'next_follow_up_at']);
        });
    }
};
