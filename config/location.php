<?php

return [
    'default_country' => env('DEFAULT_COUNTRY', 'Morocco'),
    'default_continent' => env('DEFAULT_CONTINENT', 'Africa'),
    'country_header' => 'HTTP_X_FORWARDED_COUNTRY',
    'continent_header' => 'HTTP_X_FORWARDED_CONTINENT',

    'timezones' => [
        // 🌍 PAYS ARABES / ARAB COUNTRIES
        'Morocco' => 'Africa/Casablanca',
        'Algeria' => 'Africa/Algiers',
        'Tunisia' => 'Africa/Tunis',
        'Libya' => 'Africa/Tripoli',
        'Egypt' => 'Africa/Cairo',
        'Sudan' => 'Africa/Khartoum',
        'Mauritania' => 'Africa/Nouakchott',
        'Somalia' => 'Africa/Mogadishu',
        'Djibouti' => 'Africa/Djibouti',
        'Comoros' => 'Indian/Comoro',

        'Saudi Arabia' => 'Asia/Riyadh',
        'UAE' => 'Asia/Dubai',
        'United Arab Emirates' => 'Asia/Dubai',
        'Kuwait' => 'Asia/Kuwait',
        'Bahrain' => 'Asia/Bahrain',
        'Qatar' => 'Asia/Qatar',
        'Oman' => 'Asia/Muscat',
        'Yemen' => 'Asia/Aden',
        'Iraq' => 'Asia/Baghdad',
        'Jordan' => 'Asia/Amman',
        'Lebanon' => 'Asia/Beirut',
        'Syria' => 'Asia/Damascus',
        'Palestine' => 'Asia/Gaza',

        // 🌍 AFRIQUE
        'South Africa' => 'Africa/Johannesburg',
        'Nigeria' => 'Africa/Lagos',
        'Kenya' => 'Africa/Nairobi',
        'Ethiopia' => 'Africa/Addis_Ababa',
        'Ghana' => 'Africa/Accra',
        'Tanzania' => 'Africa/Dar_es_Salaam',

        // 🌍 EUROPE
        'France' => 'Europe/Paris',
        'Germany' => 'Europe/Berlin',
        'UK' => 'Europe/London',
        'United Kingdom' => 'Europe/London',
        'Spain' => 'Europe/Madrid',
        'Italy' => 'Europe/Rome',
        'Netherlands' => 'Europe/Amsterdam',
        'Belgium' => 'Europe/Brussels',
        'Switzerland' => 'Europe/Zurich',
        'Turkey' => 'Europe/Istanbul',
        'Russia' => 'Europe/Moscow',

        // 🌏 ASIE
        'China' => 'Asia/Shanghai',
        'Japan' => 'Asia/Tokyo',
        'India' => 'Asia/Kolkata',
        'South Korea' => 'Asia/Seoul',
        'Thailand' => 'Asia/Bangkok',
        'Vietnam' => 'Asia/Ho_Chi_Minh',
        'Indonesia' => 'Asia/Jakarta',
        'Malaysia' => 'Asia/Kuala_Lumpur',
        'Singapore' => 'Asia/Singapore',
        'Philippines' => 'Asia/Manila',
        'Pakistan' => 'Asia/Karachi',
        'Bangladesh' => 'Asia/Dhaka',

        // 🌎 AMÉRIQUE
        'USA' => 'America/New_York',
        'United States' => 'America/New_York',
        'Canada' => 'America/Toronto',
        'Mexico' => 'America/Mexico_City',
        'Brazil' => 'America/Sao_Paulo',
        'Argentina' => 'America/Argentina/Buenos_Aires',

        // 🌏 OCÉANIE
        'Australia' => 'Australia/Sydney',
        'New Zealand' => 'Pacific/Auckland',

        // FALLBACK
        'Unknown' => 'UTC',
    ],
];
