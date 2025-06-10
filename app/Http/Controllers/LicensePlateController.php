<?php

namespace App\Http\Controllers;

use App\Models\LicensePlate;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LicensePlateController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'listing_id' => 'required|exists:listings,id',
            'characters' => 'required|string|unique:license_plates,characters',
            'country_id' => 'required|exists:countries,id',
            'city_id' => 'required|exists:cities,id',
            'type_id' => 'required|exists:plate_types,id',
             'digits_count' => 'required|integer',
            'first_letter' => 'nullable|string|max:1',
            'second_letter' => 'nullable|string|max:1',
            'third_letter' => 'nullable|string|max:1',
            'numbers' => 'required|string'
        ]);

        $plate = LicensePlate::create($request->all());

        return response()->json([
            'message' => 'License plate created successfully',
            'data' => $plate
        ], 201);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }


    /**
     * Display the specified resource.
     */
    public function show(LicensePlate $licensePlate)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LicensePlate $licensePlate)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LicensePlate $licensePlate)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LicensePlate $licensePlate)
    {
        //
    }
}
