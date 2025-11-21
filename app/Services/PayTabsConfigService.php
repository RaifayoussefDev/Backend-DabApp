<?php

namespace App\Services;

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
}
