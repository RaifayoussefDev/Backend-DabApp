<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('debug:rules', function () {
    $targetType = 15;
    $output = "Starting Debug\n";

    $rules = \App\Models\PricingRulesMotorcycle::where('motorcycle_type_id', $targetType)->get();
    $output .= "Searching for TypeID: {$targetType}\n";

    if ($rules->isEmpty()) {
        $output .= "No rules found for TypeID {$targetType}\n";
    } else {
        foreach ($rules as $rule) {
            $output .= "CONFLICT FOUND - ID: {$rule->id} | TypeID: {$rule->motorcycle_type_id} | Price: {$rule->price}\n";
        }
    }

    $all = \App\Models\PricingRulesMotorcycle::all();
    $output .= "Total Rules: " . $all->count() . "\n";

    foreach ($all as $r) {
        $output .= "Rule: ID={$r->id}, TypeID={$r->motorcycle_type_id}\n";
    }

    file_put_contents('debug_output.txt', $output);
    $this->info("Debug output written to debug_output.txt");
});
