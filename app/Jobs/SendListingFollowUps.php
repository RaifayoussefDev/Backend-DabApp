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
 * One-time "did it sell?" check-in, sent 7 days after a listing goes live.
 * There is no escalating/recurring schedule — each listing gets this exactly once.
 */
class SendListingFollowUps implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationService $notificationService)
    {
        Log::info('Starting listing follow-up sweep...');

        $listings = Listing::needsFollowUp()->with('seller')->get();

        Log::info("Found {$listings->count()} listing(s) due for their day-7 follow-up.");

        foreach ($listings as $listing) {
            try {
                if (!$listing->seller) {
                    continue;
                }

                $listing->update(['follow_up_sent_at' => now()]);

                $notificationService->notifyListingFollowUp($listing->seller, $listing);

                Log::info("Sent follow-up check-in for listing {$listing->id}.");
            } catch (\Exception $e) {
                Log::error("Failed to send follow-up for listing {$listing->id}: " . $e->getMessage());
            }
        }
    }
}
