<?php

use App\Models\User;
use App\Models\Listing;
use App\Models\City;
use App\Models\Event;
use App\Models\Guide;
use App\Models\NotificationTemplate;
use App\Services\NotificationService;
use App\Services\EventNotificationService;
use Illuminate\Support\Facades\Log;

$logFile = base_path('verification_results.log');
file_put_contents($logFile, "Starting Verification at " . now() . "\n");

function logResult($message) {
    global $logFile;
    file_put_contents($logFile, $message . "\n", FILE_APPEND);
}

try {
    // 1. Check Templates
    $types = ['new_listing_in_city', 'guide_updated', 'event_reminder', 'guide_new_comment'];
    $count = NotificationTemplate::whereIn('type', $types)->count();
    logResult("Templates found: $count/" . count($types));

    // 2. new_listing_in_city
    $city = City::first();
    if (!$city) {
        $city = City::create(['name' => 'Test City', 'country_id' => 1]);
    }
    
    $seller = User::factory()->create(['city_id' => $city->id]);
    $receiver = User::factory()->create(['city_id' => $city->id]);
    
    // Ensure preference
    $receiver->notificationPreference()->updateOrCreate(['user_id' => $receiver->id], ['new_listing_in_city' => true]);

    $listing = Listing::create([
        'title' => 'Verification Listing',
        'price' => 500,
        'city_id' => $city->id,
        'seller_id' => $seller->id,
        'status' => 'published',
        'description' => 'test', 
        'category_id' => 1
    ]);

    app(NotificationService::class)->notifyUsersInCityNewListing($listing);
    
    // Check notification in DB
    $notif = $receiver->notifications()->latest()->first();
    if ($notif && $notif->type == 'new_listing_in_city') {
        logResult("PASS: New Listing Notification received by user {$receiver->id}");
    } else {
        logResult("FAIL: New Listing Notification NOT received. Last notif type: " . ($notif->type ?? 'null'));
    }

    // 3. guide_updated
    $guide = Guide::create([
        'title' => 'Test Guide',
        'author_id' => $seller->id,
        'status' => 'published',
        'slug' => 'test-guide-' . uniqid(),
        'content' => 'content',
        'category_id' => 1
    ]);
    
    app(NotificationService::class)->notifyGuideUpdated($receiver, $guide);
     $notif = $receiver->notifications()->latest()->first();
    if ($notif && $notif->type == 'guide_updated') {
        logResult("PASS: Guide Update Notification received");
    } else {
        logResult("FAIL: Guide Update Notification NOT received");
    }

    // 4. event_reminder (simulate)
    $event = Event::create([
        'title' => 'Test Event', 
        'organizer_id' => $seller->id,
        'event_date' => now()->addHours(24),
        'start_time' => now()->addHours(24)->format('H:i:s'),
        'status' => 'upcoming',
        'slug' => 'event-' . uniqid(),
        'description' => 'test',
        'category_id' => 1,
        'venue_name' => 'Venue',
        'address' => ' Address',
        'city_id' => $city->id,
        'country_id' => 1
    ]);
    
    // Add interested user
    $event->interestedUsers()->attach($receiver->id);
    
    // Run command logic manually (or call service)
    app(EventNotificationService::class)->sendToInterestedUsers($event, 'event_reminder', ['hours' => 24]);
    
    $notif = $receiver->notifications()->latest()->first();
    if ($notif && $notif->type == 'event_reminder') {
        logResult("PASS: Event Reminder Notification received");
    } else {
        logResult("FAIL: Event Reminder Notification NOT received");
    }

} catch (\Exception $e) {
    logResult("ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}
