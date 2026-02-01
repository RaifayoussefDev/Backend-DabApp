<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ServiceSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin User Subscriptions",
 *     description="API endpoints for managing user subscriptions (Admin)"
 * )
 */
class AdminSubscriptionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/subscriptions",
     *     summary="List all user subscriptions (Admin)",
     *     operationId="adminGetSubscriptions",
     *     tags={"Admin User Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", description="Filter by status (active, cancelled, expired, trial)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="plan_id", in="query", description="Filter by plan ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="provider_id", in="query", description="Filter by provider ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Subscriptions retrieved successfully",
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
     *                         @OA\Property(property="id", type="integer", example=123),
     *                         @OA\Property(property="status", type="string", example="active"),
     *                         @OA\Property(property="billing_cycle", type="string", example="monthly"),
     *                         @OA\Property(property="start_date", type="string", format="date", example="2026-01-01"),
     *                         @OA\Property(property="next_billing_date", type="string", format="date", example="2026-02-01"),
     *                         @OA\Property(property="plan", type="object", @OA\Property(property="name", type="string", example="Pro Plan")),
     *                         @OA\Property(property="provider", type="object", @OA\Property(property="id", type="integer", example=50), @OA\Property(property="name", type="string", example="Ahmed Provider"))
     *                     )
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=120)
     *             ),
     *             @OA\Property(property="message", type="string", example="Subscriptions retrieved successfully")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = ServiceSubscription::with(['provider.user', 'plan']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        if ($request->has('provider_id')) {
            $query->where('provider_id', $request->provider_id);
        }

        $perPage = $request->input('per_page');

        if ($perPage) {
            $subscriptions = $query->orderBy('created_at', 'desc')->paginate($perPage);
        } else {
            $subscriptions = $query->orderBy('created_at', 'desc')->get();
        }

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
            'message' => 'Subscriptions retrieved successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/subscriptions/{id}",
     *     summary="Get subscription details (Admin)",
     *     operationId="adminGetSubscription",
     *     tags={"Admin User Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Subscription details retrieved")
     * )
     */
    public function show($id)
    {
        $subscription = ServiceSubscription::with(['provider.user', 'plan', 'transactions'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $subscription,
            'message' => 'Subscription details retrieved successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/subscriptions/{id}/cancel",
     *     summary="Cancel a subscription (Admin)",
     *     operationId="adminCancelSubscription",
     *     tags={"Admin User Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", example="Violation of terms")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription cancelled successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=123),
     *                 @OA\Property(property="status", type="string", example="cancelled"),
     *                 @OA\Property(property="cancellation_reason", type="string", example="Admin Cancellation: Violation of terms")
     *             ),
     *             @OA\Property(property="message", type="string", example="Subscription cancelled successfully")
     *         )
     *     )
     * )
     */
    public function cancel(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $subscription = ServiceSubscription::findOrFail($id);

        if ($subscription->isCancelled()) {
            return response()->json(['success' => false, 'message' => 'Subscription is already cancelled'], 400);
        }

        $subscription->cancel('Admin Cancellation: ' . $request->reason);

        return response()->json([
            'success' => true,
            'data' => $subscription,
            'message' => 'Subscription cancelled successfully'
        ]);
    }
}
