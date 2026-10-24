<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'otp' => [
        'whatsapp_enabled' => env('OTP_WHATSAPP_ENABLED', true),
        'email_enabled'    => env('OTP_EMAIL_ENABLED', true),
    ],

    'aisensy' => [
        'api_key'       => env('AISENSY_API_KEY'),
        'campaign_name' => env('AISENSY_CAMPAIGN_NAME', 'DabApp'),
        'username'      => env('AISENSY_USERNAME', 'Fadel Brothers Group L.L.C'),
    ],

    // Plate rendering (headless Chrome via screenshot.cjs)
    'browsershot' => [
        'node_binary' => env('NODE_BINARY_PATH'),
        'chrome_path' => env('PUPPETEER_EXECUTABLE_PATH') ?: env('CHROME_PATH'),
    ],

    'google_sheets' => [
        'credentials_path' => env('GOOGLE_SERVICE_ACCOUNT_PATH', storage_path('app/google-service-account.json')),
    ],

];
