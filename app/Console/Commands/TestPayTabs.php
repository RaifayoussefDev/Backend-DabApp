<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PayTabsService;
use Illuminate\Support\Facades\Http;

class TestPayTabs extends Command
{
    protected $signature = 'paytabs:test';
    protected $description = 'Test PayTabs connection and configuration';

    public function handle()
    {
        $this->info('🔍 Testing PayTabs configuration...');
        $this->newLine();

        // Vérifier la configuration
        $payTabsService = new PayTabsService();
        $config = $payTabsService->getConfig();

        $this->info('Configuration Status:');
        foreach ($config as $key => $value) {
            if ($key === 'supported_payment_methods') {
                continue; // Skip this for now, we'll display it separately
            }
            $status = $value === 'Missing' ? '❌' : '✅';
            $displayValue = is_array($value) ? json_encode($value) : $value;
            $this->line("  {$status} {$key}: {$displayValue}");
        }

        // Display supported payment methods separately
        if (isset($config['supported_payment_methods'])) {
            $this->newLine();
            $this->info('Supported Payment Methods for ' . $config['currency'] . ':');
            foreach ($config['supported_payment_methods'] as $method => $title) {
                $this->line("  ✅ {$method}: {$title}");
            }
        }

        // Show actual config values for debugging
        $this->newLine();
        $this->info('🔍 Debug Information:');
        $this->line('  Profile ID: ' . config('paytabs.profile_id'));
        $this->line('  Server Key: ' . substr(config('paytabs.server_key'), 0, 10) . '...');
        $this->line('  Region: ' . config('paytabs.region'));
        $this->line('  Currency: ' . config('paytabs.currency'));
        $this->line('  Base URL: ' . config('paytabs.base_url'));

        if (in_array('Missing', $config)) {
            $this->newLine();
            $this->error('❌ PayTabs configuration is incomplete!');
            $this->info('Please check your .env file and ensure all PAYTABS_* variables are set.');
            return 1;
        }

        $this->newLine();
        $this->info('🌐 Testing PayTabs API connection...');

        try {
            // Test with minimal payload
            $testPayload = [
                'profile_id' => (int) config('paytabs.profile_id'),
                'tran_type' => 'sale',
                'tran_class' => 'ecom',
                'cart_id' => 'test_' . time(),
                'cart_description' => 'PayTabs Connection Test',
                'cart_currency' => config('paytabs.currency'),
                'cart_amount' => 1.00,
                'customer_details' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'phone' => '1234567890',
                    'street1' => 'Test Street',
                    'city' => 'Test City',
                    'state' => 'Test State',
                    'country' => config('paytabs.region'),
                    'zip' => '12345',
                    'ip' => '127.0.0.1'
                ],
                'callback' => 'http://localhost/callback',
                'return' => 'http://localhost/return',
                'hide_shipping' => true
            ];

            $this->line('  Payload: ' . json_encode($testPayload, JSON_PRETTY_PRINT));
            $this->newLine();

            // Use the correct endpoint based on region
            $baseUrls = [
                'ARE' => 'https://secure.paytabs.com/',
                'SAU' => 'https://secure.paytabs.sa/',
                'OMN' => 'https://secure-oman.paytabs.com/',
                'JOR' => 'https://secure-jordan.paytabs.com/',
                'EGY' => 'https://secure-egypt.paytabs.com/',
                'GLOBAL' => 'https://secure-global.paytabs.com/'
            ];

            $region = config('paytabs.region', 'ARE');
            $baseUrl = $baseUrls[$region] ?? $baseUrls['ARE'];

            $this->line('  Using endpoint: ' . $baseUrl . 'payment/request');

            $response = Http::withHeaders([
                'Authorization' => config('paytabs.server_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($baseUrl . 'payment/request', $testPayload);

            $this->line('  Response Status: ' . $response->status());
            $this->line('  Response Body: ' . $response->body());

            if ($response->successful()) {
                $data = $response->json();
                $this->info('✅ PayTabs connection successful!');
                $this->line('   Payment URL available: ' . ($data['redirect_url'] ?? 'N/A'));
                $this->line('   Transaction ref: ' . ($data['tran_ref'] ?? 'N/A'));
                $this->newLine();
                $this->info('🎉 PayTabs is ready to use!');
                return 0;
            } else {
                $this->error('❌ PayTabs connection failed!');
                $errorData = $response->json();
                $this->error('   Error Code: ' . ($errorData['code'] ?? 'Unknown'));
                $this->error('   Error Message: ' . ($errorData['message'] ?? 'Unknown'));
                if (isset($errorData['trace'])) {
                    $this->line('   Trace: ' . $errorData['trace']);
                }
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('❌ PayTabs test exception: ' . $e->getMessage());
            return 1;
        }
    }
}
