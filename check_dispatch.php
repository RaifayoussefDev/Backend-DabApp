<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Jobs\MassNotificationJob;

$res = MassNotificationJob::dispatchSync(['country_id' => 999], ['title_en' => 'Test'], ['push']);
echo "Result type: " . gettype($res) . PHP_EOL;
print_r($res);
