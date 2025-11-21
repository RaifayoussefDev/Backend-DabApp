<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PayTabsConfigService;
use Illuminate\Support\Facades\Http;

class TestPayTabs extends Command
{
    protected $signature = 'paytabs:test {--mode=test : Environment to test (test or live)}';
    protected $description = 'Test PayTabs connection and configuration';

    public function handle()
    {
        $requestedEnv = $this->option('mode');
        $currentEnv = config('paytabs.environment');

        $this->info('🔍 Testing PayTabs Configuration');
        $this->newLine();

        // Show current environment
        $this->info('Current Environment: ' . strtoupper($currentEnv));

        if ($requestedEnv !== $currentEnv) {
            $this->warn("⚠️  You requested to test '{$requestedEnv}' but .env is set to '{$currentEnv}'");
            $this->warn("    The test will use: {$currentEnv}");
            $this->newLine();
        }

        try {
            // Use the new config service
            $config = PayTabsConfigService::getConfig();
            $baseUrl = PayTabsConfigService::getBaseUrl();

            $this->info('Configuration Status:');
            $this->line("  ✅ Environment: " . strtoupper($currentEnv));
            $this->line("  ✅ Profile ID: {$config['profile_id']}");
            $this->line("  ✅ Server Key: " . substr($config['server_key'], 0, 15) . '...');
            $this->line("  ✅ Client Key: " . substr($config['client_key'], 0, 15) . '...');
            $this->line("  ✅ Currency: {$config['currency']}");
            $this->line("  ✅ Region: {$config['region']}");
            $this->line("  ✅ Base URL: {$baseUrl}");

            $this->newLine();
            $this->info('🌐 Testing PayTabs API Connection...');

            // Test payload
            $testPayload = [
                'profile_id' => (int) $config['profile_id'],
                'tran_type' => 'sale',
                'tran_class' => 'ecom',
                'cart_id' => 'test_' . time(),
                'cart_description' => 'PayTabs Connection Test - ' . strtoupper($currentEnv),
                'cart_currency' => $config['currency'],
                'cart_amount' => 1.00,
                'customer_details' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'phone' => '1234567890',
                    'street1' => 'Test Street',
                    'city' => 'Test City',
                    'state' => 'Test State',
                    'country' => $config['region'],
                    'zip' => '12345',
                    'ip' => '127.0.0.1'
                ],
                'callback' => url('/api/paytabs/callback'),
                'return' => url('/api/paytabs/return'),
                'hide_shipping' => true
            ];

            $this->line('  Sending request to: ' . $baseUrl . 'payment/request');
            $this->newLine();

            $response = Http::withHeaders([
                'Authorization' => $config['server_key'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($baseUrl . 'payment/request', $testPayload);

            $this->line('  Response Status: ' . $response->status());

            if ($response->successful()) {
                $data = $response->json();

                $this->newLine();
                $this->info('✅ PayTabs Connection Successful!');
                $this->line('   Environment: ' . strtoupper($currentEnv));
                $this->line('   Transaction Ref: ' . ($data['tran_ref'] ?? 'N/A'));
                $this->line('   Payment URL: ' . ($data['redirect_url'] ?? 'N/A'));
                $this->newLine();
                $this->info('🎉 PayTabs is ready to use in ' . strtoupper($currentEnv) . ' mode!');

                return 0;
            } else {
                $errorData = $response->json();

                $this->newLine();
                $this->error('❌ PayTabs Connection Failed!');
                $this->error('   Environment: ' . strtoupper($currentEnv));
                $this->error('   Error Code: ' . ($errorData['code'] ?? 'Unknown'));
                $this->error('   Error Message: ' . ($errorData['message'] ?? 'Unknown'));

                if (isset($errorData['trace'])) {
                    $this->line('   Trace: ' . $errorData['trace']);
                }

                $this->newLine();
                $this->warn('💡 Troubleshooting Tips:');
                $this->line('   1. Verify your credentials in .env file');
                $this->line('   2. Check if the profile is active in PayTabs dashboard');
                $this->line('   3. Ensure the region matches your PayTabs account');

                return 1;
            }

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ PayTabs Test Exception:');
            $this->error('   ' . $e->getMessage());

            $this->newLine();
            $this->warn('💡 Check your .env configuration and ensure all PAYTABS_* variables are set correctly');

            return 1;
        }
    }
}
