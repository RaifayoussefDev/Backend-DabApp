<?php

namespace App\Http\Controllers;

use App\Models\MotorcycleYear;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin Motorcycle Years",
 *     description="Admin API Endpoints for managing motorcycle years"
 * )
 */
class AdminMotorcycleYearController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/motorcycle-years",
     *     summary="Get all motorcycle years (Admin)",
     *     tags={"Admin Motorcycle Years"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by year",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page. Leave empty to get all results.",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = MotorcycleYear::query();

        if ($request->has('search') && !empty($request->search)) {
            $query->where('year', $request->search);
        }

        $perPage = $request->input('per_page');

        if ($perPage === null || $perPage === '') {
            $years = $query->orderBy('year', 'desc')->get();
        } else {
            $years = $query->orderBy('year', 'desc')->paginate((int) $perPage);
        }

        return response()->json([
            'success' => true,
            'message' => 'Motorcycle years retrieved successfully.',
            'data' => $years,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/motorcycle-years",
     *     summary="Create a new motorcycle year (Admin)",
     *     tags={"Admin Motorcycle Years"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"year"},
     *             example={
     *                 "year": 2026
     *             },
     *             @OA\Property(property="year", type="integer", example=2026)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Motorcycle year created"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|unique:motorcycle_years,year',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $year = MotorcycleYear::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Motorcycle year created successfully.',
            'data' => $year,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/motorcycle-years/{id}",
     *     summary="Get a specific motorcycle year (Admin)",
     *     tags={"Admin Motorcycle Years"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Year not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $year = MotorcycleYear::find($id);

        if (!$year) {
            return response()->json([
                'success' => false,
                'message' => 'Motorcycle year not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $year,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/motorcycle-years/{id}",
     *     summary="Update a motorcycle year (Admin)",
     *     tags={"Admin Motorcycle Years"},
     *     security={{"bearerAuth":{}}},
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
     *             example={
     *                 "year": 2027
     *             },
     *             @OA\Property(property="year", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Motorcycle year updated"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $year = MotorcycleYear::find($id);

        if (!$year) {
            return response()->json([
                'success' => false,
                'message' => 'Motorcycle year not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|unique:motorcycle_years,year,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $year->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Motorcycle year updated successfully.',
            'data' => $year,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/motorcycle-years/{id}",
     *     summary="Delete a motorcycle year (Admin)",
     *     tags={"Admin Motorcycle Years"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Motorcycle year deleted successfully"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $year = MotorcycleYear::find($id);

        if (!$year) {
            return response()->json([
                'success' => false,
                'message' => 'Motorcycle year not found.',
            ], 404);
        }

        $year->delete();

        return response()->json([
            'success' => true,
            'message' => 'Motorcycle year deleted successfully.',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/motorcycle-years/stats/overview",
     *     summary="Get motorcycle years statistics (Admin)",
     *     tags={"Admin Motorcycle Years"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_years' => MotorcycleYear::count(),
            'latest_year' => MotorcycleYear::max('year'),
            'oldest_year' => MotorcycleYear::min('year'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
