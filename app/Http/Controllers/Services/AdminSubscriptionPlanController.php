<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Admin Subscription Plans",
 *     description="API endpoints for managing subscription plans (Admin)"
 * )
 */
class AdminSubscriptionPlanController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/subscription-plans",
     *     summary="List all subscription plans (Admin)",
     *     description="Retrieve a list of all subscription plans, including inactive ones.",
     *     operationId="adminGetSubscriptionPlans",
     *     tags={"Admin Subscription Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page (default: all)", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_active", in="query", description="Filter by active status", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription plans retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Pro Plan"),
     *                     @OA\Property(property="slug", type="string", example="pro-plan"),
     *                     @OA\Property(property="price_monthly", type="number", example=49.00),
     *                     @OA\Property(property="price_yearly", type="number", example=490.00),
     *                     @OA\Property(property="features", type="array", @OA\Items(type="string", example="Priority Support")),
     *                     @OA\Property(property="is_active", type="boolean", example=true)
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Subscription plans retrieved successfully")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = SubscriptionPlan::orderBy('order_position', 'asc');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->input('per_page');

        if ($perPage) {
            $plans = $query->paginate($perPage);
        } else {
            $plans = $query->get();
        }

        return response()->json([
            'success' => true,
            'data' => $plans,
            'message' => 'Subscription plans retrieved successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/subscription-plans",
     *     summary="Create a new subscription plan (Admin)",
     *     operationId="adminCreateSubscriptionPlan",
     *     tags={"Admin Subscription Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "name_ar", "slug", "price_monthly", "price_yearly"},
     *             @OA\Property(property="name", type="string", example="Pro Plan"),
     *             @OA\Property(property="name_ar", type="string", example="الخطة الاحترافية"),
     *             @OA\Property(property="slug", type="string", example="pro-plan"),
     *             @OA\Property(property="price_monthly", type="number", format="float", example=49.00),
     *             @OA\Property(property="price_yearly", type="number", format="float", example=490.00),
     *             @OA\Property(property="features", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="max_services", type="integer", nullable=true),
     *             @OA\Property(property="max_bookings_per_month", type="integer", nullable=true),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Subscription plan created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="name", type="string", example="Business Plan"),
     *                 @OA\Property(property="price_monthly", type="number", example=99.00),
     *                 @OA\Property(property="is_active", type="boolean", example=true)
     *             ),
     *             @OA\Property(property="message", type="string", example="Subscription plan created successfully")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $data = $request->all();

        // Generate slug if not provided or empty
        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'slug' => 'required|string|unique:subscription_plans,slug|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'features' => 'nullable|array',
            'max_services' => 'nullable|integer',
            'max_bookings_per_month' => 'nullable|integer',
            'priority_support' => 'boolean',
            'analytics_access' => 'boolean',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'order_position' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $plan = SubscriptionPlan::create($data);

        return response()->json([
            'success' => true,
            'data' => $plan,
            'message' => 'Subscription plan created successfully'
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/subscription-plans/{id}",
     *     summary="Get subscription plan details (Admin)",
     *     operationId="adminGetSubscriptionPlan",
     *     tags={"Admin Subscription Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Plan details retrieved")
     * )
     */
    public function show($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $plan,
            'message' => 'Subscription plan retrieved successfully'
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/subscription-plans/{id}",
     *     summary="Update subscription plan (Admin)",
     *     operationId="adminUpdateSubscriptionPlan",
     *     tags={"Admin Subscription Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/SubscriptionPlan")
     *     ),
     *     @OA\Response(response=200, description="Plan updated successfully")
     * )
     */
    public function update(Request $request, $id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $data = $request->all();

        // Generate slug if explicitly provided as null/empty, or if not provided but name changed (optional, but requested behavior implies automation)
        // For safety, only if empty/null and name is present.
        if (array_key_exists('slug', $data) && empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        } elseif (!array_key_exists('slug', $data) && !empty($data['name']) && empty($plan->slug)) {
            // Edge case: if plan has no slug (unlikely) and we update name.
            $data['slug'] = Str::slug($data['name']);
        }

        $validator = Validator::make($data, [
            'name' => 'string|max:255',
            'name_ar' => 'string|max:255',
            'slug' => 'string|max:255|unique:subscription_plans,slug,' . $id,
            'price_monthly' => 'numeric|min:0',
            'price_yearly' => 'numeric|min:0',
            'features' => 'array',
            'max_services' => 'nullable|integer',
            'max_bookings_per_month' => 'nullable|integer',
            'priority_support' => 'boolean',
            'analytics_access' => 'boolean',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'order_position' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $plan->update($data);

        return response()->json([
            'success' => true,
            'data' => $plan,
            'message' => 'Subscription plan updated successfully'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/subscription-plans/{id}",
     *     summary="Delete subscription plan (Admin)",
     *     operationId="adminDeleteSubscriptionPlan",
     *     tags={"Admin Subscription Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Plan deleted successfully")
     * )
     */
    public function destroy($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        // Optional: Check if plan has active subscriptions before deleting
        if ($plan->activeSubscriptionsCount() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete plan with active subscriptions. Deactivate it instead.'
            ], 400);
        }

        $plan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subscription plan deleted successfully'
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/subscription-plans/{id}/toggle-status",
     *     summary="Toggle subscription plan active status (Admin)",
     *     operationId="adminToggleSubscriptionPlanStatus",
     *     tags={"Admin Subscription Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Plan status updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="is_active", type="boolean", example=false)
     *             ),
     *             @OA\Property(property="message", type="string", example="Subscription plan deactivated successfully")
     *         )
     *     )
     * )
     */
    public function toggleStatus($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $plan->is_active = !$plan->is_active;
        $plan->save();

        return response()->json([
            'success' => true,
            'data' => $plan,
            'message' => 'Subscription plan ' . ($plan->is_active ? 'activated' : 'deactivated') . ' successfully'
        ]);
    }
}
