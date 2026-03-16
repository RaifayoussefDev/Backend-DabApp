<?php

namespace App\Http\Controllers;

use App\Models\MotorcycleBrand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin Motorcycle Brands",
 *     description="Admin API Endpoints for managing motorcycle brands"
 * )
 */
class AdminMotorcycleBrandController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/motorcycle-brands",
     *     summary="Get all motorcycle brands (Admin)",
     *     tags={"Admin Motorcycle Brands"},
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
        $query = MotorcycleBrand::withCount('models');

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $perPage = $request->input('per_page');

        if ($perPage === null || $perPage === '') {
            $brands = $query->orderBy('name')->get();
        } else {
            $brands = $query->orderBy('name')->paginate((int) $perPage);
        }

        return response()->json([
            'success' => true,
            'message' => 'Motorcycle brands retrieved successfully.',
            'data' => $brands,
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/motorcycle-brands/{id}/toggle-display",
     *     summary="Toggle motorcycle brand display status (Admin)",
     *     tags={"Admin Motorcycle Brands"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Display status toggled"),
     *     @OA\Response(response=404, description="Brand not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function toggleDisplay(int $id): JsonResponse
    {
        $brand = MotorcycleBrand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Motorcycle brand not found.',
            ], 404);
        }

        $brand->is_displayed = !$brand->is_displayed;
        $brand->save();

        return response()->json([
            'success' => true,
            'message' => 'Motorcycle brand display status toggled successfully.',
            'data' => $brand,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/motorcycle-brands",
     *     summary="Create a new motorcycle brand (Admin)",
     *     tags={"Admin Motorcycle Brands"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             example={
     *                 "name": "Ducati"
     *             },
     *             @OA\Property(property="name", type="string", example="Ducati")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Motorcycle brand created"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:motorcycle_brands',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $brand = MotorcycleBrand::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Motorcycle brand created successfully.',
            'data' => $brand,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/motorcycle-brands/{id}",
     *     summary="Get a specific motorcycle brand (Admin)",
     *     tags={"Admin Motorcycle Brands"},
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
        $brand = MotorcycleBrand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Motorcycle brand not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $brand,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/motorcycle-brands/{id}",
     *     summary="Update a motorcycle brand (Admin)",
     *     tags={"Admin Motorcycle Brands"},
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
     *                 "name": "Ducati Motor Holding"
     *             },
     *             @OA\Property(property="name", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Motorcycle brand updated"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $brand = MotorcycleBrand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Motorcycle brand not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:motorcycle_brands,name,' . $id,
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
            'message' => 'Motorcycle brand updated successfully.',
            'data' => $brand,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/motorcycle-brands/{id}",
     *     summary="Delete a motorcycle brand (Admin)",
     *     tags={"Admin Motorcycle Brands"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Motorcycle brand deleted successfully"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $brand = MotorcycleBrand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Motorcycle brand not found.',
            ], 404);
        }

        $brand->delete();

        return response()->json([
            'success' => true,
            'message' => 'Motorcycle brand deleted successfully.',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/motorcycle-brands/stats/overview",
     *     summary="Get motorcycle brands statistics (Admin)",
     *     tags={"Admin Motorcycle Brands"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_brands' => MotorcycleBrand::count(),
            'brands_this_month' => MotorcycleBrand::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
