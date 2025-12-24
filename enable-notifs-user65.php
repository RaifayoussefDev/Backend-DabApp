<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔧 Enabling notifications for user 65...\n\n";

$user = App\Models\User::find(65);

if (!$user) {
    echo "❌ User not found!\n";
    exit(1);
}

// Récupérer ou créer les préférences
$prefs = $user->notificationPreference;

if (!$prefs) {
    echo "📝 Creating preferences...\n";
    $prefs = App\Models\NotificationPreference::create([
        'user_id' => 65
    ]);
}

// Activer TOUT
echo "✅ Activating all preferences...\n";
$prefs->update([
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
    // POI
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
    // Quiet hours OFF
    'quiet_hours_enabled' => false,
]);

echo "\n✅ Done!\n";
echo "Push enabled: " . ($prefs->push_enabled ? 'YES' : 'NO') . "\n";
echo "Listing approved: " . ($prefs->listing_approved ? 'YES' : 'NO') . "\n";
echo "Quiet hours: " . ($prefs->quiet_hours_enabled ? 'YES' : 'NO') . "\n";
