<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use Illuminate\Http\Request;

class LocationController extends Controller
{
        /**
     * @OA\Get(
     *     path="/api/locations",
     *     summary="display all countries and cities",
     *     description="Get all countries and cities",
     *     operationId="getLocations",
     *    tags={"locations"},
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         description="ID of the country to filter cities",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="city_id",
     *         in="query",
     *         description="ID of the city to filter countries",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="countries",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="code", type="string"),
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="cities",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="country_id", type="integer"),
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal Server Error")
     *         )
     *     ),
     *     @OA\Tag(
     *         name="locations",
     *         description="Operations related to locations"
     *     ),
     *     @OA\Server(
     *         url="http://localhost:8000",
     *         description="Local server"
     *     ),
     *     @OA\Server(
     *         url="https://api.example.com",
     *         description="Production server"
     *     ),
     *     @OA\Server(
     *         url="https://staging.api.example.com",
     *         description="Staging server"
     *     ),
     *     @OA\Server(
     *         url="https://dev.api.example.com",
     *         description="Development server"
     *     ),
     * )
     */

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
    /**
     * @OA\Post(
     *     path="/api/countries",
     *     summary="Create a new country",
     *     description="Store a new country",
     *     operationId="storeCountry",
     *     tags={"locations"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="France"),
     *             @OA\Property(property="code", type="string", example="FR")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Country created successfully",
     * 
     *     ),
     * )
     */
    public function storeCountry(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'code' => 'nullable|string',
        ]);

        $country = Country::create($validated);

        return response()->json($country, 201);
    }

    
    /**
     * @OA\Put(
     *     path="/api/countries/{id}",
     *     summary="Update a country",
     *     description="Update a country by ID",
     *     operationId="updateCountry",
     *     tags={"locations"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the country to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="France"),
     *             @OA\Property(property="code", type="string", example="FR")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Country updated successfully"
     * 
     *    ),
     * )
     */

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

    /**
     * @OA\Delete(
     *     path="/api/countries/{id}",
     *     summary="Delete a country",
     *     description="Delete a country by ID",
     *     operationId="destroyCountry",
     *     tags={"locations"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the country to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Country deleted successfully"
     *     ),
     * )
     */
    public function destroyCountry($id)
    {
        $country = Country::findOrFail($id);
        $country->delete();

        return response()->json(null, 204);
    }

    // CRUD Cities
    /**
     * @OA\Post(
     *     path="/api/cities",
     *     summary="Create a new city",
     *     description="Store a new city",
     *     operationId="storeCity",
     *     tags={"locations"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "country_id"},
     *             @OA\Property(property="name", type="string", example="Paris"),
     *             @OA\Property(property="country_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="City created successfully"
     *     ),
     * )
     */
    public function storeCity(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'country_id' => 'required|exists:countries,id',
        ]);

        $city = City::create($validated);

        return response()->json($city, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/cities/{id}",
     *     summary="Update a city",
     *     description="Update a city by ID",
     *     operationId="updateCity",
     *     tags={"locations"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the city to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "country_id"},
     *             @OA\Property(property="name", type="string", example="Lyon"),
     *             @OA\Property(property="country_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="City updated successfully"
     *     ),
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/api/cities/{id}",
     *     summary="Delete a city",
     *     description="Delete a city by ID",
     *     operationId="destroyCity",
     *     tags={"locations"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the city to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="City deleted successfully"
     *     ),
     * )
     */
    public function destroyCity($id)
    {
        $city = City::findOrFail($id);
        $city->delete();

        return response()->json(null, 204);
    }
}

