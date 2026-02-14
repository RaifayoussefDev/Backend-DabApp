<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SubscriptionExpirationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $now = \Carbon\Carbon::now();

        // 1. Find active subscriptions that have passed their end date
        $expiredSubscriptions = \App\Models\ServiceSubscription::where('status', 'active')
            ->where('current_period_end', '<', $now)
            ->get();

        foreach ($expiredSubscriptions as $subscription) {

            // Mark as expired
            $subscription->update(['status' => 'expired']);

            // Notify Provider (Log for now, or use Notification system if ready)
            \Illuminate\Support\Facades\Log::info("Subscription expired for Provider ID: {$subscription->provider_id}");

            // TODO: Send Email/Push Notification to Provider
            // $subscription->provider->user->notify(new SubscriptionExpiredNotification($subscription));
        }

        // 2. Automated Renewal attempt logic could go here
        // If auto_renew is true, we would attempt to charge the card again.
        // For now, we just expire them as per current scope.
    }
}
