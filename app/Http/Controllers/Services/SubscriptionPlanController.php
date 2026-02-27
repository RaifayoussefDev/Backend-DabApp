<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Subscription Plans",
 *     description="API endpoints for viewing and comparing subscription plans (Basic, Business, Enterprise)"
 * )
 */
class SubscriptionPlanController extends Controller
{
    /**
     * Get all subscription plans
     *
     * @OA\Get(
     *     path="/api/subscription-plans",
     *     summary="Get all subscription plans",
     *     description="Retrieve a list of all active subscription plans with pricing based on billing cycle. Returns Basic (19 SAR/month), Business (29 SAR/month), and Enterprise (39 SAR/month) plans.",
     *     operationId="getAllSubscriptionPlans",
     *     tags={"Subscription Plans"},
     *     @OA\Parameter(
     *         name="billing_cycle",
     *         in="query",
     *         description="Billing cycle to display pricing for",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"monthly", "yearly"},
     *             default="monthly"
     *         ),
     *         example="monthly"
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Basic Plan"),
     *                     @OA\Property(property="name_ar", type="string", example="الخطة الأساسية"),
     *                     @OA\Property(property="slug", type="string", example="basic-plan"),
     *                     @OA\Property(property="description", type="string", example="Perfect for getting started with essential features"),
     *                     @OA\Property(property="description_ar", type="string", example="مثالية للبدء مع الميزات الأساسية"),
     *                     @OA\Property(property="price", type="number", format="float", example=19.00, description="Price based on selected billing cycle"),
     *                     @OA\Property(property="price_monthly", type="number", format="float", example=19.00),
     *                     @OA\Property(property="price_yearly", type="number", format="float", example=190.00),
     *                     @OA\Property(property="billing_cycle", type="string", example="monthly"),
     *                     @OA\Property(property="yearly_discount_percentage", type="integer", example=17, description="Percentage saved with yearly billing"),
     *                     @OA\Property(
     *                         property="features",
     *                         type="array",
     *                         @OA\Items(type="string"),
     *                         example={"Up to 5 services", "Up to 50 bookings per month", "Basic analytics", "Email support"}
     *                     ),
     *                     @OA\Property(property="max_services", type="integer", nullable=true, example=5, description="Maximum services allowed (null = unlimited)"),
     *                     @OA\Property(property="max_bookings_per_month", type="integer", nullable=true, example=50, description="Maximum monthly bookings (null = unlimited)"),
     *                     @OA\Property(property="has_unlimited_services", type="boolean", example=false),
     *                     @OA\Property(property="has_unlimited_bookings", type="boolean", example=false),
     *                     @OA\Property(property="priority_support", type="boolean", example=false),
     *                     @OA\Property(property="analytics_access", type="boolean", example=false),
     *                     @OA\Property(property="is_featured", type="boolean", example=false, description="Most popular plan badge"),
     *                     @OA\Property(property="active_subscriptions_count", type="integer", example=15, description="Number of active subscriptions")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=3)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $billingCycle = $request->query('billing_cycle', 'monthly');
        $perPage = $request->query('per_page', 15);

        $plansPaginated = SubscriptionPlan::active()
            ->ordered()
            ->paginate($perPage);

        $plans = collect($plansPaginated->items())->map(function ($plan) use ($billingCycle) {
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'name_ar' => $plan->name_ar,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'description_ar' => $plan->description_ar,
                'price' => $billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly,
                'price_monthly' => $plan->price_monthly,
                'price_yearly' => $plan->price_yearly,
                'billing_cycle' => $billingCycle,
                'yearly_discount_percentage' => $plan->yearly_discount_percentage,
                'features' => $plan->features,
                'max_services' => $plan->max_services,
                'max_bookings_per_month' => $plan->max_bookings_per_month,
                'has_unlimited_services' => $plan->hasUnlimitedServices(),
                'has_unlimited_bookings' => $plan->hasUnlimitedBookings(),
                'priority_support' => $plan->priority_support,
                'analytics_access' => $plan->analytics_access,
                'is_featured' => $plan->is_featured,
                'active_subscriptions_count' => $plan->activeSubscriptionsCount(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $plans,
            'meta' => [
                'current_page' => $plansPaginated->currentPage(),
                'last_page' => $plansPaginated->lastPage(),
                'per_page' => $plansPaginated->perPage(),
                'total' => $plansPaginated->total(),
            ],
        ]);
    }

    /**
     * Get subscription plan by ID
     *
     * @OA\Get(
     *     path="/api/subscription-plans/{id}",
     *     summary="Get subscription plan details",
     *     description="Get detailed information about a specific subscription plan by ID",
     *     operationId="getSubscriptionPlanById",
     *     tags={"Subscription Plans"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Subscription plan ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Basic Plan"),
     *                 @OA\Property(property="name_ar", type="string", example="الخطة الأساسية"),
     *                 @OA\Property(property="slug", type="string", example="basic-plan"),
     *                 @OA\Property(property="description", type="string", example="Perfect for getting started"),
     *                 @OA\Property(property="description_ar", type="string", example="مثالية للبدء"),
     *                 @OA\Property(property="price_monthly", type="number", format="float", example=19.00),
     *                 @OA\Property(property="price_yearly", type="number", format="float", example=190.00),
     *                 @OA\Property(property="yearly_discount_percentage", type="integer", example=17),
     *                 @OA\Property(
     *                     property="features",
     *                     type="array",
     *                     @OA\Items(type="string"),
     *                     example={"Up to 5 services", "Up to 50 bookings per month"}
     *                 ),
     *                 @OA\Property(property="max_services", type="integer", nullable=true, example=5),
     *                 @OA\Property(property="max_bookings_per_month", type="integer", nullable=true, example=50),
     *                 @OA\Property(property="has_unlimited_services", type="boolean", example=false),
     *                 @OA\Property(property="has_unlimited_bookings", type="boolean", example=false),
     *                 @OA\Property(property="priority_support", type="boolean", example=false),
     *                 @OA\Property(property="analytics_access", type="boolean", example=false),
     *                 @OA\Property(property="is_featured", type="boolean", example=false),
     *                 @OA\Property(property="active_subscriptions_count", type="integer", example=15)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Plan not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Subscription plan not found or is inactive.")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        $plan = SubscriptionPlan::active()->find($id);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription plan not found or is inactive.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'name_ar' => $plan->name_ar,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'description_ar' => $plan->description_ar,
                'price_monthly' => $plan->price_monthly,
                'price_yearly' => $plan->price_yearly,
                'yearly_discount_percentage' => $plan->yearly_discount_percentage,
                'features' => $plan->features,
                'max_services' => $plan->max_services,
                'max_bookings_per_month' => $plan->max_bookings_per_month,
                'has_unlimited_services' => $plan->hasUnlimitedServices(),
                'has_unlimited_bookings' => $plan->hasUnlimitedBookings(),
                'priority_support' => $plan->priority_support,
                'analytics_access' => $plan->analytics_access,
                'is_featured' => $plan->is_featured,
                'active_subscriptions_count' => $plan->activeSubscriptionsCount(),
            ],
        ]);
    }

    /**
     * Get featured subscription plans
     *
     * @OA\Get(
     *     path="/api/subscription-plans/featured",
     *     summary="Get featured plans",
     *     description="Get all featured subscription plans (typically the most popular plan marked with 'Most Popular' badge)",
     *     operationId="getFeaturedPlans",
     *     tags={"Subscription Plans"},
     *     @OA\Parameter(
     *         name="billing_cycle",
     *         in="query",
     *         description="Billing cycle",
     *         required=false,
     *         @OA\Schema(type="string", enum={"monthly", "yearly"}, default="monthly")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Business Plan"),
     *                     @OA\Property(property="name_ar", type="string", example="خطة الأعمال"),
     *                     @OA\Property(property="slug", type="string", example="business-plan"),
     *                     @OA\Property(property="price", type="number", format="float", example=29.00),
     *                     @OA\Property(property="billing_cycle", type="string", example="monthly"),
     *                     @OA\Property(property="is_featured", type="boolean", example=true)
     *                 )
     *             ),
     *             example={
     *                 "success": true,
     *                 "data": {
     *                     {
     *                         "id": 2,
     *                         "name": "Business Plan",
     *                         "name_ar": "خطة الأعمال",
     *                         "slug": "business-plan",
     *                         "price": 29.00,
     *                         "billing_cycle": "monthly",
     *                         "is_featured": true
     *                     }
     *                 }
     *             }
     *         )
     *     )
     * )
     */
    public function featured(Request $request)
    {
        $billingCycle = $request->query('billing_cycle', 'monthly');

        $plans = SubscriptionPlan::active()
            ->featured()
            ->ordered()
            ->get()
            ->map(function ($plan) use ($billingCycle) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'name_ar' => $plan->name_ar,
                    'slug' => $plan->slug,
                    'price' => $billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly,
                    'billing_cycle' => $billingCycle,
                    'is_featured' => true,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * Compare subscription plans
     *
     * @OA\Get(
     *     path="/api/subscription-plans/compare",
     *     summary="Compare plans side by side",
     *     description="Compare multiple subscription plans with detailed features and pricing. If no plan IDs provided, returns all plans for comparison.",
     *     operationId="comparePlans",
     *     tags={"Subscription Plans"},
     *     @OA\Parameter(
     *         name="plans[]",
     *         in="query",
     *         description="Array of plan IDs to compare (optional, if empty returns all plans)",
     *         required=false,
     *         style="form",
     *         explode=true,
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(type="integer", example=1)
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="billing_cycle",
     *         in="query",
     *         description="Billing cycle",
     *         required=false,
     *         @OA\Schema(type="string", enum={"monthly", "yearly"}, default="monthly")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Basic Plan"),
     *                     @OA\Property(property="name_ar", type="string", example="الخطة الأساسية"),
     *                     @OA\Property(property="price", type="number", format="float", example=19.00),
     *                     @OA\Property(property="price_monthly", type="number", format="float", example=19.00),
     *                     @OA\Property(property="price_yearly", type="number", format="float", example=190.00),
     *                     @OA\Property(property="billing_cycle", type="string", example="monthly"),
     *                     @OA\Property(property="yearly_savings", type="number", format="float", example=38.00, description="Amount saved with yearly billing"),
     *                     @OA\Property(property="features", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="max_services", type="string", example="5", description="Number or ∞ for unlimited"),
     *                     @OA\Property(property="max_bookings_per_month", type="string", example="50", description="Number or ∞ for unlimited"),
     *                     @OA\Property(property="priority_support", type="boolean", example=false),
     *                     @OA\Property(property="analytics_access", type="boolean", example=false),
     *                     @OA\Property(property="is_featured", type="boolean", example=false)
     *                 )
     *             ),
     *             example={
     *                 "success": true,
     *                 "data": {
     *                     {
     *                         "id": 1,
     *                         "name": "Basic Plan",
     *                         "name_ar": "الخطة الأساسية",
     *                         "price": 19.00,
     *                         "price_monthly": 19.00,
     *                         "price_yearly": 190.00,
     *                         "billing_cycle": "monthly",
     *                         "yearly_savings": 38.00,
     *                         "features": {"Up to 5 services", "Up to 50 bookings per month"},
     *                         "max_services": "5",
     *                         "max_bookings_per_month": "50",
     *                         "priority_support": false,
     *                         "analytics_access": false,
     *                         "is_featured": false
     *                     },
     *                     {
     *                         "id": 2,
     *                         "name": "Business Plan",
     *                         "name_ar": "خطة الأعمال",
     *                         "price": 29.00,
     *                         "price_monthly": 29.00,
     *                         "price_yearly": 290.00,
     *                         "billing_cycle": "monthly",
     *                         "yearly_savings": 58.00,
     *                         "features": {"Up to 15 services", "Up to 200 bookings per month"},
     *                         "max_services": "15",
     *                         "max_bookings_per_month": "200",
     *                         "priority_support": true,
     *                         "analytics_access": true,
     *                         "is_featured": true
     *                     },
     *                     {
     *                         "id": 3,
     *                         "name": "Enterprise Plan",
     *                         "name_ar": "الخطة المؤسسية",
     *                         "price": 39.00,
     *                         "price_monthly": 39.00,
     *                         "price_yearly": 390.00,
     *                         "billing_cycle": "monthly",
     *                         "yearly_savings": 78.00,
     *                         "features": {"Unlimited services", "Unlimited bookings"},
     *                         "max_services": "∞",
     *                         "max_bookings_per_month": "∞",
     *                         "priority_support": true,
     *                         "analytics_access": true,
     *                         "is_featured": false
     *                     }
     *                 }
     *             }
     *         )
     *     )
     * )
     */
    public function compare(Request $request)
    {
        $planIds = $request->query('plans', []);
        $billingCycle = $request->query('billing_cycle', 'monthly');

        if (empty($planIds)) {
            $plans = SubscriptionPlan::active()->ordered()->get();
        } else {
            $plans = SubscriptionPlan::active()->whereIn('id', $planIds)->ordered()->get();
        }

        $comparison = $plans->map(function ($plan) use ($billingCycle) {
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'name_ar' => $plan->name_ar,
                'price' => $billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly,
                'price_monthly' => $plan->price_monthly,
                'price_yearly' => $plan->price_yearly,
                'billing_cycle' => $billingCycle,
                'yearly_savings' => ($plan->price_monthly * 12) - $plan->price_yearly,
                'features' => $plan->features,
                'max_services' => $plan->max_services ?? '∞',
                'max_bookings_per_month' => $plan->max_bookings_per_month ?? '∞',
                'priority_support' => $plan->priority_support,
                'analytics_access' => $plan->analytics_access,
                'is_featured' => $plan->is_featured,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $comparison,
        ]);
    }
}