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
    protected $description = 'Send the one-time "did it sell?" follow-up to sellers 7 days after a listing is published';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting listing follow-up job...');

        (new SendListingFollowUps())->handle(app(\App\Services\NotificationService::class));

        $this->info('Listing follow-up job completed.');
    }
}
