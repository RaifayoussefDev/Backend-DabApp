<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$log = App\Models\NotificationLog::latest()->first();
if ($log) {
    echo 'ID: ' . $log->id . PHP_EOL;
    echo 'Status: ' . $log->status . PHP_EOL;
    echo 'Error Type: ' . gettype($log->error_message) . PHP_EOL;
    echo 'Error Content: ' . var_export($log->error_message, true) . PHP_EOL;
} else {
    echo 'No logs found';
}
