<?php

namespace App\Http\Controllers;

use App\Models\RouteWaypoint;
use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RouteWaypointController extends Controller
{
    /**
     * Display waypoints for a specific route.
     */
    public function index(int $routeId): JsonResponse
    {
        $route = Route::find($routeId);

        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Route not found',
            ], 404);
        }

        $waypoints = RouteWaypoint::where('route_id', $routeId)
            ->orderBy('order_position')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $waypoints,
        ]);
    }

    /**
     * Store a new waypoint for a route.
     */
    public function store(Request $request, int $routeId): JsonResponse
    {
        $route = Route::find($routeId);

        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Route not found',
            ], 404);
        }

        // Check if user is route creator
        if ($route->created_by !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to add waypoints to this route',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'order_position' => 'nullable|integer|min:1',
            'waypoint_type' => 'required|in:start,end,waypoint,poi,rest_stop,gas_station,viewpoint',
            'description' => 'nullable|string|max:1000',
            'stop_duration' => 'nullable|integer|min:0',
            'elevation' => 'nullable|integer',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();

            // Auto-assign order_position if not provided
            if (!isset($data['order_position'])) {
                $maxOrder = RouteWaypoint::where('route_id', $routeId)->max('order_position');
                $data['order_position'] = ($maxOrder ?? 0) + 1;
            } else {
                // Shift existing waypoints if necessary
                RouteWaypoint::where('route_id', $routeId)
                    ->where('order_position', '>=', $data['order_position'])
                    ->increment('order_position');
            }

            $data['route_id'] = $routeId;

            // Calculate distance from previous waypoint
            $previousWaypoint = RouteWaypoint::where('route_id', $routeId)
                ->where('order_position', '<', $data['order_position'])
                ->orderBy('order_position', 'desc')
                ->first();

            if ($previousWaypoint) {
                $data['distance_from_previous'] = $this->calculateDistance(
                    $previousWaypoint->latitude,
                    $previousWaypoint->longitude,
                    $data['latitude'],
                    $data['longitude']
                );
            }

            $waypoint = RouteWaypoint::create($data);

            // Update distances for next waypoints
            $this->recalculateDistances($routeId, $data['order_position']);

            // Update route total distance
            $this->updateRouteTotalDistance($routeId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Waypoint added successfully',
                'data' => $waypoint,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add waypoint: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a specific waypoint.
     */
    public function show(int $routeId, int $id): JsonResponse
    {
        $waypoint = RouteWaypoint::where('route_id', $routeId)->find($id);

        if (!$waypoint) {
            return response()->json([
                'success' => false,
                'message' => 'Waypoint not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $waypoint,
        ]);
    }

    /**
     * Update a waypoint.
     */
    public function update(Request $request, int $routeId, int $id): JsonResponse
    {
        $waypoint = RouteWaypoint::where('route_id', $routeId)->find($id);

        if (!$waypoint) {
            return response()->json([
                'success' => false,
                'message' => 'Waypoint not found',
            ], 404);
        }

        $route = Route::find($routeId);

        // Check if user is route creator
        if ($route->created_by !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this waypoint',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'waypoint_type' => 'sometimes|required|in:start,end,waypoint,poi,rest_stop,gas_station,viewpoint',
            'description' => 'nullable|string|max:1000',
            'stop_duration' => 'nullable|integer|min:0',
            'elevation' => 'nullable|integer',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();
            $waypoint->update($data);

            // Recalculate distances if coordinates changed
            if (isset($data['latitude']) || isset($data['longitude'])) {
                $this->recalculateDistances($routeId, $waypoint->order_position);
                $this->updateRouteTotalDistance($routeId);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Waypoint updated successfully',
                'data' => $waypoint->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update waypoint: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reorder a waypoint.
     */
    public function reorder(Request $request, int $routeId, int $id): JsonResponse
    {
        $waypoint = RouteWaypoint::where('route_id', $routeId)->find($id);

        if (!$waypoint) {
            return response()->json([
                'success' => false,
                'message' => 'Waypoint not found',
            ], 404);
        }

        $route = Route::find($routeId);

        // Check if user is route creator
        if ($route->created_by !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to reorder waypoints',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'new_position' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $newPosition = $request->new_position;
        $oldPosition = $waypoint->order_position;

        if ($newPosition === $oldPosition) {
            return response()->json([
                'success' => true,
                'message' => 'Waypoint is already at this position',
                'data' => $waypoint,
            ]);
        }

        DB::beginTransaction();
        try {
            if ($newPosition > $oldPosition) {
                // Moving down
                RouteWaypoint::where('route_id', $routeId)
                    ->whereBetween('order_position', [$oldPosition + 1, $newPosition])
                    ->decrement('order_position');
            } else {
                // Moving up
                RouteWaypoint::where('route_id', $routeId)
                    ->whereBetween('order_position', [$newPosition, $oldPosition - 1])
                    ->increment('order_position');
            }

            $waypoint->update(['order_position' => $newPosition]);

            // Recalculate all distances
            $this->recalculateAllDistances($routeId);
            $this->updateRouteTotalDistance($routeId);

            DB::commit();

            $waypoints = RouteWaypoint::where('route_id', $routeId)
                ->orderBy('order_position')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Waypoint reordered successfully',
                'data' => $waypoints,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder waypoint: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a waypoint.
     */
    public function destroy(int $routeId, int $id): JsonResponse
    {
        $waypoint = RouteWaypoint::where('route_id', $routeId)->find($id);

        if (!$waypoint) {
            return response()->json([
                'success' => false,
                'message' => 'Waypoint not found',
            ], 404);
        }

        $route = Route::find($routeId);

        // Check if user is route creator
        if ($route->created_by !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this waypoint',
            ], 403);
        }

        DB::beginTransaction();
        try {
            $orderPosition = $waypoint->order_position;
            $waypoint->delete();

            // Shift down subsequent waypoints
            RouteWaypoint::where('route_id', $routeId)
                ->where('order_position', '>', $orderPosition)
                ->decrement('order_position');

            // Recalculate distances
            $this->recalculateDistances($routeId, $orderPosition);
            $this->updateRouteTotalDistance($routeId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Waypoint deleted successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete waypoint: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate distance between two coordinates using Haversine formula.
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    /**
     * Recalculate distances starting from a specific position.
     */
    private function recalculateDistances(int $routeId, int $fromPosition): void
    {
        $waypoints = RouteWaypoint::where('route_id', $routeId)
            ->where('order_position', '>=', $fromPosition)
            ->orderBy('order_position')
            ->get();

        foreach ($waypoints as $waypoint) {
            $previousWaypoint = RouteWaypoint::where('route_id', $routeId)
                ->where('order_position', '<', $waypoint->order_position)
                ->orderBy('order_position', 'desc')
                ->first();

            if ($previousWaypoint) {
                $distance = $this->calculateDistance(
                    $previousWaypoint->latitude,
                    $previousWaypoint->longitude,
                    $waypoint->latitude,
                    $waypoint->longitude
                );
                $waypoint->update(['distance_from_previous' => $distance]);
            }
        }
    }

    /**
     * Recalculate all distances for a route.
     */
    private function recalculateAllDistances(int $routeId): void
    {
        $this->recalculateDistances($routeId, 1);
    }

    /**
     * Update the total distance of a route.
     */
    private function updateRouteTotalDistance(int $routeId): void
    {
        $totalDistance = RouteWaypoint::where('route_id', $routeId)
            ->sum('distance_from_previous');

        Route::where('id', $routeId)->update(['total_distance' => $totalDistance]);
    }
}
