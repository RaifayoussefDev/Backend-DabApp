<?php

use App\Models\AdminMenu;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$menus = AdminMenu::whereNull('parent_id')
    ->with('children')
    ->orderBy('order')
    ->get();

foreach ($menus as $menu) {
    echo "Order: {$menu->order} | Key: {$menu->name} | Title: {$menu->title}\n";
    foreach ($menu->children()->orderBy('order')->get() as $child) {
        echo "  - Order: {$child->order} | Key: {$child->name} | Title: {$child->title}\n";
    }
}
