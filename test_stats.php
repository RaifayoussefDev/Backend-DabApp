<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $tagStats = app()->make(App\Http\Controllers\AdminPoiTagController::class)->stats()->getData();
    echo "TAG STATS:\n";
    echo json_encode($tagStats, JSON_PRETTY_PRINT) . "\n\n";
} catch (\Exception $e) {
    echo "TAG ERROR: " . $e->getMessage() . "\n";
}

try {
    $svcStats = app()->make(App\Http\Controllers\AdminPoiServiceController::class)->stats()->getData();
    echo "SVC STATS:\n";
    echo json_encode($svcStats, JSON_PRETTY_PRINT) . "\n\n";
} catch (\Exception $e) {
    echo "SVC ERROR: " . $e->getMessage() . "\n";
}
