<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SendListingFollowUps;

class SendListingFollowUpsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'listings:send-follow-up';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send the recurring "did it sell?" reminder (J+7, J+17, J+32, then every 30 days) to sellers of still-published listings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Queued (not run inline) so a big backlog never blocks the scheduler.
        // The job is chunked and capped per run — see SendListingFollowUps.
        SendListingFollowUps::dispatch();

        $this->info('Listing follow-up sweep queued.');
    }
}
