<?php
// /tmp/debug_notification.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$filters = [
    'is_verified' => true,
    'gender' => 'male',
    'country_id' => 1,
    'role_id' => 2,
    'brand_in_garage' => 5,
    'has_points_of_interest' => true,
    'last_login_from' => '2024-01-01',
    'date_from' => '2023-01-01',
    'date_to' => '2024-12-31'
];

$users = User::applyFilters($filters)->get();
echo "Total Matching Users with ALL filters: " . $users->count() . PHP_EOL;

echo "--- Independent Filter Checks ---" . PHP_EOL;
echo "Verified: " . User::where('verified', true)->count() . PHP_EOL;
echo "Gender male: " . User::where('gender', 'male')->count() . PHP_EOL;
echo "Country ID 1: " . User::where('country_id', 1)->count() . PHP_EOL;
echo "Role ID 2: " . User::where('role_id', 2)->count() . PHP_EOL;
echo "Brand in Garage 5: " . User::whereHas('myGarage', function($q){ $q->where('brand_id', 5); })->count() . PHP_EOL;
echo "Has POI: " . User::has('pointsOfInterest')->count() . PHP_EOL;
echo "Last login >= 2024-01-01: " . User::where('last_login', '>=', '2024-01-01')->count() . PHP_EOL;
echo "Registration Date Range: " . User::whereBetween('created_at', ['2023-01-01', '2024-12-31'])->count() . PHP_EOL;

// Check the user account of the person testing (assuming we know it or just look at ALL users)
$me = User::where('email', 'like', '%admin%')->orWhere('id', 1)->first();
if ($me) {
    echo "--- Test User Data (ID: {$me->id}) ---" . PHP_EOL;
    echo "  - Verified: " . ($me->verified ? 'Yes' : 'No') . PHP_EOL;
    echo "  - Gender: {$me->gender}" . PHP_EOL;
    echo "  - Country ID: {$me->country_id}" . PHP_EOL;
    echo "  - Role ID: {$me->role_id}" . PHP_EOL;
    echo "  - Brand 5 in Garage: " . ($me->myGarage()->where('brand_id', 5)->exists() ? 'Yes' : 'No') . PHP_EOL;
    echo "  - Has POI: " . ($me->pointsOfInterest()->exists() ? 'Yes' : 'No') . PHP_EOL;
}
