<?php

namespace App\Http\Controllers;

use App\Models\PoiTag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Admin POI Tags",
 *     description="Admin API Endpoints for managing POI tags"
 * )
 */
class AdminPoiTagController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/poi-admin-tags",
     *     summary="Get all POI tags (Admin)",
     *     tags={"Admin POI Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by tag name",
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
        $query = PoiTag::withCount('pois')->orderBy('name');

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $perPage = $request->input('per_page');

        if ($perPage === null || $perPage === '') {
            $tags = $query->get();
        } else {
            $tags = $query->paginate((int) $perPage);
        }

        return response()->json([
            'success' => true,
            'data' => $tags
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/poi-admin-tags",
     *     summary="Create a new POI tag (Admin)",
     *     tags={"Admin POI Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             example={
     *                 "name": "Kid Friendly",
     *                 "slug": "kid-friendly"
     *             },
     *             @OA\Property(property="name", type="string", example="Kid Friendly"),
     *             @OA\Property(property="slug", type="string", description="Optional, auto-generated if omitted", example="kid-friendly")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tag created successfully"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:poi_tags,name',
            'slug' => 'nullable|string|max:255|unique:poi_tags,slug',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $tag = PoiTag::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Tag created successfully',
            'data' => $tag
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/poi-admin-tags/{id}",
     *     summary="Get a specific POI tag (Admin)",
     *     tags={"Admin POI Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Tag not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $tag = PoiTag::withCount('pois')->find($id);

        if (!$tag) {
            return response()->json([
                'success' => false,
                'message' => 'Tag not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $tag
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/poi-admin-tags/{id}",
     *     summary="Update a POI tag (Admin)",
     *     tags={"Admin POI Tags"},
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
     *             example={
     *                 "name": "Kid Friendly (Updated)",
     *                 "slug": "kid-friendly-updated"
     *             },
     *             @OA\Property(property="name", type="string", example="Kid Friendly (Updated)"),
     *             @OA\Property(property="slug", type="string", example="kid-friendly-updated")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Tag updated successfully"),
     *     @OA\Response(response=404, description="Tag not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tag = PoiTag::find($id);

        if (!$tag) {
            return response()->json([
                'success' => false,
                'message' => 'Tag not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:poi_tags,name,' . $id,
            'slug' => 'nullable|string|max:255|unique:poi_tags,slug,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $tag->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Tag updated successfully',
            'data' => $tag
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/poi-admin-tags/{id}",
     *     summary="Delete a POI tag (Admin)",
     *     tags={"Admin POI Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Tag deleted successfully"),
     *     @OA\Response(response=404, description="Tag not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $tag = PoiTag::find($id);

        if (!$tag) {
            return response()->json([
                'success' => false,
                'message' => 'Tag not found'
            ], 404);
        }

        // Tags are usually safe to delete (cascade or set null in pivot)
        $tag->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tag deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/poi-admin-tags/stats/overview",
     *     summary="Get POI tag statistics (Admin)",
     *     tags={"Admin POI Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function stats(): JsonResponse
    {
        $tags = PoiTag::withCount('pois')->orderByDesc('pois_count')->get();

        $stats = [
            'total_tags' => $tags->count(),
            'tags_distribution' => $tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'pois_count' => $tag->pois_count,
                ];
            })
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
