<?php

namespace App\Http\Controllers;

use App\Models\RouteTag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Admin - Route Tags",
 *     description="API Endpoints for Managing Route Tags by Admins"
 * )
 */
class AdminRouteTagController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/route-tags",
     *     summary="Get all route tags (Admin)",
     *     tags={"Admin - Route Tags"},
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
        $query = RouteTag::query()->withCount('routes')->orderBy('usage_count', 'desc');

        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('slug', 'like', '%' . $request->search . '%');
        }

        if ($request->has('per_page') && $request->per_page != '') {
            $perPage = $request->input('per_page', 15);
            $tags = $query->paginate($perPage);
        } else {
            $tags = $query->get();
        }

        return response()->json([
            'success' => true,
            'data' => $tags,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/route-tags",
     *     summary="Create a new route tag (Admin)",
     *     tags={"Admin - Route Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Off-road")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tag created successfully"),
     *     @OA\Response(response=409, description="Tag already exists"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $slug = Str::slug($request->name);

        $existingTag = RouteTag::where('slug', $slug)->first();
        if ($existingTag) {
            return response()->json([
                'success' => false,
                'message' => 'Tag already exists',
                'data' => $existingTag,
            ], 409);
        }

        $tag = RouteTag::create([
            'name' => $request->name,
            'slug' => $slug,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Route tag created successfully',
            'data' => $tag,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/route-tags/{id}",
     *     summary="Get a specific route tag (Admin)",
     *     tags={"Admin - Route Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Tag not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $tag = RouteTag::withCount('routes')->find($id);

        if (!$tag) {
            return response()->json([
                'success' => false,
                'message' => 'Route tag not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $tag,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/route-tags/{id}",
     *     summary="Update a route tag (Admin)",
     *     tags={"Admin - Route Tags"},
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
     *             @OA\Property(property="name", type="string", example="Scenic")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Tag updated successfully"),
     *     @OA\Response(response=404, description="Tag not found"),
     *     @OA\Response(response=409, description="Another tag with this name already exists"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tag = RouteTag::find($id);

        if (!$tag) {
            return response()->json([
                'success' => false,
                'message' => 'Route tag not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $slug = Str::slug($request->name);

        $existingTag = RouteTag::where('slug', $slug)
            ->where('id', '!=', $id)
            ->first();

        if ($existingTag) {
            return response()->json([
                'success' => false,
                'message' => 'Another tag with this name already exists',
            ], 409);
        }

        $tag->update([
            'name' => $request->name,
            'slug' => $slug,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Route tag updated successfully',
            'data' => $tag->fresh(),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/route-tags/{id}",
     *     summary="Delete a route tag (Admin)",
     *     tags={"Admin - Route Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Tag deleted successfully"),
     *     @OA\Response(response=404, description="Tag not found")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $tag = RouteTag::find($id);

        if (!$tag) {
            return response()->json([
                'success' => false,
                'message' => 'Route tag not found',
            ], 404);
        }

        $tag->routes()->detach();
        $tag->delete();

        return response()->json([
            'success' => true,
            'message' => 'Route tag deleted successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/route-tags/stats/overview",
     *     summary="Get route tags statistics (Admin)",
     *     tags={"Admin - Route Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
    public function stats(): JsonResponse
    {
        $totalTags = RouteTag::count();
        $tagsWithRoutes = RouteTag::has('routes')->count();
        $tagsWithoutRoutes = RouteTag::doesntHave('routes')->count();

        // Get top tags by route count
        $topTags = RouteTag::withCount('routes')
            ->orderBy('routes_count', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'slug', 'routes_count']);

        return response()->json([
            'success' => true,
            'data' => [
                'total_tags' => $totalTags,
                'tags_with_routes' => $tagsWithRoutes,
                'tags_without_routes' => $tagsWithoutRoutes,
                'top_tags' => $topTags,
            ]
        ]);
    }
}
