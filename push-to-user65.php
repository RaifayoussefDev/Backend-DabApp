<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::find(65);
$service = app(App\Services\NotificationService::class);

// Utiliser 'listing_approved' qui EXISTE dans le modèle
$result = $service->sendToUser(
    $user,
    'listing_approved',
    [
        'listing_title' => 'Honda CBR 600RR 2020',
        'listing_id' => 123,
    ]
);

echo "📋 Result:\n";
print_r($result);

if ($result['success']) {
    echo "\n✅ Notification sent!\n";
    echo "Notification ID: {$result['notification_id']}\n";
    echo "Push sent: " . ($result['push_results']['sent'] ?? 0) . "\n";
    echo "Push failed: " . ($result['push_results']['failed'] ?? 0) . "\n";
} else {
    echo "\n❌ Failed: {$result['message']}\n";
}
