<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;


abstract class Controller
{
    //
    public function getCountries()
    {
        $response = Http::withHeaders([
            'X-RapidAPI-Key' => '67301d5de3msh12e6fc2e7d022d4p1d74b0jsn8fd8b36e8546',
            'X-RapidAPI-Host' => 'wft-geo-db.p.rapidapi.com'
        ])->get('https://wft-geo-db.p.rapidapi.com/v1/geo/countries');

        return $response->json();
    }

    public function getCities($countryCode)
    {
        $response = Http::withHeaders([
            'X-RapidAPI-Key' => '67301d5de3msh12e6fc2e7d022d4p1d74b0jsn8fd8b36e8546',
            'X-RapidAPI-Host' => 'wft-geo-db.p.rapidapi.com'
        ])->get("https://wft-geo-db.p.rapidapi.com/v1/geo/countries/{$countryCode}/cities");

        return $response->json();
    }
}
