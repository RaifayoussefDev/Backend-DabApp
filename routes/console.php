<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| NOTE: app/Console/Kernel.php::schedule() is dead code on Laravel 11+ — the
| framework never loads it. Scheduling must live here (or in bootstrap/app.php).
| Only the notification tasks are wired up for now; the other Kernel entries
| (soom:auto-mark-sold, events:send-reminders, SubscriptionExpirationJob, …)
| are still dormant and need a separate audit before being turned on.
*/

// Recurring "did it sell?" reminder — J+7, J+17, J+32, then every 30 days.
Schedule::command('listings:send-follow-up')
    ->dailyAt('18:00')
    ->withoutOverlapping();

// Fire admin broadcasts (user + guest) whose scheduled_at has arrived.
Schedule::command('notifications:dispatch-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

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
