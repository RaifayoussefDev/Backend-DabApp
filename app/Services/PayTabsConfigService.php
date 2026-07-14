<?php

namespace App\Services;

use App\Models\CurrencyExchangeRate;
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
}
