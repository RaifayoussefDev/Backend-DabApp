<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

function check($label, $query) {
    echo "$label: " . $query->count() . PHP_EOL;
}

check("Verified users", User::where('verified', true));
check("Gender male", User::where('gender', 'male'));
check("Country ID 1", User::where('country_id', 1));
check("Role ID 2", User::where('role_id', 2));
check("Brand in Garage (ID 5)", User::whereHas('myGarage', function($q){ $q->where('brand_id', 5); }));
check("Has Points of Interest", User::has('pointsOfInterest'));
check("Last login >= 2024-01-01", User::where('last_login', '>=', '2024-01-01'));
check("Created between 2023 and 2024", User::whereBetween('created_at', ['2023-01-01', '2024-12-31']));

$allFilters = User::applyFilters([
    'is_verified' => true,
    'gender' => 'male',
    'country_id' => 1,
    'role_id' => 2,
    'brand_in_garage' => 5,
    'has_points_of_interest' => true,
    'last_login_from' => '2024-01-01',
    'date_from' => '2023-01-01',
    'date_to' => '2024-12-31'
]);
check("TOTAL with all filters", $allFilters);

$me = User::where('email', 'like', '%admin%')->orWhere('id', 1)->first();
if ($me) {
    echo "--- Test User (ID: {$me->id}) ---" . PHP_EOL;
    echo "Verified: " . ($me->verified ? 'Yes' : 'No') . PHP_EOL;
    echo "Gender: {$me->gender}" . PHP_EOL;
    echo "Country: {$me->country_id}" . PHP_EOL;
    echo "Role: {$me->role_id}" . PHP_EOL;
    echo "Brand 5 in Garage: " . ($me->myGarage()->where('brand_id', 5)->exists() ? 'Yes' : 'No') . PHP_EOL;
    echo "Has POI: " . ($me->pointsOfInterest()->exists() ? 'Yes' : 'No') . PHP_EOL;
    echo "Active Tokens: " . $me->notificationTokens()->where('is_active', true)->count() . PHP_EOL;
}
