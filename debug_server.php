<?php
// debug_server.php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n--- DIAGNOSTIC START ---\n";

// 1. Check Firebase Config
$configFile = config('firebase.credentials.file');
$path = storage_path('app/' . $configFile);
echo "1. Firebase Config Path: " . $path . "\n";

if (file_exists($path)) {
    echo "   [OK] File exists.\n";
} else {
    echo "   [ERROR] File NOT FOUND! Notification Job will fail.\n";
}

// 2. Try Service
try {
    app(\App\Services\FirebaseService::class);
    echo "2. FirebaseService: [OK] Loaded successfully.\n";
} catch (\Throwable $e) {
    echo "2. FirebaseService: [ERROR] " . $e->getMessage() . "\n";
}

// 3. Check Template
$tpl = \App\Models\NotificationTemplate::where('type', 'admin_broadcast')->first();
echo "3. Template 'admin_broadcast': " . ($tpl ? "[OK] Found." : "[ERROR] NOT FOUND (Run Seeder).") . "\n";

// 4. Check Queue Worker Status (Simple verify)
echo "4. Queue Connection: " . config('queue.default') . "\n";

echo "--- DIAGNOSTIC END ---\n";