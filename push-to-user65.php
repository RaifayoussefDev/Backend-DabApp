<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::find(65);

if (!$user) {
    echo "❌ User 65 not found!\n";
    exit(1);
}

echo "📤 Sending notification to: {$user->first_name} {$user->last_name}\n";
echo "Email: {$user->email}\n\n";

$service = app(App\Services\NotificationService::class);

$result = $service->sendToUser(
    $user,
    'event_registration_confirmed',
    [
        'event_name' => 'Red Bull Extreme Sports Festival',
        'event_id' => 2,
    ]
);

// Afficher TOUT le résultat pour voir ce qui s'est passé
echo "📋 Full Result:\n";
print_r($result);
echo "\n";

// Vérifier le succès
if (isset($result['success']) && $result['success']) {
    echo "✅ Success!\n";
    if (isset($result['notification_id'])) {
        echo "Notification ID: {$result['notification_id']}\n";
    }
    if (isset($result['push_results'])) {
        echo "Push sent: " . ($result['push_results']['sent'] ?? 0) . "\n";
        echo "Push failed: " . ($result['push_results']['failed'] ?? 0) . "\n";
    }
} else {
    echo "❌ Failed!\n";
    echo "Reason: " . ($result['message'] ?? 'Unknown error') . "\n";
}
