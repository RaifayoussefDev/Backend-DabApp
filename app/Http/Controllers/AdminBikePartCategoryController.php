<?php

namespace App\Http\Controllers;

use App\Models\BikePartCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;

/**
 * @OA\Tag(
 *     name="Admin Bike Part Categories",
 *     description="Admin API Endpoints for managing bike part categories"
 * )
 */
class AdminBikePartCategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/bike-part-categories",
     *     summary="Get all bike part categories (Admin)",
     *     tags={"Admin Bike Part Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by category name",
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
        $query = BikePartCategory::query();

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('name_ar', 'LIKE', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page');

        if ($perPage === null || $perPage === '') {
            $categories = $query->get();
        } else {
            $categories = $query->paginate((int) $perPage);
        }

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/bike-part-categories",
     *     summary="Create a new bike part category (Admin)",
     *     tags={"Admin Bike Part Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             example={
     *                 "name": "Brakes",
     *                 "name_ar": "فرامل"
     *             },
     *             @OA\Property(property="name", type="string", example="Brakes"),
     *             @OA\Property(property="name_ar", type="string", example="فرامل")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Category created successfully"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:bike_part_categories',
            'name_ar' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = BikePartCategory::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category,
        ], Response::HTTP_CREATED);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/bike-part-categories/{id}",
     *     summary="Get a specific bike part category (Admin)",
     *     tags={"Admin Bike Part Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $category = BikePartCategory::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/bike-part-categories/{id}",
     *     summary="Update a bike part category (Admin)",
     *     tags={"Admin Bike Part Categories"},
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
     *                 "name": "Brakes Setup",
     *                 "name_ar": "نظام الفرامل"
     *             },
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="name_ar", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Category updated successfully"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $category = BikePartCategory::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:bike_part_categories,name,' . $id,
            'name_ar' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $category->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/bike-part-categories/{id}",
     *     summary="Delete a bike part category (Admin)",
     *     tags={"Admin Bike Part Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Category deleted successfully"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $category = BikePartCategory::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        $deletedData = $category->toArray();
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
            'deleted_data' => $deletedData,
            'remaining_count' => BikePartCategory::count()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/bike-part-categories/stats/overview",
     *     summary="Get bike part categories statistics (Admin)",
     *     tags={"Admin Bike Part Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_categories' => BikePartCategory::count(),
            'categories_this_month' => BikePartCategory::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
