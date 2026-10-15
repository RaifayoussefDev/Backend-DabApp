<?php

namespace App\Observers;

use App\Models\Listing;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

/**
 * A listing goes live through many paths (PayTabs callback/return/mobile verify,
 * free or 100%-promo listings, admin and prospect creation), so the "it's live"
 * notifications hang off the status transition instead of each controller.
 */
class ListingObserver
{
    public function created(Listing $listing): void
    {
        if ($listing->status === 'published') {
            $this->wentLive($listing);
        }
    }

    public function updated(Listing $listing): void
    {
        // Only the first publication — re-opening a sold/closed listing is not "new"
        if ($listing->wasChanged('status')
            && $listing->status === 'published'
            && $listing->getOriginal('status') === 'draft') {
            $this->wentLive($listing);
        }
    }

    private function wentLive(Listing $listing): void
    {
        $send = function () use ($listing) {
            $notifications = app(NotificationService::class);
            $listing = $listing->fresh(['seller', 'city']);

            if (!$listing || $listing->status !== 'published') {
                return;
            }

            if ($listing->seller) {
                try {
                    $notifications->notifyListingApproved($listing->seller, $listing);
                } catch (\Throwable $e) {
                    Log::error("listing_approved notification failed for listing {$listing->id}: " . $e->getMessage());
                }
            }

            try {
                $notifications->notifyUsersInCityNewListing($listing);
            } catch (\Throwable $e) {
                Log::error("new_listing_in_city broadcast failed for listing {$listing->id}: " . $e->getMessage());
            }
        };

        // In HTTP, defer until after the response: the city broadcast loops over
        // users, and the update may still be inside a payment transaction.
        // Console/queue workers have no "after response", so run it now.
        if (app()->runningInConsole()) {
            $send();
        } else {
            app()->terminating($send);
        }
    }
}
