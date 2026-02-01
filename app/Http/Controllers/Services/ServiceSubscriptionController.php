<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ServiceSubscription;
use App\Models\SubscriptionPlan;
use App\Models\ServiceProvider;
use App\Models\SubscriptionTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="Service Subscriptions",
 *     description="API endpoints for managing service provider subscriptions"
 * )
 */
class ServiceSubscriptionController extends Controller
{
    /**
     * Get current provider's subscription
     *
     * @OA\Get(
     *     path="/api/my-subscription",
     *     summary="Get current provider's subscription",
     *     description="Retrieve the active subscription details for the authenticated service provider including usage statistics and features",
     *     operationId="getMySubscription",
     *     tags={"Service Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="has_subscription", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="subscription",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="status", type="string", enum={"active", "cancelled", "expired", "payment_failed", "trial"}, example="active"),
     *                     @OA\Property(
     *                         property="plan",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="name", type="string", example="Business Plan"),
     *                         @OA\Property(property="name_ar", type="string", example="خطة الأعمال")
     *                     ),
     *                     @OA\Property(property="billing_cycle", type="string", enum={"monthly", "yearly"}, example="monthly"),
     *                     @OA\Property(property="current_price", type="number", format="float", example=29.00),
     *                     @OA\Property(property="current_period_start", type="string", format="date", example="2026-01-01"),
     *                     @OA\Property(property="current_period_end", type="string", format="date", example="2026-02-01"),
     *                     @OA\Property(property="next_billing_date", type="string", format="date", example="2026-02-01"),
     *                     @OA\Property(property="days_until_renewal", type="integer", example=15),
     *                     @OA\Property(property="auto_renew", type="boolean", example=true),
     *                     @OA\Property(property="on_trial", type="boolean", example=false),
     *                     @OA\Property(property="trial_ends_at", type="string", format="date", nullable=true, example=null)
     *                 ),
     *                 @OA\Property(
     *                     property="usage",
     *                     type="object",
     *                     @OA\Property(property="services_used", type="integer", example=8, description="Number of services currently created"),
     *                     @OA\Property(property="services_quota", type="integer", nullable=true, example=7, description="Remaining services quota (null means unlimited)"),
     *                     @OA\Property(property="bookings_this_month", type="integer", example=45, description="Bookings received this month"),
     *                     @OA\Property(property="bookings_quota", type="integer", nullable=true, example=155, description="Remaining bookings quota (null means unlimited)")
     *                 ),
     *                 @OA\Property(
     *                     property="features",
     *                     type="object",
     *                     @OA\Property(property="priority_support", type="boolean", example=true),
     *                     @OA\Property(property="analytics_access", type="boolean", example=true)
     *                 )
     *             ),
     *             example={
     *                 "success": true,
     *                 "data": {
     *                     "has_subscription": true,
     *                     "subscription": {
     *                         "id": 1,
     *                         "status": "active",
     *                         "plan": {
     *                             "id": 2,
     *                             "name": "Business Plan",
     *                             "name_ar": "خطة الأعمال"
     *                         },
     *                         "billing_cycle": "monthly",
     *                         "current_price": 29.00,
     *                         "current_period_start": "2026-01-01",
     *                         "current_period_end": "2026-02-01",
     *                         "next_billing_date": "2026-02-01",
     *                         "days_until_renewal": 15,
     *                         "auto_renew": true,
     *                         "on_trial": false,
     *                         "trial_ends_at": null
     *                     },
     *                     "usage": {
     *                         "services_used": 8,
     *                         "services_quota": 7,
     *                         "bookings_this_month": 45,
     *                         "bookings_quota": 155
     *                     },
     *                     "features": {
     *                         "priority_support": true,
     *                         "analytics_access": true
     *                     }
     *                 }
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Service provider not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Service provider not found")
     *         )
     *     )
     * )
     */
    public function mySubscription(Request $request)
    {
        $user = $request->user();
        $provider = ServiceProvider::where('user_id', $user->id)->firstOrFail();

        $subscription = $provider->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_subscription' => false,
                    'message' => 'No active subscription found',
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'has_subscription' => true,
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'plan' => [
                        'id' => $subscription->plan->id,
                        'name' => $subscription->plan->name,
                        'name_ar' => $subscription->plan->name_ar,
                    ],
                    'billing_cycle' => $subscription->billing_cycle,
                    'current_price' => $subscription->current_price,
                    'current_period_start' => $subscription->current_period_start->format('Y-m-d'),
                    'current_period_end' => $subscription->current_period_end->format('Y-m-d'),
                    'next_billing_date' => $subscription->next_billing_date?->format('Y-m-d'),
                    'days_until_renewal' => $subscription->days_until_renewal,
                    'auto_renew' => $subscription->auto_renew,
                    'on_trial' => $subscription->onTrial(),
                    'trial_ends_at' => $subscription->trial_ends_at?->format('Y-m-d'),
                ],
                'usage' => [
                    'services_used' => $provider->services()->count(),
                    'services_quota' => $provider->remaining_services_quota,
                    'bookings_this_month' => $provider->bookings()
                        ->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year)
                        ->count(),
                    'bookings_quota' => $provider->remaining_bookings_quota,
                ],
                'features' => [
                    'priority_support' => $provider->hasPrioritySupport(),
                    'analytics_access' => $provider->hasAnalyticsAccess(),
                ],
            ],
        ]);
    }

    /**
     * Subscribe to a plan
     *
     * @OA\Post(
     *     path="/api/subscriptions/subscribe",
     *     summary="Subscribe to a plan",
     *     description="Create a new subscription for the authenticated service provider. Provider must not have an active subscription.",
     *     operationId="subscribeToAPlan",
     *     tags={"Service Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Subscription details",
     *         @OA\JsonContent(
     *             required={"plan_id", "billing_cycle"},
     *             @OA\Property(
     *                 property="plan_id",
     *                 type="integer",
     *                 example=2,
     *                 description="ID of the subscription plan (1=Basic, 2=Business, 3=Enterprise)"
     *             ),
     *             @OA\Property(
     *                 property="billing_cycle",
     *                 type="string",
     *                 enum={"monthly", "yearly"},
     *                 example="monthly",
     *                 description="Billing frequency"
     *             ),
     *             @OA\Property(
     *                 property="payment_method_id",
     *                 type="integer",
     *                 nullable=true,
     *                 example=1,
     *                 description="Card type ID (optional)"
     *             ),
     *             @OA\Property(
     *                 property="bank_card_id",
     *                 type="integer",
     *                 nullable=true,
     *                 example=5,
     *                 description="Saved bank card ID (optional)"
     *             ),
     *             example={
     *                 "plan_id": 2,
     *                 "billing_cycle": "monthly",
     *                 "payment_method_id": 1,
     *                 "bank_card_id": 5
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Subscription created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Successfully subscribed to Business Plan"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="subscription_id", type="integer", example=1),
     *                 @OA\Property(property="transaction_id", type="integer", example=1),
     *                 @OA\Property(property="invoice_number", type="string", example="INV-SUB-20260130-A1B2C3"),
     *                 @OA\Property(property="amount", type="number", format="float", example=29.00),
     *                 @OA\Property(property="next_billing_date", type="string", format="date", example="2026-02-30")
     *             ),
     *             example={
     *                 "success": true,
     *                 "message": "Successfully subscribed to Business Plan",
     *                 "data": {
     *                     "subscription_id": 1,
     *                     "transaction_id": 1,
     *                     "invoice_number": "INV-SUB-20260130-A1B2C3",
     *                     "amount": 29.00,
     *                     "next_billing_date": "2026-02-30"
     *                 }
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Provider already has active subscription",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You already have an active subscription. Please cancel it first or upgrade instead.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="plan_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The plan id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="billing_cycle",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected billing cycle is invalid.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create subscription: Database connection error")
     *         )
     *     )
     * )
     */
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:subscription_plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'payment_method_id' => 'nullable|exists:card_types,id',
            'bank_card_id' => 'nullable|exists:bank_cards,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $provider = ServiceProvider::where('user_id', $user->id)->firstOrFail();

        if ($provider->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active subscription. Please cancel it first or upgrade instead.',
            ], 400);
        }

        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $billingCycle = $request->billing_cycle;
        $amount = $billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;

        DB::beginTransaction();
        try {
            $startDate = now();
            $endDate = $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth();

            $subscription = ServiceSubscription::create([
                'provider_id' => $provider->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'status' => 'active',
                'current_period_start' => $startDate,
                'current_period_end' => $endDate,
                'next_billing_date' => $endDate,
                'payment_method_id' => $request->payment_method_id,
                'bank_card_id' => $request->bank_card_id,
                'auto_renew' => true,
            ]);

            $transaction = SubscriptionTransaction::create([
                'subscription_id' => $subscription->id,
                'amount' => $amount,
                'currency' => 'SAR',
                'transaction_type' => 'subscription',
                'status' => 'completed',
                'billing_period_start' => $startDate,
                'billing_period_end' => $endDate,
                'invoice_number' => SubscriptionTransaction::generateInvoiceNumber(),
                'processed_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Successfully subscribed to ' . $plan->name,
                'data' => [
                    'subscription_id' => $subscription->id,
                    'transaction_id' => $transaction->id,
                    'invoice_number' => $transaction->invoice_number,
                    'amount' => $amount,
                    'next_billing_date' => $endDate->format('Y-m-d'),
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel subscription
     *
     * @OA\Post(
     *     path="/api/subscriptions/cancel",
     *     summary="Cancel active subscription",
     *     description="Cancel the current active subscription. Access will continue until the end of the current billing period.",
     *     operationId="cancelSubscription",
     *     tags={"Service Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="reason",
     *                 type="string",
     *                 maxLength=500,
     *                 nullable=true,
     *                 example="Too expensive for my needs",
     *                 description="Optional cancellation reason"
     *             ),
     *             example={
     *                 "reason": "Switching to a different service"
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subscription cancelled successfully. You will have access until 2026-02-30"),
     *             example={
     *                 "success": true,
     *                 "message": "Subscription cancelled successfully. You will have access until 2026-02-30"
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No active subscription found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No active subscription found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="reason",
     *                     type="array",
     *                     @OA\Items(type="string", example="The reason must not be greater than 500 characters.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function cancel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $provider = ServiceProvider::where('user_id', $user->id)->firstOrFail();

        $subscription = $provider->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found',
            ], 404);
        }

        $subscription->cancel($request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled successfully. You will have access until ' .
                $subscription->current_period_end->format('Y-m-d'),
        ]);
    }
}