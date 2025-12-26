<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\NotificationPreference;

class NotificationPreferenceSeeder extends Seeder
{
    public function run()
    {
        $users = User::all();

        foreach ($users as $user) {
            if (!$user->notificationPreference) {
                NotificationPreference::create([
                    'user_id' => $user->id,
                    
                    // Listings
                    'listing_approved' => true,
                    'listing_rejected' => true,
                    'listing_expired' => true,
                    'listing_sold' => true,
                    
                    // Auctions
                    'bid_placed' => true,
                    'bid_accepted' => true,
                    'bid_rejected' => true,
                    'bid_outbid' => true,
                    'auction_ending_soon' => true,

                    // Soom
                    'soom_new_negotiation' => true,
                    'soom_counter_offer' => true,
                    'soom_accepted' => true,
                    'soom_rejected' => true,
                    
                    // Payments
                    'payment_success' => true,
                    'payment_failed' => true,
                    'payment_pending' => true,
                    
                    // Wishlist
                    'wishlist_price_drop' => true,
                    'wishlist_item_sold' => true,
                    
                    // Messages
                    'new_message' => true,
                    
                    // Guides
                    'new_guide_published' => true,
                    'guide_comment' => true,
                    'guide_like' => true,
                    
                    // Events
                    'event_reminder' => true,
                    'event_updated' => true,
                    'event_cancelled' => true,
                    
                    // POI (Points of Interest)
                    'poi_review' => true,
                    'new_poi_nearby' => true,
                    
                    // Routes
                    'route_comment' => true,
                    'route_warning' => true,
                    
                    // System
                    'system_updates' => true,
                    'promotional' => true,
                    'newsletter' => true,
                    'admin_custom' => true,
                    
                    // Channels
                    'push_enabled' => true,
                    'in_app_enabled' => true,
                    'email_enabled' => true,
                    'sms_enabled' => true,
                    
                    // Quiet Hours (Disabled by default)
                    'quiet_hours_enabled' => false,
                ]);
            }
        }
    }
}
