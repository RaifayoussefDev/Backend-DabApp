<?php

namespace App\Helpers;

class CountryHelper
{
    public static function getDialCode($countryCode)
    {
        $path = storage_path('app/countries.json');
        if (!file_exists($path)) {
            return null;
        }

        $countries = json_decode(file_get_contents($path), true);

        foreach ($countries as $country) {
            if (strtoupper($country['code']) === strtoupper($countryCode)) {
                return $country['dial_code'];
            }
        }

        return null;
    }

    /**
     * Format phone number based on country code
     *
     * Examples:
     * - SA + "0688808238" → "+966688808238"
     * - SA + "966688808238" → "+966688808238" (already has code)
     * - MA + "212688808080" → "+212688808080" (already has code)
     * - MA + "0688808238" → "+212688808238"
     */
    public static function formatPhone($phone, $countryCode)
    {
        $dialCode = self::getDialCode($countryCode);
        if (!$dialCode) {
            return $phone; // If country not found → keep original phone
        }

        // Remove all non-digits
        $cleanPhone = preg_replace('/\D+/', '', $phone);

        // Get dial code without the + sign for comparison
        $dialCodeWithoutPlus = ltrim($dialCode, '+');

        // ✅ Check if phone already starts with country code
        if (str_starts_with($cleanPhone, $dialCodeWithoutPlus)) {
            return '+' . $cleanPhone;
        }

        // ✅ Remove leading 0 if present (local format like 0688808238)
        if (substr($cleanPhone, 0, 1) === '0') {
            $cleanPhone = substr($cleanPhone, 1);
        }

        // ✅ Return with country dial code
        return $dialCode . $cleanPhone;
    }

    /**
     * Format phone number based on country NAME (like "Saudi Arabia")
     */
    public static function formatPhoneByCountryName($phone, $countryName)
    {
        $country = self::getCountryInfoByName($countryName);
        if (!$country) {
            return $phone; // If country not found → keep original phone
        }

        // Remove all non-digits
        $cleanPhone = preg_replace('/\D+/', '', $phone);

        // Get dial code without the + sign for comparison
        $dialCodeWithoutPlus = ltrim($country['dial_code'], '+');

        // ✅ Check if phone already starts with country code
        if (str_starts_with($cleanPhone, $dialCodeWithoutPlus)) {
            return '+' . $cleanPhone;
        }

        // ✅ Remove leading 0 if present (local format like 0688808238)
        if (substr($cleanPhone, 0, 1) === '0') {
            $cleanPhone = substr($cleanPhone, 1);
        }

        // ✅ Return with country dial code
        return $country['dial_code'] . $cleanPhone;
    }

    /**
     * Get country information from JSON file by NAME
     */
    public static function getCountryInfoByName($countryName)
    {
        $path = storage_path('app/countries.json');
        if (!file_exists($path)) {
            return null;
        }

        $countries = json_decode(file_get_contents($path), true);

        return collect($countries)->firstWhere('name', $countryName);
    }

    /**
     * Get country information from JSON file
     */
    public static function getCountryInfo($countryCode)
    {
        $path = storage_path('app/countries.json');
        if (!file_exists($path)) {
            return null;
        }

        $countries = json_decode(file_get_contents($path), true);

        return collect($countries)->firstWhere('code', strtoupper($countryCode));
    }

    /**
     * Get country ID from database by country CODE
     */
    public static function getCountryIdFromDb($countryCode)
    {
        try {
            $dbCountry = \DB::table('countries')
                ->select('id')
                ->where('code', strtoupper($countryCode))
                ->first();

            return $dbCountry ? $dbCountry->id : null;
        } catch (\Exception $e) {
            \Log::error('Error getting country from database by code', [
                'country_code' => $countryCode,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get country ID from database by country NAME
     */
    public static function getCountryIdFromDbByName($countryName)
    {
        try {
            $dbCountry = \DB::table('countries')
                ->select('id')
                ->where('name', $countryName)
                ->first();

            return $dbCountry ? $dbCountry->id : null;
        } catch (\Exception $e) {
            \Log::error('Error getting country from database by name', [
                'country_name' => $countryName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Comprehensive method to handle country and phone formatting BY NAME
     */
    public static function processCountryAndPhoneByName($phone, $countryName)
    {
        // Get country info from JSON by NAME
        $countryInfo = self::getCountryInfoByName($countryName);

        // Get country ID from DB by NAME (can be null)
        $countryId = self::getCountryIdFromDbByName($countryName);

        // Format phone using JSON data
        $formattedPhone = $countryInfo
            ? self::formatPhoneByCountryName($phone, $countryName)
            : $phone;

        return [
            'country_info' => $countryInfo,
            'country_id' => $countryId, // ✅ Can be NULL
            'country_code' => $countryInfo ? $countryInfo['code'] : null,
            'formatted_phone' => $formattedPhone,
            'country_name' => $countryName
        ];
    }

    /**
     * Comprehensive method to handle country and phone formatting
     */
    public static function processCountryAndPhone($phone, $countryCode)
    {
        // Get country info from JSON (always works)
        $countryInfo = self::getCountryInfo($countryCode);

        // Get country ID from DB (can be null)
        $countryId = self::getCountryIdFromDb($countryCode);

        // Format phone using JSON data
        $formattedPhone = $countryInfo
            ? self::formatPhone($phone, $countryCode)
            : $phone;

        return [
            'country_info' => $countryInfo,
            'country_id' => $countryId, // ✅ Can be NULL
            'formatted_phone' => $formattedPhone,
            'country_name' => $countryInfo ? $countryInfo['name'] : 'Unknown'
        ];
    }
}
