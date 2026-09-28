<?php

namespace App\Console\Commands;

use Database\Seeders\MarketplaceDemoSeeder;
use Illuminate\Console\Command;

/**
 * Fills dev/test with realistic marketplace data (3 vendors, 25 products, orders, reviews...) so the
 * mobile app and QA have something to browse. Referenced from the API guide handed to Alaa.
 * Idempotent: safe to run again, it warns and does nothing once the demo data already exists.
 * Refuses on production (MarketplaceDemoSeeder itself also refuses, this is the first line of defence).
 *
 *   php artisan marketplace:seed-demo
 *
 * Meant to run once per environment, typically wired as an opt-in deploy step
 * (docker/entrypoint.sh, SEED_MARKETPLACE_DEMO=true) rather than by hand over SSH.
 */
class MarketplaceSeedDemoCommand extends Command
{
    protected $signature = 'marketplace:seed-demo';

    protected $description = 'DEV/TEST ONLY: seed the marketplace demo data (vendors, products, orders...). Refuses on production.';

    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('Refused: APP_ENV=production. Run marketplace reference data only (categories, shipping providers) via MarketplaceReferenceSeeder.');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => MarketplaceDemoSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
