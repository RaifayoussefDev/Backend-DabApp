<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BikePartBrand;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BikePartBrandController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'data' => BikePartBrand::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:bike_part_brands',
        ]);

        $brand = BikePartBrand::create($validated);

        return response()->json([
            'message' => 'Brand created successfully',
            'data' => $brand
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(BikePartBrand $bikePartBrand)
    {
        return response()->json([
            'data' => $bikePartBrand
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BikePartBrand $bikePartBrand)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:bike_part_brands,name,'.$bikePartBrand->id,
        ]);

        $bikePartBrand->update($validated);

        return response()->json([
            'message' => 'Brand updated successfully',
            'data' => $bikePartBrand
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BikePartBrand $bikePartBrand)
    {
        // Sauvegarder les données avant suppression si besoin
        $deletedData = $bikePartBrand->toArray();
        
        $bikePartBrand->delete();
    
        return response()->json([
            'message' => 'Brand deleted successfully',
            'deleted_data' => $deletedData, // Optionnel : données supprimées
            'remaining_count' => BikePartBrand::count() // Optionnel : compte restant
        ]);
    }
}