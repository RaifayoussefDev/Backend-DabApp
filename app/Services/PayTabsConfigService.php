<?php

namespace App\Services;

use App\Models\CurrencyExchangeRate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayTabsConfigService
{
    /**
     * Get active PayTabs configuration
     */
    public static function getConfig()
    {
        $environment = config('paytabs.environment', 'test');
        $config = config("paytabs.{$environment}");

        if (!$config || !$config['profile_id'] || !$config['server_key']) {
            throw new \Exception("PayTabs {$environment} configuration is incomplete");
        }

        return $config;
    }

    /**
     * Get profile ID
     */
    public static function getProfileId()
    {
        return self::getConfig()['profile_id'];
    }

    /**
     * Get server key
     */
    public static function getServerKey()
    {
        return self::getConfig()['server_key'];
    }

    /**
     * Get client key
     */
    public static function getClientKey()
    {
        return self::getConfig()['client_key'];
    }

    /**
     * Get currency
     */
    public static function getCurrency()
    {
        return self::getConfig()['currency'];
    }

    /**
     * Get region
     */
    public static function getRegion()
    {
        return self::getConfig()['region'];
    }

    /**
     * Get base URL based on region
     */
    public static function getBaseUrl()
    {
        $region = self::getRegion();
        $endpoints = config('paytabs.endpoints');

        return $endpoints[$region] ?? $endpoints['ARE'];
    }

    /**
     * Check if currently in test mode
     */
    public static function isTestMode()
    {
        return config('paytabs.environment') === 'test';
    }

    /**
     * Check if currently in live mode
     */
    public static function isLiveMode()
    {
        return config('paytabs.environment') === 'live';
    }

    /**
     * Convert an amount into the merchant account's settlement currency (AED) using the
     * currency_exchange_rates table, where exchange_rate means "1 AED = {rate} {local currency}".
     * Prices throughout the trainer feature are entered/displayed in SAR, but this PayTabs
     * account only settles in AED — sending the raw SAR number unconverted would silently
     * charge the wrong amount. Falls back to the original amount (no conversion) if no rate
     * is configured for the given country, rather than failing the payment outright.
     */
    public static function convertToSettlementCurrency(float $amount, int $countryId = 1): float
    {
        $rate = CurrencyExchangeRate::where('country_id', $countryId)->first();

        if (!$rate || $rate->exchange_rate <= 0) {
            Log::warning("PayTabsConfigService::convertToSettlementCurrency — no exchange rate for country_id {$countryId}, sending amount unconverted", ['amount' => $amount]);
            return $amount;
        }

        $converted = round($amount / $rate->exchange_rate, 2);
        Log::info("PayTabs currency conversion: {$amount} {$rate->currency_code} → {$converted} AED (1 AED = {$rate->exchange_rate} {$rate->currency_code})");

        return $converted;
    }

    /**
     * Query PayTabs for a transaction's real status — used by mobile-SDK payment flows
     * that need to verify a `tran_ref` themselves rather than waiting on the async
     * server-to-server webhook. Tries the BACKEND profile first, then the MOBILE profile
     * (the app's native SDK may have opened the transaction under either), retrying on
     * "Transaction Not Found" (PayTabs can be briefly behind after the SDK reports success).
     * Returns the raw PayTabs response array, or null if verification could not be confirmed
     * with either profile.
     */
    public static function verifyTransaction(string $tranRef, int $retries = 3): ?array
    {
        $backendConfig = self::getConfig();
        $result = self::tryVerifyWithProfile($tranRef, $backendConfig['profile_id'], $backendConfig['server_key'], 'BACKEND', $retries);
        if ($result) {
            return $result;
        }

        $mobileConfig = config('paytabs.mobile');
        if ($mobileConfig && !empty($mobileConfig['profile_id'])) {
            $result = self::tryVerifyWithProfile($tranRef, $mobileConfig['profile_id'], $mobileConfig['server_key'], 'MOBILE', $retries);
            if ($result) {
                return $result;
            }
        }

        Log::error('PayTabsConfigService::verifyTransaction — failed with all configurations', ['tran_ref' => $tranRef]);
        return null;
    }

    private static function tryVerifyWithProfile(string $tranRef, $profileId, $serverKey, string $configType, int $retries): ?array
    {
        $baseUrl = self::getBaseUrl();
        $attempt = 0;

        while ($attempt < $retries) {
            $attempt++;
            try {
                $response = Http::timeout(15)->withHeaders([
                    'Authorization' => $serverKey,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ])->post($baseUrl . 'payment/query', [
                    'profile_id' => (int) $profileId,
                    'tran_ref'   => $tranRef,
                ]);

                if ($response->successful()) {
                    return $response->json();
                }

                $error = $response->json();
                $errorMessage = $error['message'] ?? 'Unknown error';

                if ($attempt < $retries && strpos($errorMessage, 'Transaction Not Found') !== false) {
                    sleep(2 * $attempt);
                    continue;
                }

                return null;
            } catch (\Exception $e) {
                Log::error("PayTabsConfigService::verifyTransaction exception ({$configType})", [
                    'tran_ref' => $tranRef,
                    'error'    => $e->getMessage(),
                ]);
                return null;
            }
        }

        return null;
    }
}
