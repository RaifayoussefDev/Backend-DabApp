<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tokens = App\Models\User::find(65)->notificationTokens;
echo 'Total Tokens for User 65: ' . $tokens->count() . PHP_EOL;
foreach($tokens as $t) {
    echo 'Token ID: ' . $t->id . PHP_EOL;
    echo '  Active: ' . ($t->is_active ? 'Y' : 'N') . PHP_EOL;
    echo '  fcm_token type: ' . gettype($t->fcm_token) . PHP_EOL;
    echo '  fcm_token value: ' . var_export($t->fcm_token, true) . PHP_EOL;
}
