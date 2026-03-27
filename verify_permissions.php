<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AdminMenu;

$tree = AdminMenu::getTree();

echo "--- API TREE OUTPUT (SAMPLE) ---\n";
foreach ($tree as $menu) {
    echo "Title: " . $menu['title'] . " | Permission: " . ($menu['permission'] ?? 'NULL') . "\n";
    if (isset($menu['children'])) {
        foreach ($menu['children'] as $child) {
            echo "  - Sub: " . $child['title'] . " | Permission: " . ($child['permission'] ?? 'NULL') . "\n";
        }
    }
}
