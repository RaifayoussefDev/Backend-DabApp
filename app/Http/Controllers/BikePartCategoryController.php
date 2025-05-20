<?php

namespace App\Http\Controllers;

use App\Models\BikePartCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BikePartCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     * swagger
     * @OA\Get(
     *     path="/api/bike-part-categories",
     *    tags={"Bike Part Categories"},
     *    summary="Get all bike part categories",
     *   @OA\Response(response=200, description="List of categories")
     * )
     */
    public function index()
    {
        return response()->json([
            'data' => BikePartCategory::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * swagger
     * @OA\Post(
     *     path="/api/bike-part-categories",
     *    tags={"Bike Part Categories"},
     *  summary="Create a new bike part category",
     *   @OA\RequestBody(
     *        required=true,
     *       @OA\JsonContent(
     *           required={"name"},
     *          @OA\Property(property="name", type="string", example="Oil")
     *       )
     *   ),
     *  @OA\Response(response=201, description="Category created successfully"),
     *  @OA\Response(response=422, description="Validation error")
     * )
     * 
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:bike_part_categories',
        ]);

        $category = BikePartCategory::create($validated);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $category
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     * swagger
     * @OA\Get(
     *    path="/api/bike-part-categories/{id}",
     *   tags={"Bike Part Categories"},
     *  summary="Get a specific bike part category",
     *   @OA\Parameter(
     *        name="id",
     *       in="path",
     *       required=true,
     *      @OA\Schema(type="integer")
     *   ),
     * @OA\Response(response=200, description="Category found"),
     * @OA\Response(response=404, description="Category not found")
     * )
     */
    public function show(BikePartCategory $bikePartCategory)
    {
        return response()->json([
            'data' => $bikePartCategory
        ]);
    }

    /**
     * Update the specified resource in storage.
     * swagger
     * @OA\Put(
     *    path="/api/bike-part-categories/{id}",
     *   tags={"Bike Part Categories"},
     *  summary="Update a bike part category",
     *  @OA\Parameter(
     *       name="id",
     *      in="path",
     *      required=true,
     *     @OA\Schema(type="integer")
     *  ),
     * @OA\RequestBody(
     *       required=true,
     *      @OA\JsonContent(
     *          required={"name"},
     *         @OA\Property(property="name", type="string", example="Oil")
     *      )
     *  ),
     * @OA\Response(response=200, description="Category updated successfully"),
     * @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, BikePartCategory $bikePartCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:bike_part_categories,name,' . $bikePartCategory->id,
        ]);

        $bikePartCategory->update($validated);

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $bikePartCategory
        ]);
    }

    /**
     * Remove the specified resource from storage.
     * swagger
     * @OA\Delete(
     *  path="/api/bike-part-categories/{id}",
     * tags={"Bike Part Categories"},
     * summary="Delete a bike part category",
     * @OA\Parameter(
     *       name="id",
     *      in="path",
     *     required=true,
     *    @OA\Schema(type="integer")
     * ),
     * @OA\Response(response=200, description="Category deleted successfully"),
     * @OA\Response(response=404, description="Category not found")
     * )
     *  
     */
    public function destroy(BikePartCategory $bikePartCategory)
    {
        $deletedData = $bikePartCategory->toArray();
        $bikePartCategory->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
            'deleted_data' => $deletedData,
            'remaining_count' => BikePartCategory::count()
        ]);
    }
}
