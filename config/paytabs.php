<?php

return [
    'profile_id' => env('PAYTABS_PROFILE_ID'),
    'server_key' => env('PAYTABS_SERVER_KEY'),
    'client_key' => env('PAYTABS_CLIENT_KEY'),
    'currency'   => env('PAYTABS_CURRENCY', 'USD'),
    'region'     => env('PAYTABS_REGION', 'ARE'), // ARE, SAU, OMN, JOR, EGY
    'base_url'   => env('PAYTABS_BASE_URL', 'https://secure.paytabs.com'),
];
