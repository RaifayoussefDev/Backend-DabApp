<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$out = "TAGS:\n";
try {
    $out .= json_encode(app()->make(\App\Http\Controllers\AdminPoiTagController::class)->stats()->getData(), JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    $out .= $e->getMessage() . "\n";
}
$out .= "\nSERVICES:\n";
try {
    $out .= json_encode(app()->make(\App\Http\Controllers\AdminPoiServiceController::class)->stats()->getData(), JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    $out .= $e->getMessage() . "\n";
}

file_put_contents('test_stats.log', $out);
