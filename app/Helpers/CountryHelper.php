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

    public static function formatPhone($phone, $countryCode)
    {
        $dialCode = self::getDialCode($countryCode);
        if (!$dialCode) {
            return $phone; // si pays introuvable → garder tel original
        }

        // Enlever espaces et symboles
        $cleanPhone = preg_replace('/\D+/', '', $phone);

        // Si ça commence par "0", on l’enlève
        if (substr($cleanPhone, 0, 1) === '0') {
            $cleanPhone = substr($cleanPhone, 1);
        }

        // Retourne format international
        return $dialCode . $cleanPhone;
    }
}
