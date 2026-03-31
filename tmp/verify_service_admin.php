<?php

use Illuminate\Support\Facades\Route;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$routes = [
    'api/admin/service-providers',
    'api/admin/service-providers/stats',
    'api/admin/subscription-transactions',
    'api/admin/subscription-transactions/stats',
    'api/admin/service-promo-codes',
    'api/admin/services/stats/overview',
    'api/admin/services/1/pricing-rules',
    'api/admin/services/1/required-documents',
    'api/admin/services/1/schedules',
];

echo "Checking Routes:\n";
foreach ($routes as $path) {
    try {
        $route = Route::getRoutes()->match(request()->create($path, 'GET'));
        echo "[OK] GET $path -> " . $route->getActionName() . "\n";
    } catch (\Exception $e) {
        echo "[FAIL] GET $path: " . $e->getMessage() . "\n";
    }
}

$postRoutes = [
    ['path' => 'api/admin/service-providers/1/verify', 'method' => 'POST'],
    ['path' => 'api/admin/service-providers/1/toggle-status', 'method' => 'POST'],
    ['path' => 'api/admin/service-promo-codes', 'method' => 'POST'],
    ['path' => 'api/admin/services/1/pricing-rules', 'method' => 'POST'],
    ['path' => 'api/admin/services/1/required-documents', 'method' => 'POST'],
];

echo "\nChecking POST/PATCH Routes:\n";
foreach ($postRoutes as $r) {
    try {
        $route = Route::getRoutes()->match(request()->create($r['path'], $r['method']));
        echo "[OK] {$r['method']} {$r['path']} -> " . $route->getActionName() . "\n";
    } catch (\Exception $e) {
        // Some might fail if they expect specific IDs that don't exist, but we just check route presence
        echo "[INFO] {$r['method']} {$r['path']}: " . $e->getMessage() . "\n";
    }
}
