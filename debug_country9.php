<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$u = App\Models\User::where('country_id', 9)->first();
if ($u) {
    echo 'User ID: ' . $u->id . PHP_EOL;
    echo 'Tokens count: ' . $u->notificationTokens()->count() . PHP_EOL;
    echo 'Active Tokens count: ' . $u->notificationTokens()->where('is_active', true)->count() . PHP_EOL;
    echo 'Push Enabled: ' . ($u->notificationPreference?->push_enabled ? 'Yes' : 'No') . PHP_EOL;
} else {
    echo 'User not found';
}
