<?php

namespace App\Http\Controllers;

use App\Models\MotorcycleType;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Motorcycle Types")
 */
class MotorcycleTypeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/motorcycle-types",
     *     tags={"Motorcycle Types"},
     *     summary="Get all motorcycle types",
     *     @OA\Response(response=200, description="List of motorcycle types or no data")
     * )
     */
    public function index()
    {
        $types = MotorcycleType::all();

        if ($types->isEmpty()) {
            return response()->json([
                'message' => 'No motorcycle types found.',
                'data' => []
            ], 200);
        }

        return response()->json([
            'message' => 'Motorcycle types retrieved successfully.',
            'data' => $types
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/motorcycle-types",
     *     tags={"Motorcycle Types"},
     *     summary="Create a new motorcycle type",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Sport")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Motorcycle type created successfully")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:motorcycle_types',
        ]);

        $type = MotorcycleType::create($request->only('name'));

        return response()->json([
            'message' => 'Motorcycle type created successfully.',
            'data' => $type
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/motorcycle-types/{id}",
     *     tags={"Motorcycle Types"},
     *     summary="Get a motorcycle type by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Motorcycle type data"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function show($id)
    {
        $type = MotorcycleType::find($id);

        if (!$type) {
            return response()->json(['message' => 'Motorcycle type not found.'], 404);
        }

        return response()->json([
            'message' => 'Motorcycle type retrieved successfully.',
            'data' => $type
        ], 200);
    }

    /**
     * @OA\Put(
     *     path="/api/motorcycle-types/{id}",
     *     tags={"Motorcycle Types"},
     *     summary="Update a motorcycle type",
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
     *             @OA\Property(property="name", type="string", example="Cruiser")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Motorcycle type updated successfully"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function update(Request $request, $id)
    {
        $type = MotorcycleType::find($id);

        if (!$type) {
            return response()->json(['message' => 'Motorcycle type not found.'], 404);
        }

        $request->validate([
            'name' => 'required|unique:motorcycle_types,name,' . $id,
        ]);

        $type->update($request->only('name'));

        return response()->json([
            'message' => 'Motorcycle type updated successfully.',
            'data' => $type
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/motorcycle-types/{id}",
     *     tags={"Motorcycle Types"},
     *     summary="Delete a motorcycle type",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Motorcycle type deleted successfully"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function destroy($id)
    {
        $type = MotorcycleType::find($id);

        if (!$type) {
            return response()->json(['message' => 'Motorcycle type not found.'], 404);
        }

        $type->delete();

        return response()->json([
            'message' => 'Motorcycle type deleted successfully.'
        ], 200);
    }
}
