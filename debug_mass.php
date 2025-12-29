<?php

use App\Models\User;
use App\Models\NotificationTemplate;
use App\Models\NotificationPreference;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- START DEBUG ---\n";

// 1. Check Template
$template = NotificationTemplate::where('type', 'admin_broadcast')->first();
if ($template) {
    echo "PASS: Template 'admin_broadcast' Found.\n";
} else {
    echo "FAIL: Template 'admin_broadcast' NOT FOUND. Run Seeder!\n";
}

// 2. Check User Preference
$user = User::whereHas('listings')->first(); // Get a user who likely has listings
if (!$user) {
    $user = User::first();
    echo "WARN: No user with listings found, using first user.\n";
}

if ($user) {
    echo "User ID: " . $user->id . "\n";
    $pref = $user->notificationPreference;
    if (!$pref) {
        echo "FAIL: User has no notification preferences.\n";
    } else {
        // Test the isNotificationEnabled method with our new mapping
        $enabled = $pref->isNotificationEnabled('admin_broadcast');
        if ($enabled) {
            echo "PASS: User preference for 'admin_broadcast' is ENABLED (Mapped to admin_custom).\n";
        } else {
            echo "FAIL: User preference for 'admin_broadcast' is DISABLED.\n";
            echo "admin_custom value: " . ($pref->admin_custom ? 'true' : 'false') . "\n";
        }
    }
} else {
    echo "FAIL: No users found in DB.\n";
}

echo "--- END DEBUG ---\n";
