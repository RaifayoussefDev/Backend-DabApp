<?php

namespace App\Http\Controllers;

use App\Models\RouteCompletion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Admin - Route Completions",
 *     description="API Endpoints for Managing Route Completions by Admins"
 * )
 */
class AdminRouteCompletionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/route-completions",
     *     summary="Get all route completions (Admin)",
     *     tags={"Admin - Route Completions"},
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
     *         name="route_id",
     *         in="query",
     *         description="Filter by route ID",
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
        $query = RouteCompletion::query()->with(['user', 'route']);

        if ($request->has('route_id') && !empty($request->route_id)) {
            $query->where('route_id', $request->route_id);
        }

        $query->latest('completed_at');

        if ($request->has('per_page') && $request->per_page != '') {
            $perPage = $request->input('per_page', 15);
            $completions = $query->paginate($perPage);
        } else {
            $completions = $query->get();
        }

        return response()->json([
            'success' => true,
            'data' => $completions,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/route-completions/{id}",
     *     summary="Get a specific route completion (Admin)",
     *     tags={"Admin - Route Completions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Completion not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $completion = RouteCompletion::with(['user', 'route'])->find($id);

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
     * @OA\Delete(
     *     path="/api/admin/route-completions/{id}",
     *     summary="Delete a route completion (Admin)",
     *     tags={"Admin - Route Completions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Completion deleted successfully"),
     *     @OA\Response(response=404, description="Completion not found")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $completion = RouteCompletion::find($id);

        if (!$completion) {
            return response()->json([
                'success' => false,
                'message' => 'Completion not found',
            ], 404);
        }

        $route = $completion->route;
        $completion->delete();

        // Optional: decrement route completion count if route exists
        if ($route && $route->completed_count > 0) {
            $route->decrement('completed_count');
        }

        return response()->json([
            'success' => true,
            'message' => 'Route completion deleted successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/route-completions/stats/overview",
     *     summary="Get route completions statistics (Admin)",
     *     tags={"Admin - Route Completions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
    public function stats(): JsonResponse
    {
        $totalCompletions = RouteCompletion::count();

        $recentCompletions = RouteCompletion::with(['user', 'route'])
            ->latest('completed_at')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_completions' => $totalCompletions,
                'recent_completions' => $recentCompletions,
            ]
        ]);
    }
}
