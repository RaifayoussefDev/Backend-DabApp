<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PayTabs Active Environment
    |--------------------------------------------------------------------------
    |
    | This value determines which PayTabs configuration to use.
    | Supported: "test", "live"
    |
    */
    'environment' => env('PAYTABS_ENVIRONMENT', 'test'),

    /*
    |--------------------------------------------------------------------------
    | PayTabs Test Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for testing and development
    |
    */
    'test' => [
        'profile_id' => env('PAYTABS_TEST_PROFILE_ID'),
        'server_key' => env('PAYTABS_TEST_SERVER_KEY'),
        'client_key' => env('PAYTABS_TEST_CLIENT_KEY'),
        'currency'   => env('PAYTABS_TEST_CURRENCY', 'AED'),
        'region'     => env('PAYTABS_TEST_REGION', 'ARE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PayTabs Live Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for production
    |
    */
    'live' => [
        'profile_id' => env('PAYTABS_LIVE_PROFILE_ID'),
        'server_key' => env('PAYTABS_LIVE_SERVER_KEY'),
        'client_key' => env('PAYTABS_LIVE_CLIENT_KEY'),
        'currency'   => env('PAYTABS_LIVE_CURRENCY', 'AED'),
        'region'     => env('PAYTABS_LIVE_REGION', 'ARE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PayTabs Regional Endpoints
    |--------------------------------------------------------------------------
    |
    | Base URLs for different PayTabs regions
    |
    */
    'endpoints' => [
        'ARE'    => 'https://secure.paytabs.com/',      // United Arab Emirates
        'SAU'    => 'https://secure.paytabs.sa/',       // Saudi Arabia
        'OMN'    => 'https://secure-oman.paytabs.com/', // Oman
        'JOR'    => 'https://secure-jordan.paytabs.com/', // Jordan
        'EGY'    => 'https://secure-egypt.paytabs.com/', // Egypt
        'GLOBAL' => 'https://secure-global.paytabs.com/', // Global
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Payment Methods by Currency
    |--------------------------------------------------------------------------
    |
    | Available payment methods for each currency
    |
    */
    'payment_methods' => [
        'AED' => [
            'creditcard' => 'Credit Card',
            'mada'       => 'Mada',
            'applepay'   => 'Apple Pay',
            'stcpay'     => 'STC Pay',
            'omannet'    => 'OmanNet',
            'sadad'      => 'Sadad',
        ],
        'SAR' => [
            'creditcard' => 'Credit Card',
            'mada'       => 'Mada',
            'applepay'   => 'Apple Pay',
            'stcpay'     => 'STC Pay',
            'sadad'      => 'Sadad',
        ],
        'USD' => [
            'creditcard' => 'Credit Card',
            'applepay'   => 'Apple Pay',
        ],
    ],
];
