<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Vérifier les paiements en attente toutes les 10 minutes
        $schedule->command('payments:check-pending')
                 ->everyTenMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Optionnel : Nettoyer les logs anciens chaque semaine
        $schedule->command('log:clear')
                 ->weekly()
                 ->sundays()
                 ->at('02:00');

        // Optionnel : Optimiser la base de données chaque nuit
        $schedule->command('optimize:clear')
                 ->daily()
                 ->at('03:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
