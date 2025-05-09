<?php

return [

    /*
    |----------------------------------------------------------------------
    | Laravel CORS Options
    |----------------------------------------------------------------------
    |
    | Here you may configure your settings for Cross-Origin Resource Sharing
    | (CORS). By default, the settings allow any origin to access your API
    | but you can adjust these settings to meet your needs.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],  // Paths that are allowed for CORS

    'allowed_methods' => ['*'],  // Allow all HTTP methods (GET, POST, etc.)

    'allowed_origins' => [
        'https://dabapp.co',
        'http://localhost:4200',
<<<<<<< HEAD
        'http://192.168.11.184:5500',  // Specify the trusted origin(s)
        'http://localhost:5500',
        'http://192.168.11.186:4200'
    ], 
=======
    ],
>>>>>>> 10c78591c5400e764e83c06b1d796360964dc7b6

    'allowed_headers' => ['*'],  // Allow all headers

    'exposed_headers' => [],  // Optional: Expose specific headers for frontend apps

    'max_age' => 0,  // Pre-flight request cache for 0 seconds

    'supports_credentials' => true,
];
