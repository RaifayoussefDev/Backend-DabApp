<?php

namespace App\Http\Controllers;

use App\Models\BikePartBrand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;

/**
 * @OA\Tag(
 *     name="Admin Bike Part Brands",
 *     description="Admin API Endpoints for managing bike part brands"
 * )
 */
class AdminBikePartBrandController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/bike-part-brands",
     *     summary="Get all bike part brands (Admin)",
     *     tags={"Admin Bike Part Brands"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by brand name",
     *         required=false,
     *         @OA\Schema(type="string")
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
        $query = BikePartBrand::query();

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $query->orderByRaw("CASE WHEN name = 'Other' THEN 1 ELSE 0 END, name ASC");

        $perPage = $request->input('per_page');

        if ($perPage === null || $perPage === '') {
            $brands = $query->get();
        } else {
            $brands = $query->paginate((int) $perPage);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bike part brands retrieved successfully.',
            'data' => $brands,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/bike-part-brands",
     *     summary="Create a new bike part brand (Admin)",
     *     tags={"Admin Bike Part Brands"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             example={
     *                 "name": "Shimano"
     *             },
     *             @OA\Property(property="name", type="string", example="Shimano")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Brand created successfully"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:bike_part_brands',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $brand = BikePartBrand::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Brand created successfully',
            'data' => $brand,
        ], Response::HTTP_CREATED);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/bike-part-brands/{id}",
     *     summary="Get a specific bike part brand (Admin)",
     *     tags={"Admin Bike Part Brands"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Brand not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $brand = BikePartBrand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $brand,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/bike-part-brands/{id}",
     *     summary="Update a bike part brand (Admin)",
     *     tags={"Admin Bike Part Brands"},
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
     *             required={"name"},
     *             example={
     *                 "name": "SRAM"
     *             },
     *             @OA\Property(property="name", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Brand updated successfully"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $brand = BikePartBrand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:bike_part_brands,name,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $brand->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Brand updated successfully',
            'data' => $brand,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/bike-part-brands/{id}",
     *     summary="Delete a bike part brand (Admin)",
     *     tags={"Admin Bike Part Brands"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Brand deleted successfully"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $brand = BikePartBrand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found',
            ], 404);
        }

        $deletedData = $brand->toArray();
        $brand->delete();

        return response()->json([
            'success' => true,
            'message' => 'Brand deleted successfully',
            'deleted_data' => $deletedData,
            'remaining_count' => BikePartBrand::count()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/bike-part-brands/stats/overview",
     *     summary="Get bike part brands statistics (Admin)",
     *     tags={"Admin Bike Part Brands"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_brands' => BikePartBrand::count(),
            'brands_this_month' => BikePartBrand::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
