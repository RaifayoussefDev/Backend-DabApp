<?php

namespace App\Http\Controllers;

use App\Models\PoiType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="POI Types",
 *     description="API Endpoints for POI Types management"
 * )
 */
class PoiTypeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/poi-types",
     *     summary="Get all POI types",
     *     tags={"POI Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="with_count",
     *         in="query",
     *         description="Include POI count",
     *         required=false,
     *         @OA\Schema(type="boolean", default=true)
     *     ),
     *     @OA\Parameter(
     *         name="with_services",
     *         in="query",
     *         description="Include services",
     *         required=false,
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Gas Station"),
     *                     @OA\Property(property="icon", type="string", example="fas fa-gas-pump"),
     *                     @OA\Property(property="color", type="string", example="#FF5733"),
     *                     @OA\Property(property="pois_count", type="integer", example=15),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )),
     *                 @OA\Property(property="first_page_url", type="string"),
     *                 @OA\Property(property="from", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="last_page_url", type="string"),
     *                 @OA\Property(property="next_page_url", type="string", nullable=true),
     *                 @OA\Property(property="path", type="string"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="prev_page_url", type="string", nullable=true),
     *                 @OA\Property(property="to", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = PoiType::query();

        // Include POI count by default
        if ($request->boolean('with_count', true)) {
            $query->withCount('pointsOfInterest');
        }

        // Optionally include services
        if ($request->boolean('with_services', false)) {
            $query->with('services');
        }

        // Check if pagination is requested
        if ($request->has('per_page') || $request->has('page')) {
            $perPage = $request->input('per_page', 20);
            $poiTypes = $query->orderBy('name')->paginate($perPage);
        } else {
            // "if per_page and page vide return all"
            $poiTypes = $query->orderBy('name')->get();
        }

        return response()->json([
            'success' => true,
            'data' => $poiTypes,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/poi-types",
     *     summary="Create a new POI type",
     *     tags={"POI Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Gas Station"),
     *             @OA\Property(property="icon", type="string", example="fas fa-gas-pump"),
     *             @OA\Property(property="color", type="string", example="#FF5733"),
     *             @OA\Property(property="type", type="string", example="fontawesome")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="POI type created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="POI type created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Gas Station"),
     *                 @OA\Property(property="icon", type="string", example="fas fa-gas-pump"),
     *                 @OA\Property(property="color", type="string", example="#FF5733"),
     *                 @OA\Property(property="type", type="string", example="fontawesome")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:poi_types,name',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $poiType = PoiType::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'POI type created successfully',
            'data' => $poiType,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/poi-types/{id}",
     *     summary="Get a specific POI type",
     *     tags={"POI Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="with_pois",
     *         in="query",
     *         description="Include related POIs",
     *         required=false,
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="POI type not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $query = PoiType::withCount('pointsOfInterest');

        // Optionally include related POIs
        if ($request->boolean('with_pois', false)) {
            $query->with([
                'pointsOfInterest' => function ($q) {
                    $q->active()->with(['mainImage', 'city', 'country'])->limit(10);
                }
            ]);
        }

        // Optionally include services
        if ($request->boolean('with_services', false)) {
            $query->with('services');
        }

        $poiType = $query->find($id);

        if (!$poiType) {
            return response()->json([
                'success' => false,
                'message' => 'POI type not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $poiType,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/poi-types/{id}",
     *     summary="Update a POI type",
     *     tags={"POI Types"},
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
     *             @OA\Property(property="name", type="string", example="Gas Station"),
     *             @OA\Property(property="icon", type="string", example="fas fa-gas-pump"),
     *             @OA\Property(property="color", type="string", example="#FF5733"),
     *             @OA\Property(property="type", type="string", example="fontawesome")
     *         )
     *     ),
     *     @OA\Response(response=200, description="POI type updated successfully"),
     *     @OA\Response(response=404, description="POI type not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $poiType = PoiType::find($id);

        if (!$poiType) {
            return response()->json([
                'success' => false,
                'message' => 'POI type not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:poi_types,name,' . $id,
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $poiType->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'POI type updated successfully',
            'data' => $poiType->fresh(),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/poi-types/{id}",
     *     summary="Delete a POI type",
     *     tags={"POI Types"},
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
     *         description="Force delete even with associated POIs (admin only)",
     *         required=false,
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Response(response=200, description="POI type deleted successfully"),
     *     @OA\Response(response=404, description="POI type not found"),
     *     @OA\Response(response=409, description="Cannot delete POI type with associated POIs"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $poiType = PoiType::withCount('pointsOfInterest')->find($id);

        if (!$poiType) {
            return response()->json([
                'success' => false,
                'message' => 'POI type not found',
            ], 404);
        }

        // Check if there are associated POIs
        if ($poiType->points_of_interest_count > 0) {
            // Only allow force delete if user is admin
            if (!$request->boolean('force', false) || !auth()->user()->hasPermission('manage_poi_types')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete POI type with associated POIs',
                    'pois_count' => $poiType->points_of_interest_count,
                ], 409);
            }
        }

        $poiType->delete();

        return response()->json([
            'success' => true,
            'message' => 'POI type deleted successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/poi-types/{id}/pois",
     *     summary="Get all POIs of a specific type",
     *     tags={"POI Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="POI type not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getPois(Request $request, int $id): JsonResponse
    {
        $poiType = PoiType::find($id);

        if (!$poiType) {
            return response()->json([
                'success' => false,
                'message' => 'POI type not found',
            ], 404);
        }

        $perPage = $request->input('per_page', 20);

        $pois = $poiType->pointsOfInterest()
            ->with(['mainImage', 'city', 'country'])
            ->active()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $pois,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/poi-types/stats",
     *     summary="Get statistics for all POI types",
     *     tags={"POI Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 @OA\Property(property="total_types", type="integer", example=10),
     *                 @OA\Property(property="total_pois", type="integer", example=150),
     *                 @OA\Property(
     *                     property="types_with_counts",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="pois_count", type="integer")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function stats(): JsonResponse
    {
        $types = PoiType::withCount('pointsOfInterest')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_types' => $types->count(),
                'total_pois' => $types->sum('points_of_interest_count'),
                'types_with_counts' => $types->map(function ($type) {
                    return [
                        'id' => $type->id,
                        'name' => $type->name,
                        'icon' => $type->icon,
                        'color' => $type->color,
                        'pois_count' => $type->points_of_interest_count,
                    ];
                }),
            ],
        ]);
    }
}
