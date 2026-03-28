<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Force fresh data and clear cache first
\Illuminate\Support\Facades\Cache::flush();

$userId = 84; // Raifa
$user = \App\Models\User::with('role')->find($userId);

if (!$user) {
    echo "❌ User with ID 84 not found!" . PHP_EOL;
    exit;
}

echo "--- USER INFO ---" . PHP_EOL;
echo "User ID: " . $user->id . PHP_EOL;
echo "Role ID: " . $user->role_id . PHP_EOL;
echo "Role Name: " . ($user->role->name ?? 'NULL') . PHP_EOL;
echo "Is Admin Logic ($user->role_id === 1): " . ($user->role_id === 1 ? 'YES' : 'NO') . PHP_EOL;

echo PHP_EOL . "--- BUILDING MENU TREE ---" . PHP_EOL;
$menus = \App\Models\AdminMenu::buildTreeForUser($user);
echo "Total Top-Level Menu Items Returned: " . count($menus) . PHP_EOL;

echo PHP_EOL . "--- MENU LIST ---" . PHP_EOL;
foreach ($menus as $m) {
    echo "- " . str_pad($m['title'], 20) . " | Path: " . str_pad($m['path'], 15) . " | Roles in DB: " . json_encode($m['role'] ?? []) . PHP_EOL;
}

echo PHP_EOL . "💡 Suggestion: If 'Users' or others appear above with Roles: [\"admin\"], then the SQL filter was ignored." . PHP_EOL;
