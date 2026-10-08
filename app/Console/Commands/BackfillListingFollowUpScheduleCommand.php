<?php

namespace App\Console\Commands;

use App\Jobs\SendListingFollowUps;
use App\Models\Listing;
use Illuminate\Console\Command;

/**
 * One-off backfill: arms `next_follow_up_at` for published listings that
 * predate the recurring "did it sell?" feature (or were otherwise never
 * touched since), so the daily `listings:send-follow-up` sweep actually finds
 * them — Listing::scopeDueForFollowUp() requires next_follow_up_at to be
 * non-null, and that column is only ever set by the model's `saving` hook
 * (Listing::booted()), which fires on save, not for rows sitting untouched
 * in the DB.
 *
 * Safe by construction, no new cadence logic:
 *  - Reuses Listing::computeNextFollowUpAt(), which already floors an
 *    already-past natural date (an old listing entering the schedule) to
 *    now()+30 days — so backfilling never fires an instant burst of reminders.
 *  - Only fills a NULL slot (matches the `saving` hook's own guard), so
 *    running it twice, or alongside normal traffic, is a no-op the second time.
 *  - Sending itself still goes through the existing daily sweep, capped at
 *    SendListingFollowUps::DAILY_CAP per run — a large backlog drains over
 *    several days instead of one push blast.
 */
class BackfillListingFollowUpScheduleCommand extends Command
{
    protected $signature = 'listings:backfill-follow-up-schedule {--dry-run : Report how many listings would be armed, without writing anything}';

    protected $description = 'One-off: arm next_follow_up_at for published listings the recurring "did it sell?" schedule never picked up, so the capped daily sweep can reach them gradually';

    public function handle(): int
    {
        $query = Listing::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where(fn ($q) => $q->whereNull('follow_up_response')->orWhere('follow_up_response', '!=', 'sold'))
            ->whereNull('next_follow_up_at');

        $total = $query->count();

        if ($total === 0) {
            $this->info('Nothing to backfill — every published listing already has a follow-up schedule.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("{$total} published listing(s) would be armed. No changes made (--dry-run).");
            return self::SUCCESS;
        }

        $armed = 0;

        $query->chunkById(500, function ($listings) use (&$armed) {
            foreach ($listings as $listing) {
                // computeNextFollowUpAt() already floors an overdue natural date (an old
                // listing entering the schedule) to now()+30 days, so this never marks a
                // backlog listing due immediately — see the class docblock.
                $next = $listing->computeNextFollowUpAt();

                // Model event, not a real column change on our part — skip observers
                // so a one-off backfill can't trigger unrelated side effects at scale.
                $listing->forceFill(['next_follow_up_at' => $next])->saveQuietly();

                $armed++;
            }
        });

        $cap = SendListingFollowUps::DAILY_CAP;
        $this->info("Armed {$armed} listing(s).");
        $this->info('None fire immediately — a listing whose natural reminder date already passed is floored to 30 days out, so the first wave lands together on that day.');
        $this->info("From then on the daily sweep (dailyAt 18:00) is capped at {$cap}/run, so a wave larger than that drains over several days instead of one burst.");

        return self::SUCCESS;
    }
}
