<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::find(65);
$service = app(App\Services\NotificationService::class);

$result = $service->sendToUser(
    $user,
    'event_registration_confirmed',
    [
        'event_name' => 'Red Bull Extreme Sports Festival',
        'event_id' => 2,
    ]
);

echo "✅ Push sent!\n";
echo "Notification ID: " . $result['notification_id'] . "\n";
echo "Push sent: " . ($result['push_results']['sent'] ?? 0) . "\n";
echo "Push failed: " . ($result['push_results']['failed'] ?? 0) . "\n";

