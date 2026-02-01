<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin Services",
 *     description="API endpoints for moderating provider services (Admin)"
 * )
 */
class AdminServiceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/services",
     *     summary="List all services (Admin)",
     *     operationId="adminGetServices",
     *     tags={"Admin Services"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", description="Filter by status (active, inactive, pending_approval)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="category_id", in="query", description="Filter by category", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="provider_id", in="query", description="Filter by provider", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Services retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=10),
     *                         @OA\Property(property="name", type="string", example="Premium Car Wash"),
     *                         @OA\Property(property="price", type="number", example=50.00),
     *                         @OA\Property(property="is_approved", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=false),
     *                         @OA\Property(property="provider", type="object", @OA\Property(property="id", type="integer", example=5), @OA\Property(property="name", type="string", example="Mohammed Ali")),
     *                         @OA\Property(property="category", type="object", @OA\Property(property="id", type="integer", example=2), @OA\Property(property="name", type="string", example="Car Care"))
     *                     )
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="per_page", type="integer", example=15)
     *             ),
     *             @OA\Property(property="message", type="string", example="Services retrieved successfully")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Service::with(['category', 'provider.user']);

        if ($request->has('status')) {
            if ($request->status === 'pending_approval') {
                $query->where('is_approved', false);
            } elseif ($request->status === 'active') {
                $query->where('is_active', true)->where('is_approved', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('provider_id')) {
            $query->where('provider_id', $request->provider_id);
        }

        $perPage = $request->input('per_page');

        if ($perPage) {
            $services = $query->orderBy('created_at', 'desc')->paginate($perPage);
        } else {
            $services = $query->orderBy('created_at', 'desc')->get();
        }

        return response()->json([
            'success' => true,
            'data' => $services,
            'message' => 'Services retrieved successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/services/{id}",
     *     summary="Get service details (Admin)",
     *     operationId="adminGetService",
     *     tags={"Admin Services"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Service details retrieved")
     * )
     */
    public function show($id)
    {
        $service = Service::with(['category', 'provider.user', 'reviews'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $service]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/services/{id}/approve",
     *     summary="Approve a service (Admin)",
     *     operationId="adminApproveService",
     *     tags={"Admin Services"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Service approved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=10),
     *                 @OA\Property(property="is_approved", type="boolean", example=true),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="rejection_reason", type="string", nullable=true, example=null)
     *             ),
     *             @OA\Property(property="message", type="string", example="Service approved successfully")
     *         )
     *     )
     * )
     */
    public function approve($id)
    {
        $service = Service::findOrFail($id);

        $service->update([
            'is_approved' => true,
            'is_active' => true,
            'rejection_reason' => null
        ]);

        // TODO: Notify provider

        return response()->json([
            'success' => true,
            'data' => $service,
            'message' => 'Service approved successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/services/{id}/reject",
     *     summary="Reject a service (Admin)",
     *     operationId="adminRejectService",
     *     tags={"Admin Services"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", example="Incomplete description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Service rejected successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=10),
     *                 @OA\Property(property="is_approved", type="boolean", example=false),
     *                 @OA\Property(property="is_active", type="boolean", example=false),
     *                 @OA\Property(property="rejection_reason", type="string", example="Incomplete description and missing images")
     *             ),
     *             @OA\Property(property="message", type="string", example="Service rejected successfully")
     *         )
     *     )
     * )
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $service = Service::findOrFail($id);

        $service->update([
            'is_approved' => false,
            'is_active' => false,
            'rejection_reason' => $request->reason
        ]);

        // TODO: Notify provider

        return response()->json([
            'success' => true,
            'data' => $service,
            'message' => 'Service rejected successfully'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/services/{id}",
     *     summary="Delete a service (Admin)",
     *     operationId="adminDeleteService",
     *     tags={"Admin Services"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Service deleted")
     * )
     */
    public function destroy($id)
    {
        $service = Service::findOrFail($id);
        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service deleted successfully'
        ]);
    }
}
