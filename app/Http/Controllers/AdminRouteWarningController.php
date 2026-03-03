<?php

namespace App\Http\Controllers;

use App\Models\RouteWarning;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Admin - Route Warnings",
 *     description="API Endpoints for Managing Route Warnings by Admins"
 * )
 */
class AdminRouteWarningController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/route-warnings",
     *     summary="Get all route warnings (Admin)",
     *     tags={"Admin - Route Warnings"},
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
     *     @OA\Parameter(
     *         name="warning_type",
     *         in="query",
     *         description="Filter by warning type",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="active_only",
     *         in="query",
     *         description="Get only active warnings",
     *         required=false,
     *         @OA\Schema(type="boolean")
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
        $query = RouteWarning::query()->with(['route', 'waypoint', 'reporter']);

        if ($request->has('route_id') && !empty($request->route_id)) {
            $query->where('route_id', $request->route_id);
        }

        if ($request->has('warning_type') && !empty($request->warning_type)) {
            $query->where('warning_type', $request->warning_type);
        }

        if ($request->has('active_only') && $request->boolean('active_only')) {
            $query->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', now());
                });
        }

        $query->latest();

        if ($request->has('per_page') && $request->per_page != '') {
            $perPage = $request->input('per_page', 15);
            $warnings = $query->paginate($perPage);
        } else {
            $warnings = $query->get();
        }

        return response()->json([
            'success' => true,
            'data' => $warnings,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/route-warnings/{id}",
     *     summary="Get a specific route warning (Admin)",
     *     tags={"Admin - Route Warnings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Warning not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $warning = RouteWarning::with(['route', 'waypoint', 'reporter'])->find($id);

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
     * @OA\Delete(
     *     path="/api/admin/route-warnings/{id}",
     *     summary="Delete a route warning (Admin)",
     *     tags={"Admin - Route Warnings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Warning deleted successfully"),
     *     @OA\Response(response=404, description="Warning not found")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $warning = RouteWarning::find($id);

        if (!$warning) {
            return response()->json([
                'success' => false,
                'message' => 'Warning not found',
            ], 404);
        }

        $warning->delete();

        return response()->json([
            'success' => true,
            'message' => 'Route warning deleted successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/route-warnings/stats/overview",
     *     summary="Get route warnings statistics (Admin)",
     *     tags={"Admin - Route Warnings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
    public function stats(): JsonResponse
    {
        $totalWarnings = RouteWarning::count();
        $activeWarnings = RouteWarning::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })->count();

        $warningsByType = RouteWarning::select('warning_type', \DB::raw('count(*) as count'))
            ->groupBy('warning_type')
            ->pluck('count', 'warning_type');

        return response()->json([
            'success' => true,
            'data' => [
                'total_warnings' => $totalWarnings,
                'active_warnings' => $activeWarnings,
                'warnings_by_type' => $warningsByType,
            ]
        ]);
    }
}
