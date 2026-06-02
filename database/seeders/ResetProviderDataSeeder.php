<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
            // 1. Chat sessions (linked to bookings)
            DB::table('chat_sessions')->truncate();
            $this->command->line('✅ chat_sessions cleared');

            // 2. Reviews (linked to bookings + providers)
            DB::table('service_reviews')->truncate();
            $this->command->line('✅ service_reviews cleared');

            // 3. Bookings
            DB::table('service_bookings')->truncate();
            $this->command->line('✅ service_bookings cleared');

            // 4. Transport route stops → routes
            DB::table('transport_route_stops')->truncate();
            DB::table('transport_routes')->truncate();
            $this->command->line('✅ transport_routes cleared');

            // 5. Instructor locations → instructors
            DB::table('instructor_locations')->truncate();
            DB::table('riding_instructors')->truncate();
            $this->command->line('✅ riding_instructors cleared');

            // 6. Provider pivot + working hours + images
            DB::table('provider_service_categories')->truncate();
            DB::table('provider_working_hours')->truncate();
            DB::table('service_provider_images')->truncate();
            $this->command->line('✅ provider metadata cleared');

            // 7. Subscription transactions
            DB::table('subscription_transactions')->truncate();
            $this->command->line('✅ subscription_transactions cleared');

            // 8. Subscriptions
            DB::table('service_subscriptions')->truncate();
            $this->command->line('✅ service_subscriptions cleared');

            // 9. Subscription-related payments only
            $deleted = DB::table('payments')
                ->where('cart_id', 'like', 'sub_%')
                ->delete();
            $this->command->line("✅ payments (subscription) deleted: {$deleted}");

            // 10. Services
            DB::table('services')->truncate();
            $this->command->line('✅ services cleared');

            // 11. Providers (last — everything else references this)
            DB::table('service_providers')->truncate();
            $this->command->line('✅ service_providers cleared');

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
            'payments',
        ];

        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            $this->command->line("  {$table}: {$count}");
        }
    }
}
