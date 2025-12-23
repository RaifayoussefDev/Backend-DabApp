<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\NotificationPreference;

class EnableAllNotifications extends Command
{
    protected $signature = 'test:enable-notifications {email=test@dabapp.com}';
    protected $description = 'Enable all notifications for a user';

    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("❌ User not found: {$email}");
            return 1;
        }

        $prefs = $user->notificationPreference;

        if (!$prefs) {
            $this->info('📝 Creating notification preferences...');
            $prefs = NotificationPreference::create(['user_id' => $user->id]);
        }

        $prefs->update([
            'listing_approved' => true,
            'listing_rejected' => true,
            'listing_expired' => true,
            'listing_sold' => true,
            'bid_placed' => true,
            'bid_accepted' => true,
            'bid_rejected' => true,
            'bid_outbid' => true,
            'auction_ending_soon' => true,
            'payment_success' => true,
            'payment_failed' => true,
            'payment_pending' => true,
            'wishlist_price_drop' => true,
            'wishlist_item_sold' => true,
            'new_message' => true,
            'new_guide_published' => true,
            'guide_comment' => true,
            'guide_like' => true,
            'event_reminder' => true,
            'event_updated' => true,
            'event_cancelled' => true,
            'poi_review' => true,
            'new_poi_nearby' => true,
            'route_comment' => true,
            'route_warning' => true,
            'system_updates' => true,
            'promotional' => true,
            'newsletter' => true,
            'admin_custom' => true,
            'push_enabled' => true,
            'in_app_enabled' => true,
            'email_enabled' => true,
        ]);

        $this->info('✅ All notifications enabled for: ' . $user->email);

        return 0;
    }
}
