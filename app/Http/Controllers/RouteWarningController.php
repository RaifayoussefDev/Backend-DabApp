<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\RouteWarning;
use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Route Warnings",
 *     description="API Endpoints for Route Warnings (construction, accidents, weather alerts, etc.)"
 * )
 */
class RouteWarningController extends Controller
{
    protected $notificationService;

    public function __construct(\App\Services\NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * @OA\Get(
     *     path="/api/routes/{route_id}/warnings",
     *     summary="Get all warnings for a route",
     *     tags={"Route Warnings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="route_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="active_only",
     *         in="query",
     *         description="Get only active warnings",
     *         @OA\Schema(type="boolean", default=true)
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Route not found")
     * )
     */
    public function index(Request $request, int $routeId): JsonResponse
    {
        $route = Route::find($routeId);

        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Route not found',
            ], 404);
        }

        $query = RouteWarning::where('route_id', $routeId)
            ->with(['waypoint', 'reporter']);

        // Filter active warnings by default
        if ($request->input('active_only', true)) {
            $query->where('is_active', true)
                  ->where(function ($q) {
                      $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', now());
                  });
        }

        $warnings = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $warnings,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/routes/{route_id}/warnings",
     *     summary="Report a warning for a route",
     *     tags={"Route Warnings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="route_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"warning_type","description"},
     *             @OA\Property(property="warning_type", type="string", enum={"construction","accident","weather","road_closure"}),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="waypoint_id", type="integer"),
     *             @OA\Property(property="start_date", type="string", format="date"),
     *             @OA\Property(property="end_date", type="string", format="date")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Warning reported successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
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

        $validator = Validator::make($request->all(), [
            'warning_type' => 'required|in:construction,accident,weather,road_closure',
            'description' => 'required|string|max:2000',
            'waypoint_id' => 'nullable|exists:route_waypoints,id',
            'start_date' => 'nullable|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify waypoint belongs to this route
        if ($request->has('waypoint_id')) {
            $waypoint = \App\Models\RouteWaypoint::find($request->waypoint_id);
            if ($waypoint && $waypoint->route_id !== $routeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Waypoint does not belong to this route',
                ], 422);
            }
        }

        $data = $validator->validated();
        $data['route_id'] = $routeId;
        $data['reported_by'] = Auth::id();

        $warning = RouteWarning::create($data);
        $warning->load(['waypoint', 'reporter']);

        // Send Notification
        try {
            $this->notificationService->sendToUser(Auth::user(), 'warning_reported', [
                'warning_type' => $warning->warning_type,
                'route_name' => $route->name ?? 'Route'
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send warning reported notification: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Warning reported successfully',
            'data' => $warning,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/routes/{route_id}/warnings/{id}",
     *     summary="Get a specific warning",
     *     tags={"Route Warnings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="route_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Warning not found")
     * )
     */
    public function show(int $routeId, int $id): JsonResponse
    {
        $warning = RouteWarning::where('route_id', $routeId)
            ->with(['waypoint', 'reporter', 'route'])
            ->find($id);

        if (!$warning) {
            return response()->json([
                'success' => false,
                'message' => 'Warning not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $warning,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/routes/{route_id}/warnings/{id}",
     *     summary="Update a warning",
     *     tags={"Route Warnings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="route_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Warning updated successfully"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Warning not found")
     * )
     */
    public function update(Request $request, int $routeId, int $id): JsonResponse
    {
        $warning = RouteWarning::where('route_id', $routeId)->find($id);

        if (!$warning) {
            return response()->json([
                'success' => false,
                'message' => 'Warning not found',
            ], 404);
        }

        // Check if user is reporter or admin
        if ($warning->reported_by !== Auth::id() ) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this warning',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'warning_type' => 'sometimes|required|in:construction,accident,weather,road_closure',
            'description' => 'sometimes|required|string|max:2000',
            'waypoint_id' => 'nullable|exists:route_waypoints,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $warning->update($validator->validated());
        $warning->load(['waypoint', 'reporter']);

        return response()->json([
            'success' => true,
            'message' => 'Warning updated successfully',
            'data' => $warning,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/routes/{route_id}/warnings/{id}",
     *     summary="Delete a warning",
     *     tags={"Route Warnings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="route_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Warning deleted successfully"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Warning not found")
     * )
     */
    public function destroy(int $routeId, int $id): JsonResponse
    {
        $warning = RouteWarning::where('route_id', $routeId)->find($id);

        if (!$warning) {
            return response()->json([
                'success' => false,
                'message' => 'Warning not found',
            ], 404);
        }

        // Check if user is reporter or admin
        if ($warning->reported_by !== Auth::id() ) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this warning',
            ], 403);
        }

        $warning->delete();

        return response()->json([
            'success' => true,
            'message' => 'Warning deleted successfully',
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/routes/{route_id}/warnings/{id}/deactivate",
     *     summary="Deactivate a warning",
     *     tags={"Route Warnings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="route_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Warning deactivated successfully"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Warning not found")
     * )
     */
    public function deactivate(int $routeId, int $id): JsonResponse
    {
        $warning = RouteWarning::where('route_id', $routeId)->find($id);

        if (!$warning) {
            return response()->json([
                'success' => false,
                'message' => 'Warning not found',
            ], 404);
        }

        // Check if user is reporter or admin
        if ($warning->reported_by !== Auth::id() ) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to deactivate this warning',
            ], 403);
        }

        $warning->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Warning deactivated successfully',
            'data' => $warning,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/warnings/active",
     *     summary="Get all active warnings across all routes",
     *     tags={"Route Warnings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="warning_type",
     *         in="query",
     *         description="Filter by warning type",
     *         @OA\Schema(type="string", enum={"construction","accident","weather","road_closure"})
     *     ),
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function getAllActive(Request $request): JsonResponse
    {
        $query = RouteWarning::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->with(['route', 'waypoint', 'reporter']);

        if ($request->has('warning_type')) {
            $query->where('warning_type', $request->warning_type);
        }

        $warnings = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $warnings,
        ]);
    }
}
