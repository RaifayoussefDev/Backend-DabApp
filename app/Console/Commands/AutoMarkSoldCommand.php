<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\AutoMarkSold;

class AutoMarkSoldCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'soom:auto-mark-sold';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically mark listings as sold 5 days after sale validation if not done manually';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting auto-mark-sold job...');

        // Dispatch synchronously for immediate feedback in console
        (new AutoMarkSold())->handle();

        $this->info('Auto-mark-sold job completed.');
    }
}
