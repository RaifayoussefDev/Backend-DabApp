<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\NotificationTemplate;
use App\Services\NotificationService;
use App\Services\FirebaseService;

echo "\n--- STARTING SYNCHRONOUS DEBUG ---\n";

// 1. Get a User
$user = User::where('is_active', true)->first();
if (!$user) {
    die("[FAIL] No active user found.\n");
}
echo "[OK] Found User ID: {$user->id} ({$user->email})\n";

// 2. Check Preference
$pref = $user->notificationPreference;
if (!$pref) {
    die("[FAIL] No preferences found for user.\n");
}

$enabled = $pref->isNotificationEnabled('admin_broadcast');
echo "[INFO] isNotificationEnabled('admin_broadcast') returned: " . ($enabled ? "TRUE" : "FALSE") . "\n";

if (!$enabled) {
    echo "[DEBUG] admin_custom column value: " . ($pref->admin_custom ? '1' : '0') . "\n";
    echo "[DEBUG] If this is FALSE, check NotificationPreference.php model mapping.\n";
}

// 3. Check Template
$template = NotificationTemplate::where('type', 'admin_broadcast')->first();
if (!$template) {
    echo "[FAIL] Template 'admin_broadcast' missing.\n";
} else {
    echo "[OK] Template 'admin_broadcast' found.\n";
}

// 4. Attempt Send (Bypassing Job)
echo "[INFO] Attempting to send notification via Service...\n";

try {
    $service = app(NotificationService::class);
    $result = $service->sendToUser($user, 'admin_broadcast', [
        'title' => 'Debug Title',
        'body'  => 'Debug Body'
    ], ['channels' => ['push']]);

    print_r($result);

    if ($result['success']) {
        echo "[SUCCESS] Notification created! Check Database now.\n";
    } else {
        echo "[FAIL] Service returned error: " . $result['message'] . "\n";
    }

} catch (\Throwable $e) {
    echo "[CRITICAL EXCEPTION] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "--- END DEBUG ---\n";
