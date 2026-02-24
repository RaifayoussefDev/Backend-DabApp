<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\RouteWaypoint;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Routes",
 *     description="API Endpoints for Motorcycle Routes"
 * )
 */
class RouteController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/routes",
     *     summary="Get all routes",
     *     tags={"Routes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="difficulty",
     *         in="query",
     *         description="Filter by difficulty",
     *         @OA\Schema(type="string", enum={"easy","moderate","difficult","expert"})
     *     ),
     *     @OA\Parameter(
     *         name="is_featured",
     *         in="query",
     *         description="Filter featured routes",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(response=200, description="Success")
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

        if ($request->has('category_id')) {
            $query->inCategory($request->category_id);
        }

        if ($request->has('difficulty')) {
            $query->byDifficulty($request->difficulty);
        }

        if ($request->has('is_featured') && $request->boolean('is_featured')) {
            $query->featured();
        }

        $routes = $query->latest()->paginate(20);

        // Ajouter le starter_point à chaque route
        $routes->getCollection()->transform(function ($route) {
            $route->starter_point = $route->waypoints->first();
            unset($route->waypoints); // Optionnel : pour ne pas retourner tous les waypoints
            return $route;
        });

        return response()->json([
            'success' => true,
            'data' => $routes,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/routes",
     *     summary="Create a new route",
     *     tags={"Routes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","description","waypoints"},
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="category_id", type="integer"),
     *             @OA\Property(property="difficulty", type="string"),
     *             @OA\Property(property="best_season", type="string"),
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
            $routeData['created_by'] = auth()->id();

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
     *     path="/api/routes/{id}",
     *     summary="Get a specific route",
     *     tags={"Routes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
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

        // Increment views count
        $route->increment('views_count');

        return response()->json([
            'success' => true,
            'data' => $route,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/routes/{id}",
     *     summary="Update a route",
     *     tags={"Routes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Route updated successfully"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Route not found")
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

        if ($route->created_by !== auth()->id() && !auth()->user()->hasPermission('manage_routes')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this route',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'category_id' => 'nullable|exists:route_categories,id',
            'difficulty' => 'nullable|in:easy,moderate,difficult,expert',
            'estimated_duration' => 'nullable|string|max:50',
            'best_season' => 'nullable|string|max:255',
            'road_condition' => 'nullable|in:excellent,good,fair,poor',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $route->update($validator->validated());
        $route->load(['waypoints', 'category', 'tags']);

        return response()->json([
            'success' => true,
            'message' => 'Route updated successfully',
            'data' => $route,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/routes/{id}",
     *     summary="Delete a route",
     *     tags={"Routes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Route deleted successfully"),
     *     @OA\Response(response=403, description="Forbidden"),
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

        if ($route->created_by !== auth()->id() && !auth()->user()->hasPermission('manage_routes')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this route',
            ], 403);
        }

        $route->delete();

        return response()->json([
            'success' => true,
            'message' => 'Route deleted successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/routes/{id}/like",
     *     summary="Toggle like status for a route",
     *     tags={"Routes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Like toggled successfully")
     * )
     */
    public function toggleLike(int $id): JsonResponse
    {
        $route = Route::find($id);

        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Route not found',
            ], 404);
        }

        $user = auth()->user();
        $isLiked = $route->likedBy()->where('user_id', $user->id)->exists();

        if ($isLiked) {
            $route->likedBy()->detach($user->id);
            $route->decrement('likes_count');
            $message = 'Route unliked';
        } else {
            $route->likedBy()->attach($user->id);
            $route->increment('likes_count');
            $message = 'Route liked';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'is_liked' => !$isLiked,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/routes/{id}/favorite",
     *     summary="Toggle favorite status for a route",
     *     tags={"Routes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Favorite toggled successfully")
     * )
     */
    public function toggleFavorite(int $id): JsonResponse
    {
        $route = Route::find($id);

        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Route not found',
            ], 404);
        }

        $user = auth()->user();
        $isFavorited = $route->favoritedBy()->where('user_id', $user->id)->exists();

        if ($isFavorited) {
            $route->favoritedBy()->detach($user->id);
            $message = 'Route removed from favorites';
        } else {
            $route->favoritedBy()->attach($user->id);
            $message = 'Route added to favorites';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'is_favorited' => !$isFavorited,
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
