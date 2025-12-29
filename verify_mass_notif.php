<?php

use App\Models\User;
use App\Models\City;
use App\Jobs\MassNotificationJob;
use Illuminate\Support\Facades\Log;

$logFile = base_path('mass_verification.log');
file_put_contents($logFile, "Starting Mass Verification at " . now() . "\n");

function logM($message) {
    global $logFile;
    file_put_contents($logFile, $message . "\n", FILE_APPEND);
}

try {
    // 1. Setup Data
    $city = City::firstOrCreate(['name' => 'Mass City'], ['country_id' => 1]);
    $user1 = User::factory()->create(['city_id' => $city->id]);
    $user2 = User::factory()->create(['city_id' => $city->id]);
    $user3 = User::factory()->create(['city_id' => $city->id]); // Control (different filters?)
    
    // 2. Dispatch Job Manually (Simulate Controller)
    $filters = ['city_id' => $city->id];
    $content = [
        'title_en' => 'Big Sale!',
        'title_ar' => 'Grosse Promo!',
        'body_en' => '50% Off Everything',
        'body_ar' => '50% de reduction',
        'type' => 'promo'
    ];
    $channels = ['push'];

    logM("Dispatching Job for city {$city->id}...");
    // We execute handle() directly to avoid queue worker delay for test
    $job = new MassNotificationJob($filters, $content, $channels);
    $job->handle(app(\App\Services\NotificationService::class));
    
    // 3. Verify
    $notif1 = $user1->notifications()->latest()->first();
    $notif2 = $user2->notifications()->latest()->first();

    if ($notif1 && $notif1->type == 'admin_broadcast' && str_contains($notif1->data['title'], 'Big Sale')) {
        logM("PASS: User 1 received mass notification.");
    } else {
        logM("FAIL: User 1 did not receive notification. Type: " . ($notif1->type ?? 'null'));
    }

    if ($notif2 && $notif2->type == 'admin_broadcast') {
        logM("PASS: User 2 received mass notification.");
    } else {
        logM("FAIL: User 2 did not receive notification.");
    }
    
    // Check Email (if we enabled it? we didn't mocked mail but can check log in real app)
    // For now, notification DB check is sufficient proof of Job logic.

} catch (\Exception $e) {
    logM("ERROR: " . $e->getMessage());
}
