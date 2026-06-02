<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes all provider + subscription + booking data for a clean test start.
 * Reference tables (subscription_plans, tow_types, service_categories) are kept intact.
 * Users are NOT deleted.
 *
 * Usage:
 *   php artisan db:seed --class=ResetProviderDataSeeder
 */
class ResetProviderDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('');
        $this->command->warn('⚠️  This will delete ALL provider, subscription, and booking data.');
        $this->command->warn('   Users, plans, tow types, and service categories are kept.');
        $this->command->warn('');

        if ($this->command->confirm('Continue?', false) === false) {
            $this->command->info('Aborted.');
            return;
        }

        // Show counts before
        $this->command->info('--- Before ---');
        $this->printCounts();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $this->truncateIfExists('chat_sessions');
            $this->truncateIfExists('service_reviews');
            $this->truncateIfExists('service_bookings');
            $this->truncateIfExists('transport_route_stops');
            $this->truncateIfExists('transport_routes');
            $this->truncateIfExists('instructor_locations');
            $this->truncateIfExists('riding_instructors');
            $this->truncateIfExists('provider_service_categories');
            $this->truncateIfExists('provider_working_hours');
            $this->truncateIfExists('service_provider_images');
            $this->truncateIfExists('subscription_transactions');
            $this->truncateIfExists('service_subscriptions');
            $this->truncateIfExists('services');
            $this->truncateIfExists('service_providers');


        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Show counts after
        $this->command->info('');
        $this->command->info('--- After ---');
        $this->printCounts();

        $this->command->info('');
        $this->command->info('✅ Reset complete. Ready to test from scratch.');
    }

    private function truncateIfExists(string $table): void
    {
        if (Schema::hasTable($table)) {
            DB::table($table)->truncate();
            $this->command->line("✅ {$table} cleared");
        } else {
            $this->command->line("⚠️  {$table} skipped (table not found)");
        }
    }

    private function printCounts(): void
    {
        $tables = [
            'service_providers',
            'service_subscriptions',
            'services',
            'service_bookings',
            'service_reviews',
            'riding_instructors',
            'transport_routes',
            'provider_working_hours',
            'subscription_transactions',
        ];

        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            $this->command->line("  {$table}: {$count}");
        }
    }
}
