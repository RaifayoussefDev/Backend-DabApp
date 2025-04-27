<?php

namespace App\Http\Controllers;

use App\Models\MotorcycleYear;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Motorcycle Years")
 */
class MotorcycleYearController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/motorcycle-years",
     *     tags={"Motorcycle Years"},
     *     summary="Get all motorcycle years",
     *     @OA\Response(response=200, description="List of motorcycle years")
     * )
     */
    public function index()
    {
        return response()->json(MotorcycleYear::all());
    }

    /**
     * @OA\Post(
     *     path="/api/motorcycle-years",
     *     tags={"Motorcycle Years"},
     *     summary="Create a new motorcycle year",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"year"},
     *             @OA\Property(property="year", type="integer", example=2024)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Motorcycle year created")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|unique:motorcycle_years,year',
        ]);

        $year = MotorcycleYear::create($request->only('year'));

        return response()->json($year, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/motorcycle-years/{id}",
     *     tags={"Motorcycle Years"},
     *     summary="Get a motorcycle year by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Motorcycle year data"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function show($id)
    {
        $year = MotorcycleYear::find($id);
        if (!$year) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json($year);
    }

    /**
     * @OA\Put(
     *     path="/api/motorcycle-years/{id}",
     *     tags={"Motorcycle Years"},
     *     summary="Update a motorcycle year",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"year"},
     *             @OA\Property(property="year", type="integer", example=2025)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Motorcycle year updated"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function update(Request $request, $id)
    {
        $year = MotorcycleYear::find($id);
        if (!$year) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $request->validate([
            'year' => 'required|integer|unique:motorcycle_years,year,' . $id,
        ]);

        $year->update($request->only('year'));

        return response()->json($year);
    }

    /**
     * @OA\Delete(
     *     path="/api/motorcycle-years/{id}",
     *     tags={"Motorcycle Years"},
     *     summary="Delete a motorcycle year",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function destroy($id)
    {
        $year = MotorcycleYear::find($id);
        if (!$year) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $year->delete();

        return response()->json(null, 204);
    }
}
