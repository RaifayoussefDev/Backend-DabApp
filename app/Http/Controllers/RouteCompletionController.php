<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\RouteCompletion;
use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Route Completions",
 *     description="API Endpoints for tracking route completions by users"
 * )
 */
class RouteCompletionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/routes/{route_id}/completions",
     *     summary="Get all completions for a route",
     *     tags={"Route Completions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="route_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Route not found")
     * )
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

        $completions = RouteCompletion::where('route_id', $routeId)
            ->with('user')
            ->latest('completed_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $completions,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/routes/{route_id}/completions",
     *     summary="Mark a route as completed",
     *     tags={"Route Completions"},
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
     *             required={"completed_at"},
     *             @OA\Property(property="completed_at", type="string", format="date-time"),
     *             @OA\Property(property="actual_duration", type="string", example="3 hours 45 minutes"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Route marked as completed"),
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
            'completed_at' => 'required|date|before_or_equal:now',
            'actual_duration' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $completion = RouteCompletion::create([
                'route_id' => $routeId,
                'user_id' => Auth::id(),
                'completed_at' => $request->completed_at,
                'actual_duration' => $request->actual_duration,
                'notes' => $request->notes,
            ]);

            // Increment route completed count
            $route->increment('completed_count');

            DB::commit();

            $completion->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Route marked as completed successfully',
                'data' => $completion,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark route as completed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/routes/{route_id}/completions/{id}",
     *     summary="Get a specific completion",
     *     tags={"Route Completions"},
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
     *     @OA\Response(response=404, description="Completion not found")
     * )
     */
    public function show(int $routeId, int $id): JsonResponse
    {
        $completion = RouteCompletion::where('route_id', $routeId)
            ->with(['user', 'route'])
            ->find($id);

        if (!$completion) {
            return response()->json([
                'success' => false,
                'message' => 'Completion not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $completion,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/routes/{route_id}/completions/{id}",
     *     summary="Update a completion record",
     *     tags={"Route Completions"},
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
     *     @OA\Response(response=200, description="Completion updated successfully"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Completion not found")
     * )
     */
    public function update(Request $request, int $routeId, int $id): JsonResponse
    {
        $completion = RouteCompletion::where('route_id', $routeId)->find($id);

        if (!$completion) {
            return response()->json([
                'success' => false,
                'message' => 'Completion not found',
            ], 404);
        }

        // Check if user owns this completion
        if ($completion->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this completion',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'completed_at' => 'sometimes|required|date|before_or_equal:now',
            'actual_duration' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $completion->update($validator->validated());
        $completion->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Completion updated successfully',
            'data' => $completion,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/routes/{route_id}/completions/{id}",
     *     summary="Delete a completion record",
     *     tags={"Route Completions"},
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
     *     @OA\Response(response=200, description="Completion deleted successfully"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Completion not found")
     * )
     */
    public function destroy(int $routeId, int $id): JsonResponse
    {
        $completion = RouteCompletion::where('route_id', $routeId)->find($id);

        if (!$completion) {
            return response()->json([
                'success' => false,
                'message' => 'Completion not found',
            ], 404);
        }

        // Check if user owns this completion
        if ($completion->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this completion',
            ], 403);
        }

        DB::beginTransaction();
        try {
            $route = $completion->route;
            $completion->delete();

            // Decrement route completed count
            if ($route->completed_count > 0) {
                $route->decrement('completed_count');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Completion deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete completion: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/user/completions",
     *     summary="Get all completions for the authenticated user",
     *     tags={"Route Completions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function userCompletions(): JsonResponse
    {
        $completions = RouteCompletion::where('user_id', Auth::id())
            ->with('route')
            ->latest('completed_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $completions,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/user/completion-stats",
     *     summary="Get completion statistics for the authenticated user",
     *     tags={"Route Completions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function userStats(): JsonResponse
    {
        $userId = Auth::id();

        $totalCompletions = RouteCompletion::where('user_id', $userId)->count();
        $uniqueRoutes = RouteCompletion::where('user_id', $userId)
            ->distinct('route_id')
            ->count('route_id');

        $totalDistance = DB::table('route_completions')
            ->join('routes', 'route_completions.route_id', '=', 'routes.id')
            ->where('route_completions.user_id', $userId)
            ->sum('routes.total_distance');

        $recentCompletions = RouteCompletion::where('user_id', $userId)
            ->with('route')
            ->latest('completed_at')
            ->limit(5)
            ->get();

        $stats = [
            'total_completions' => $totalCompletions,
            'unique_routes_completed' => $uniqueRoutes,
            'total_distance_km' => round($totalDistance, 2),
            'recent_completions' => $recentCompletions,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/routes/{route_id}/check-completion",
     *     summary="Check if current user has completed this route",
     *     tags={"Route Completions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="route_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function checkCompletion(int $routeId): JsonResponse
    {
        $route = Route::find($routeId);

        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Route not found',
            ], 404);
        }

        $completion = RouteCompletion::where('route_id', $routeId)
            ->where('user_id', Auth::id())
            ->latest('completed_at')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'has_completed' => $completion !== null,
                'completion_count' => RouteCompletion::where('route_id', $routeId)
                    ->where('user_id', Auth::id())
                    ->count(),
                'last_completion' => $completion,
            ],
        ]);
    }
}
