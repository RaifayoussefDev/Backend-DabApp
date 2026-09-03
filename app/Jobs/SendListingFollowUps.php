<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Listing;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

/**
 * Recurring "did it sell?" check-in. Sent J+7, J+17, J+32 after a listing goes
 * live, then every 30 days, for as long as the listing stays `published` and
 * the seller hasn't answered `sold`. No stop condition by design.
 *
 * Stateless: the next due time lives on `listings.next_follow_up_at`, recomputed
 * from `published_at` + `Listing::followUpOffsetDays()` after each send.
 *
 * Scale-safe:
 *  - runs on the queue (the command only dispatches it), never blocks the scheduler;
 *  - processes the due set in id-ordered chunks of 200 (bounded memory);
 *  - stops after LISTING_FOLLOW_UP_DAILY_CAP sends (default 3000) — the overflow
 *    stays `next_follow_up_at <= now` and is picked up on the next daily run, so a
 *    one-off spike of e.g. 10k drains over a few days instead of all at once.
 */
class SendListingFollowUps implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Max reminders sent per run; the overflow carries to the next daily run. */
    public const DAILY_CAP = 3000;

    public function handle(NotificationService $notificationService)
    {
        $cap = self::DAILY_CAP;
        $sent = 0;
        $skipped = 0;

        Log::info("Listing follow-up sweep starting (cap {$cap}).");

        Listing::dueForFollowUp()
            ->with('seller')
            ->orderBy('id')
            ->chunkById(200, function ($listings) use ($notificationService, $cap, &$sent, &$skipped) {
                foreach ($listings as $listing) {
                    if ($sent >= $cap) {
                        return false; // stop chunking — rest waits for the next run
                    }

                    try {
                        $reminderNumber = $listing->follow_up_count + 1;
                        $next = $listing->computeNextFollowUpAt($reminderNumber);

                        if ($listing->seller) {
                            $notificationService->notifyListingFollowUp($listing->seller, $listing, $reminderNumber);
                            $sent++;
                        } else {
                            $skipped++;
                        }

                        // Advance the schedule either way, so a seller-less listing
                        // isn't re-scanned every single run.
                        $listing->forceFill([
                            'follow_up_sent_at' => $listing->seller ? now() : $listing->follow_up_sent_at,
                            'follow_up_count'   => $reminderNumber,
                            'next_follow_up_at' => $next,
                        ])->save();
                    } catch (\Exception $e) {
                        Log::error("Follow-up failed for listing {$listing->id}: " . $e->getMessage());
                    }
                }

                return true;
            });

        Log::info("Listing follow-up sweep done. sent={$sent} skipped_no_seller={$skipped}"
            . ($sent >= $cap ? ' (cap hit — remainder carries to next run)' : ''));
    }
}
