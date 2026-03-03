<?php

namespace App\Http\Controllers;

use App\Models\PoiType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Models\PointOfInterest;

/**
 * @OA\Tag(
 *     name="Admin POI Types",
 *     description="Admin API Endpoints for managing POI types"
 * )
 */
class AdminPoiTypeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/poi-types",
     *     summary="Get all POI types (Admin)",
     *     tags={"Admin POI Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by type name",
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
        $query = PoiType::withCount('pois');

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('name_ar', 'LIKE', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page');

        if ($perPage === null || $perPage === '') {
            $types = $query->get();
        } else {
            $types = $query->paginate((int) $perPage);
        }

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/poi-types",
     *     summary="Create a new POI type (Admin)",
     *     tags={"Admin POI Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             example={
     *                 "name": "Restaurant",
     *                 "name_ar": "مطعم",
     *                 "icon": "fa-utensils",
     *                 "color": "#FF0000"
     *             },
     *             @OA\Property(property="name", type="string", example="Restaurant"),
     *             @OA\Property(property="name_ar", type="string", example="مطعم"),
     *             @OA\Property(property="icon", type="string", example="fa-utensils"),
     *             @OA\Property(property="color", type="string", example="#FF0000")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Type created successfully"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $type = PoiType::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Type created successfully',
            'data' => $type
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/poi-types/{id}",
     *     summary="Get a specific POI type (Admin)",
     *     tags={"Admin POI Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Type not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $type = PoiType::withCount('pois')->find($id);

        if (!$type) {
            return response()->json([
                'success' => false,
                'message' => 'Type not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $type
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/poi-types/{id}",
     *     summary="Update a POI type (Admin)",
     *     tags={"Admin POI Types"},
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
     *                 "name": "Restaurant (Updated)",
     *                 "color": "#FF5733"
     *             },
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="name_ar", type="string"),
     *             @OA\Property(property="icon", type="string"),
     *             @OA\Property(property="color", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Type updated successfully"),
     *     @OA\Response(response=404, description="Type not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $type = PoiType::find($id);

        if (!$type) {
            return response()->json([
                'success' => false,
                'message' => 'Type not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $type->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Type updated successfully',
            'data' => $type
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/poi-types/{id}",
     *     summary="Delete a POI type (Admin)",
     *     tags={"Admin POI Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="force",
     *         in="query",
     *         description="Force delete even if there are POIs attached (cascades or fails)",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(response=200, description="Type deleted successfully"),
     *     @OA\Response(response=400, description="Cannot delete type with associated POIs without force"),
     *     @OA\Response(response=404, description="Type not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $type = PoiType::find($id);

        if (!$type) {
            return response()->json([
                'success' => false,
                'message' => 'Type not found'
            ], 404);
        }

        $poiCount = PointOfInterest::where('type_id', $id)->count();

        if ($poiCount > 0 && !$request->boolean('force')) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete type because there are {$poiCount} POIs associated with it. Provide 'force=true' parameter to override if DB allows cascading."
            ], 400);
        }

        $type->delete();

        return response()->json([
            'success' => true,
            'message' => 'Type deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/poi-types/stats/overview",
     *     summary="Get POI Types statistics (Admin)",
     *     tags={"Admin POI Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function stats(): JsonResponse
    {
        $types = PoiType::withCount('pois')->orderByDesc('pois_count')->get();

        $stats = [
            'total_types' => $types->count(),
            'types_distribution' => $types->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'pois_count' => $type->pois_count,
                    'icon' => $type->icon,
                ];
            })
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
