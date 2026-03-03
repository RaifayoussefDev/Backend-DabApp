<?php

namespace App\Http\Controllers;

use App\Models\PoiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin POI Services",
 *     description="Admin API Endpoints for managing POI services"
 * )
 */
class AdminPoiServiceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/poi-services",
     *     summary="Get all POI services (Admin)",
     *     tags={"Admin POI Services"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by service name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="type_id",
     *         in="query",
     *         description="Filter by POI type",
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
        $query = PoiService::with('type');

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('name', 'LIKE', "%{$search}%");
        }

        if ($request->has('type_id')) {
            $query->where('type_id', $request->type_id);
        }

        $perPage = $request->input('per_page');

        if ($perPage === null || $perPage === '') {
            $services = $query->get();
        } else {
            $services = $query->paginate((int) $perPage);
        }

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/poi-services",
     *     summary="Create a new POI service (Admin)",
     *     tags={"Admin POI Services"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","type_id"},
     *             example={
     *                 "name": "Wheelchair Accessible",
     *                 "type_id": 1
     *             },
     *             @OA\Property(property="name", type="string", example="Wheelchair Accessible"),
     *             @OA\Property(property="type_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=201, description="POI service created successfully"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type_id' => 'required|exists:poi_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $service = PoiService::create($validator->validated());
        $service->load('type');

        return response()->json([
            'success' => true,
            'message' => 'POI service created successfully',
            'data' => $service,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/poi-services/{id}",
     *     summary="Get a specific POI service (Admin)",
     *     tags={"Admin POI Services"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="POI service not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $service = PoiService::with('type')->find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'POI service not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $service,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/poi-services/{id}",
     *     summary="Update a POI service (Admin)",
     *     tags={"Admin POI Services"},
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
     *                 "name": "Wheelchair Accessible (Updated)"
     *             },
     *             @OA\Property(property="name", type="string", example="Wheelchair Accessible (Updated)"),
     *             @OA\Property(property="type_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="POI service updated successfully"),
     *     @OA\Response(response=404, description="POI service not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $service = PoiService::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'POI service not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'type_id' => 'sometimes|required|exists:poi_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $service->update($validator->validated());
        $service->load('type');

        return response()->json([
            'success' => true,
            'message' => 'POI service updated successfully',
            'data' => $service,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/poi-services/{id}",
     *     summary="Delete a POI service (Admin)",
     *     tags={"Admin POI Services"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="POI service deleted successfully"),
     *     @OA\Response(response=404, description="POI service not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $service = PoiService::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'POI service not found',
            ], 404);
        }

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'POI service deleted successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/poi-services/stats/overview",
     *     summary="Get POI service statistics (Admin)",
     *     tags={"Admin POI Services"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_services' => PoiService::count(),
            'by_type' => PoiService::select('type_id', \DB::raw('count(*) as count'))
                ->with('type:id,name')
                ->groupBy('type_id')
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
