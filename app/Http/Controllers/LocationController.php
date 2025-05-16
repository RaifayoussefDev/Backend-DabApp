<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    // GET /api/locations?country_id=1 OR ?city_id=2
    public function index(Request $request)
    {
        $countryId = $request->query('country_id');
        $cityId = $request->query('city_id');

        if ($countryId) {
            $cities = City::where('country_id', $countryId)->get();
            $countries = Country::all();
        } elseif ($cityId) {
            $city = City::with('country')->findOrFail($cityId);
            $countries = Country::where('id', $city->country_id)->get();
            $cities = City::all();
        } else {
            $countries = Country::all();
            $cities = City::all();
        }

        return response()->json([
            'countries' => $countries,
            'cities' => $cities,
        ]);
    }

    // CRUD Countries

    public function storeCountry(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'code' => 'nullable|string',
        ]);

        $country = Country::create($validated);

        return response()->json($country, 201);
    }

    public function updateCountry(Request $request, $id)
    {
        $country = Country::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string',
            'code' => 'nullable|string',
        ]);

        $country->update($validated);

        return response()->json($country);
    }

    public function destroyCountry($id)
    {
        $country = Country::findOrFail($id);
        $country->delete();

        return response()->json(null, 204);
    }

    // CRUD Cities

    public function storeCity(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'country_id' => 'required|exists:countries,id',
        ]);

        $city = City::create($validated);

        return response()->json($city, 201);
    }

    public function updateCity(Request $request, $id)
    {
        $city = City::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string',
            'country_id' => 'required|exists:countries,id',
        ]);

        $city->update($validated);

        return response()->json($city);
    }

    public function destroyCity($id)
    {
        $city = City::findOrFail($id);
        $city->delete();

        return response()->json(null, 204);
    }
}
