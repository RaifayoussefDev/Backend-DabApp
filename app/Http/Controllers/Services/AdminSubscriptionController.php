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
    /**
     * @OA\Post(
     *     path="/api/admin/subscriptions",
     *     summary="Create a new subscription manually (Admin)",
     *     operationId="adminCreateSubscription",
     *     tags={"Admin User Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"provider_id", "plan_id", "billing_cycle"},
     *             @OA\Property(property="provider_id", type="integer", example=10),
     *             @OA\Property(property="plan_id", type="integer", example=2),
     *             @OA\Property(property="status", type="string", example="active", enum={"active", "pending", "cancelled", "expired", "trial"}),
     *             @OA\Property(property="billing_cycle", type="string", example="monthly", enum={"monthly", "yearly"}),
     *             @OA\Property(property="starts_at", type="string", format="date", example="2026-02-11"),
     *             @OA\Property(property="expires_at", type="string", format="date", example="2026-03-11")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Subscription created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider_id' => 'required|exists:service_providers,id',
            'plan_id' => 'required|exists:subscription_plans,id',
            'status' => 'string|in:active,pending,cancelled,expired,trial',
            'billing_cycle' => 'required|in:monthly,yearly',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Deactivate existing active subscriptions for this provider if the new one is active
        if ($request->input('status', 'active') === 'active') {
            ServiceSubscription::where('provider_id', $request->provider_id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        }

        $startsAt = $request->input('starts_at', now());
        $expiresAt = $request->input('expires_at');

        // If expires_at is not provided, calculate based on billing cycle
        if (!$expiresAt) {
            $date = \Carbon\Carbon::parse($startsAt);
            $expiresAt = $request->billing_cycle === 'yearly'
                ? $date->copy()->addYear()
                : $date->copy()->addMonth();
        }

        $subscription = ServiceSubscription::create([
            'provider_id' => $request->provider_id,
            'plan_id' => $request->plan_id,
            'status' => $request->input('status', 'active'),
            'billing_cycle' => $request->billing_cycle,
            'current_period_start' => $startsAt,
            'current_period_end' => $expiresAt,
            'next_billing_date' => $expiresAt, // Assuming renewal happens at end of period
            'auto_renew' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $subscription->load(['provider.user', 'plan']),
            'message' => 'Subscription created successfully'
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/subscriptions/{id}",
     *     summary="Update a subscription (Admin)",
     *     operationId="adminUpdateSubscription",
     *     tags={"Admin User Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="plan_id", type="integer"),
     *             @OA\Property(property="status", type="string", enum={"active", "pending", "cancelled", "expired", "trial"}),
     *             @OA\Property(property="expires_at", type="string", format="date")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Subscription updated successfully")
     * )
     */
    public function update(Request $request, $id)
    {
        $subscription = ServiceSubscription::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'plan_id' => 'exists:subscription_plans,id',
            'status' => 'string|in:active,pending,cancelled,expired,trial',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->only(['plan_id', 'status']);

        if ($request->has('expires_at')) {
            $data['current_period_end'] = $request->expires_at;
            $data['next_billing_date'] = $request->expires_at;
        }

        $subscription->update($data);

        return response()->json([
            'success' => true,
            'data' => $subscription->load(['provider.user', 'plan']),
            'message' => 'Subscription updated successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/subscriptions/stats",
     *     summary="Get subscription statistics (Admin)",
     *     operationId="adminGetSubscriptionStats",
     *     tags={"Admin User Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_subscriptions", type="integer", example=150),
     *                 @OA\Property(property="active_subscriptions", type="integer", example=120),
     *                 @OA\Property(property="cancelled_subscriptions", type="integer", example=10),
     *                 @OA\Property(property="expired_subscriptions", type="integer", example=20),
     *                 @OA\Property(property="revenue_this_month", type="number", example=5000.00),
     *                 @OA\Property(property="subscriptions_by_plan", type="object")
     *             ),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function statistics()
    {
        $total = ServiceSubscription::count();
        $active = ServiceSubscription::where('status', 'active')->count();
        $cancelled = ServiceSubscription::where('status', 'cancelled')->count();
        $expired = ServiceSubscription::where('status', 'expired')->count();
        $trial = ServiceSubscription::where('status', 'trial')->count();

        $byPlan = ServiceSubscription::selectRaw('plan_id, count(*) as count')
            ->groupBy('plan_id')
            ->with('plan:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'plan_name' => $item->plan ? $item->plan->name : 'Unknown',
                    'count' => $item->count
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'total_subscriptions' => $total,
                'active_subscriptions' => $active,
                'cancelled_subscriptions' => $cancelled,
                'expired_subscriptions' => $expired,
                'trial_subscriptions' => $trial,
                'subscriptions_by_plan' => $byPlan
            ],
            'message' => 'Statistics retrieved successfully'
        ]);
    }
}
