<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\RouteWaypoint;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Admin - Routes",
 *     description="API Endpoints for Managing Routes by Admins"
 * )
 */
class AdminRouteController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/routes",
     *     summary="Get all routes (Admin)",
     *     tags={"Admin - Routes"},
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
     *         description="Search by title",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category ID",
     *         required=false,
     *         @OA\Schema(type="integer")
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
        $query = Route::with([
            'creator',
            'category',
            'tags',
            'waypoints' => function ($query) {
                $query->orderBy('order_position', 'asc')->limit(1);
            }
        ]);

        if ($request->has('search') && !empty($request->search)) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->inCategory($request->category_id);
        }

        $query->latest();

        if ($request->has('per_page') && $request->per_page != '') {
            $perPage = $request->input('per_page', 15);
            $routes = $query->paginate($perPage);
        } else {
            $routes = $query->get();
        }

        // Add starter_point to each route
        if ($routes instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $routes->getCollection()->transform(function ($route) {
                $route->starter_point = $route->waypoints->first();
                unset($route->waypoints);
                return $route;
            });
        } else {
            $routes->transform(function ($route) {
                $route->starter_point = $route->waypoints->first();
                unset($route->waypoints);
                return $route;
            });
        }

        return response()->json([
            'success' => true,
            'data' => $routes,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/routes",
     *     summary="Create a new route (Admin)",
     *     tags={"Admin - Routes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","description","waypoints"},
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="category_id", type="integer"),
     *             @OA\Property(property="difficulty", type="string", enum={"easy","moderate","difficult","expert"}),
     *             @OA\Property(property="created_by", type="integer", description="User ID of the creator"),
     *             @OA\Property(property="waypoints", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Route created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'nullable|exists:route_categories,id',
            'difficulty' => 'nullable|in:easy,moderate,difficult,expert',
            'estimated_duration' => 'nullable|string|max:50',
            'best_season' => 'nullable|string|max:255',
            'road_condition' => 'nullable|in:excellent,good,fair,poor',
            'featured_image' => 'nullable|string',
            'created_by' => 'nullable|exists:users,id',
            'waypoints' => 'required|array|min:2',
            'waypoints.*.name' => 'required|string|max:255',
            'waypoints.*.latitude' => 'required|numeric|between:-90,90',
            'waypoints.*.longitude' => 'required|numeric|between:-180,180',
            'waypoints.*.waypoint_type' => 'nullable|in:start,waypoint,poi,rest_stop,gas_station,viewpoint,end',
            'waypoints.*.description' => 'nullable|string',
            'waypoints.*.poi_id' => 'nullable|exists:points_of_interest,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:route_tags,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $routeData = $request->except(['waypoints', 'tags']);
            $routeData['slug'] = Str::slug($request->title) . '-' . time();

            if (!isset($routeData['created_by'])) {
                $routeData['created_by'] = auth()->id();
            }

            // Calculate total distance
            $totalDistance = $this->calculateTotalDistance($request->waypoints);
            $routeData['total_distance'] = $totalDistance;

            $route = Route::create($routeData);

            // Create waypoints
            $previousLatitude = null;
            $previousLongitude = null;

            foreach ($request->waypoints as $index => $waypointData) {
                $waypointData['route_id'] = $route->id;
                $waypointData['order_position'] = $index + 1;

                if ($previousLatitude && $previousLongitude) {
                    $waypointData['distance_from_previous'] = $this->calculateDistance(
                        $previousLatitude,
                        $previousLongitude,
                        $waypointData['latitude'],
                        $waypointData['longitude']
                    );
                }

                RouteWaypoint::create($waypointData);

                $previousLatitude = $waypointData['latitude'];
                $previousLongitude = $waypointData['longitude'];
            }

            // Attach tags
            if ($request->has('tags')) {
                $route->tags()->sync($request->tags);
            }

            DB::commit();

            $route->load(['waypoints', 'category', 'tags']);

            return response()->json([
                'success' => true,
                'message' => 'Route created successfully',
                'data' => $route,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create route: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/routes/{id}",
     *     summary="Get a specific route (Admin)",
     *     tags={"Admin - Routes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Route not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $route = Route::with([
            'creator',
            'category',
            'waypoints.poi',
            'images',
            'approvedReviews.user',
            'tags',
            'activeWarnings',
        ])->find($id);

        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Route not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $route,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/routes/{id}",
     *     summary="Update a route (Admin)",
     *     tags={"Admin - Routes"},
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
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="category_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Route updated successfully"),
     *     @OA\Response(response=404, description="Route not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $route = Route::find($id);

        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Route not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'category_id' => 'nullable|exists:route_categories,id',
            'difficulty' => 'nullable|in:easy,moderate,difficult,expert',
            'estimated_duration' => 'nullable|string|max:50',
            'best_season' => 'nullable|string|max:255',
            'road_condition' => 'nullable|in:excellent,good,fair,poor',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $route->update($validator->validated());

        if ($request->has('tags')) {
            $route->tags()->sync($request->tags);
        }

        $route->load(['waypoints', 'category', 'tags']);

        return response()->json([
            'success' => true,
            'message' => 'Route updated successfully',
            'data' => $route,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/routes/{id}",
     *     summary="Delete a route (Admin)",
     *     tags={"Admin - Routes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Route deleted successfully"),
     *     @OA\Response(response=404, description="Route not found")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $route = Route::find($id);

        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Route not found',
            ], 404);
        }

        $route->delete();

        return response()->json([
            'success' => true,
            'message' => 'Route deleted successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/routes/stats/overview",
     *     summary="Get routes statistics (Admin)",
     *     tags={"Admin - Routes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
    public function stats(): JsonResponse
    {
        $totalRoutes = Route::count();
        $totalDistance = Route::sum('total_distance');

        $difficultyCounts = Route::select('difficulty', DB::raw('count(*) as count'))
            ->groupBy('difficulty')
            ->pluck('count', 'difficulty');

        $featuredRoutes = Route::where('is_featured', true)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_routes' => $totalRoutes,
                'total_distance_saved' => round($totalDistance, 2),
                'difficulty_breakdown' => $difficultyCounts,
                'featured_routes_count' => $featuredRoutes,
            ]
        ]);
    }

    /**
     * Calculate total distance between waypoints
     */
    private function calculateTotalDistance(array $waypoints): float
    {
        $totalDistance = 0;
        $previousLat = null;
        $previousLon = null;

        foreach ($waypoints as $waypoint) {
            if ($previousLat && $previousLon) {
                $totalDistance += $this->calculateDistance(
                    $previousLat,
                    $previousLon,
                    $waypoint['latitude'],
                    $waypoint['longitude']
                );
            }
            $previousLat = $waypoint['latitude'];
            $previousLon = $waypoint['longitude'];
        }

        return round($totalDistance, 2);
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        return $distance;
    }
}
