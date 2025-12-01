<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PayTabs Active Environment
    |--------------------------------------------------------------------------
    */
    'environment' => env('PAYTABS_ENVIRONMENT', 'test'),

    /*
    |--------------------------------------------------------------------------
    | PayTabs Test Configuration (Backend)
    |--------------------------------------------------------------------------
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
    | PayTabs Live Configuration (Backend)
    |--------------------------------------------------------------------------
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
    | PayTabs Mobile SDK Configuration (Separate Keys)
    |--------------------------------------------------------------------------
    */
    'mobile' => [
        'profile_id' => env('PAYTABS_MOBILE_PROFILE_ID'),
        'server_key' => env('PAYTABS_MOBILE_SERVER_KEY'),
        'client_key' => env('PAYTABS_MOBILE_CLIENT_KEY'),
        'currency'   => env('PAYTABS_MOBILE_CURRENCY', 'AED'),
        'region'     => env('PAYTABS_MOBILE_REGION', 'ARE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PayTabs Regional Endpoints
    |--------------------------------------------------------------------------
    */
    'endpoints' => [
        'ARE'    => 'https://secure.paytabs.com/',
        'SAU'    => 'https://secure.paytabs.sa/',
        'OMN'    => 'https://secure-oman.paytabs.com/',
        'JOR'    => 'https://secure-jordan.paytabs.com/',
        'EGY'    => 'https://secure-egypt.paytabs.com/',
        'GLOBAL' => 'https://secure-global.paytabs.com/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Payment Methods by Currency
    |--------------------------------------------------------------------------
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
