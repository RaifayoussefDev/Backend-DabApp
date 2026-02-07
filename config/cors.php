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
        'http://localhost:4200',
        'http://192.168.11.184:5500',
        'http://localhost:5500',
        'https://dabapp-frontend-new.pages.dev',
        'http://localhost:8000',
        'https://dabapp-adminboard.vercel.app',
        'adminboard.dabapp.co',
    ],

    'allowed_origins_patterns' => [
        '/^https?:\/\/([\w-]+\.)?dabapp\.co$/',  // Accepte dabapp.co et tous ses sous-domaines (http et https)
    ],

    'allowed_headers' => ['*'],  // Allow all headers

    'exposed_headers' => [],  // Optional: Expose specific headers for frontend apps

    'max_age' => 0,  // Pre-flight request cache for 0 seconds

    'supports_credentials' => true,  // Enable sending credentials (cookies, HTTP authentication)
];
