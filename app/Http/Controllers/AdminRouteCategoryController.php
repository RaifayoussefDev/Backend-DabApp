<?php

namespace App\Http\Controllers;

use App\Models\RouteCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Admin - Route Categories",
 *     description="API Endpoints for Managing Route Categories by Admins"
 * )
 */
class AdminRouteCategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/route-categories",
     *     summary="Get all route categories (Admin)",
     *     tags={"Admin - Route Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page. Leave empty to get all items.",
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
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = RouteCategory::query()->withCount('routes');

        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $query->orderBy('name', 'asc');

        if ($request->has('per_page') && $request->per_page != '') {
            $perPage = $request->input('per_page', 15);
            $categories = $query->paginate($perPage);
        } else {
            $categories = $query->get();
        }

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/route-categories",
     *     summary="Create a new route category (Admin)",
     *     tags={"Admin - Route Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Mountain Passes"),
     *             @OA\Property(property="icon", type="string", example="fas fa-mountain"),
     *             @OA\Property(property="color", type="string", example="#2E8B57")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Category created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:route_categories,name',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['slug'] = Str::slug($request->name);

        $category = RouteCategory::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Route category created successfully',
            'data' => $category,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/route-categories/{id}",
     *     summary="Get a specific route category (Admin)",
     *     tags={"Admin - Route Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Category not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $category = RouteCategory::withCount('routes')->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Route category not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/route-categories/{id}",
     *     summary="Update a route category (Admin)",
     *     tags={"Admin - Route Categories"},
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
     *             @OA\Property(property="name", type="string", example="Coastal Routes"),
     *             @OA\Property(property="icon", type="string", example="fas fa-water"),
     *             @OA\Property(property="color", type="string", example="#1E90FF")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Category updated successfully"),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $category = RouteCategory::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Route category not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:route_categories,name,' . $id,
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Route category updated successfully',
            'data' => $category->fresh(),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/route-categories/{id}",
     *     summary="Delete a route category (Admin)",
     *     tags={"Admin - Route Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Category deleted successfully"),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=409, description="Cannot delete category with associated routes")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $category = RouteCategory::withCount('routes')->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Route category not found',
            ], 404);
        }

        if ($category->routes_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with associated routes',
            ], 409);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Route category deleted successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/route-categories/stats/overview",
     *     summary="Get route category statistics (Admin)",
     *     tags={"Admin - Route Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
    public function stats(): JsonResponse
    {
        $totalCategories = RouteCategory::count();
        $categoriesWithRoutes = RouteCategory::has('routes')->count();
        $categoriesWithoutRoutes = RouteCategory::doesntHave('routes')->count();

        // Get top categories by route count
        $topCategories = RouteCategory::withCount('routes')
            ->orderBy('routes_count', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'routes_count']);

        return response()->json([
            'success' => true,
            'data' => [
                'total_categories' => $totalCategories,
                'categories_with_routes' => $categoriesWithRoutes,
                'categories_without_routes' => $categoriesWithoutRoutes,
                'top_categories' => $topCategories,
            ]
        ]);
    }
}
