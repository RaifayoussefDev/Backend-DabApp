<?php

namespace App\Http\Controllers;

use App\Models\BikePartBrand;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @OA\Tag(
 *     name="Bike Part Brands",
 *     description="API Endpoints for managing bike part brands"
 * )
 */
class BikePartBrandController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/bike-part-brands",
     *     tags={"Bike Part Brands"},
     *     summary="Get all bike part brands",
     *     @OA\Response(response=200, description="List of brands")
     * )
     */
    public function index()
    {
        return response()->json([
            'data' => BikePartBrand::all()
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/bike-part-brands",
     *     tags={"Bike Part Brands"},
     *     summary="Create a new bike part brand",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Shimano")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Brand created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
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
     * @OA\Get(
     *     path="/api/bike-part-brands/{id}",
     *     tags={"Bike Part Brands"},
     *     summary="Get a specific bike part brand",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Brand found"),
     *     @OA\Response(response=404, description="Brand not found")
     * )
     */
    public function show(BikePartBrand $bikePartBrand)
    {
        return response()->json([
            'data' => $bikePartBrand
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/bike-part-brands/{id}",
     *     tags={"Bike Part Brands"},
     *     summary="Update a bike part brand",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Updated Brand")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Brand updated successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, BikePartBrand $bikePartBrand)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:bike_part_brands,name,' . $bikePartBrand->id,
        ]);

        $bikePartBrand->update($validated);

        return response()->json([
            'message' => 'Brand updated successfully',
            'data' => $bikePartBrand
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/bike-part-brands/{id}",
     *     tags={"Bike Part Brands"},
     *     summary="Delete a bike part brand",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Brand deleted successfully"),
     *     @OA\Response(response=404, description="Brand not found")
     * )
     */
    public function destroy(BikePartBrand $bikePartBrand)
    {
        $deletedData = $bikePartBrand->toArray();
        $bikePartBrand->delete();

        return response()->json([
            'message' => 'Brand deleted successfully',
            'deleted_data' => $deletedData,
            'remaining_count' => BikePartBrand::count()
        ]);
    }
}
