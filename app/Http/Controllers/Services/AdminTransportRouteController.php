<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\TransportRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin Transport Routes",
 *     description="API endpoints for managing transport routes (Admin)"
 * )
 */
class AdminTransportRouteController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/transport-routes",
     *     summary="List transport routes (Admin)",
     *     operationId="adminGetTransportRoutes",
     *     tags={"Admin Transport Routes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_active", in="query", description="Filter by active status", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="Routes retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="route_date", type="string", format="date", example="2026-06-15"),
     *                     @OA\Property(property="start_location", type="string", example="Riyadh"),
     *                     @OA\Property(property="end_location", type="string", example="Jeddah"),
     *                     @OA\Property(property="status", type="string", example="scheduled"),
     *                     @OA\Property(property="provider", type="object", @OA\Property(property="name", type="string", example="Transport Co."))
     *                 )),
     *                 @OA\Property(property="total", type="integer", example=5)
     *             ),
     *             @OA\Property(property="message", type="string", example="Transport routes retrieved successfully")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = TransportRoute::with(['provider.user']);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->input('per_page');

        if ($perPage) {
            $routes = $query->orderBy('route_date', 'desc')->paginate($perPage);
        } else {
            $routes = $query->orderBy('route_date', 'desc')->get();
        }

        return response()->json([
            'success' => true,
            'data' => $routes,
            'message' => 'Transport routes retrieved successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/transport-routes/{id}",
     *     summary="Get route details (Admin)",
     *     operationId="adminGetTransportRoute",
     *     tags={"Admin Transport Routes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Route details retrieved")
     * )
     */
    public function show($id)
    {
        $route = TransportRoute::with(['provider.user', 'stops'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $route,
            'message' => 'Transport route retrieved successfully'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/transport-routes/{id}",
     *     summary="Delete transport route (Admin)",
     *     operationId="adminDeleteTransportRoute",
     *     tags={"Admin Transport Routes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Route deleted")
     * )
     */
    public function destroy($id)
    {
        $route = TransportRoute::findOrFail($id);
        $route->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transport route deleted successfully'
        ]);
    }
}
