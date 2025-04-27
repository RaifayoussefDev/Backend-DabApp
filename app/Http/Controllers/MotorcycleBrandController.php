<?php

namespace App\Http\Controllers;

use App\Models\MotorcycleBrand;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Motorcycle Brands")
 */
class MotorcycleBrandController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/motorcycle-brands",
     *     tags={"Motorcycle Brands"},
     *     summary="Get all motorcycle brands",
     *     @OA\Response(response=200, description="List of motorcycle brands")
     * )
     */
    public function index()
    {
        $brands = MotorcycleBrand::all();

        if ($brands->isEmpty()) {
            return response()->json(['message' => 'No motorcycle brands found.'], 200);
        }

        return response()->json([
            'message' => 'Motorcycle brands retrieved successfully.',
            'data' => $brands
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/motorcycle-brands",
     *     tags={"Motorcycle Brands"},
     *     summary="Create a new motorcycle brand",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Honda")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Motorcycle brand created")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:motorcycle_brands',
        ]);

        $brand = MotorcycleBrand::create($request->only('name'));

        return response()->json([
            'message' => 'Motorcycle brand created successfully.',
            'data' => $brand
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/motorcycle-brands/{id}",
     *     tags={"Motorcycle Brands"},
     *     summary="Get a motorcycle brand by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Motorcycle brand data"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function show($id)
    {
        $brand = MotorcycleBrand::find($id);

        if (!$brand) {
            return response()->json([
                'message' => 'Motorcycle brand not found.'
            ], 404);
        }

        return response()->json([
            'message' => 'Motorcycle brand retrieved successfully.',
            'data' => $brand
        ], 200);
    }

    /**
     * @OA\Put(
     *     path="/api/motorcycle-brands/{id}",
     *     tags={"Motorcycle Brands"},
     *     summary="Update a motorcycle brand",
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
     *             @OA\Property(property="name", type="string", example="Yamaha")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Motorcycle brand updated"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function update(Request $request, $id)
    {
        $brand = MotorcycleBrand::find($id);

        if (!$brand) {
            return response()->json([
                'message' => 'Motorcycle brand not found.'
            ], 404);
        }

        $request->validate([
            'name' => 'required|unique:motorcycle_brands,name,' . $id,
        ]);

        $brand->update($request->only('name'));

        return response()->json([
            'message' => 'Motorcycle brand updated successfully.',
            'data' => $brand
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/motorcycle-brands/{id}",
     *     tags={"Motorcycle Brands"},
     *     summary="Delete a motorcycle brand",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Motorcycle brand deleted successfully"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function destroy($id)
    {
        $brand = MotorcycleBrand::find($id);

        if (!$brand) {
            return response()->json([
                'message' => 'Motorcycle brand not found.'
            ], 404);
        }

        $brand->delete();

        return response()->json([
            'message' => 'Motorcycle brand deleted successfully.'
        ], 200); // Send success message with 200 OK status
    }
}
