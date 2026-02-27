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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ServiceProviderImage;
use App\Services\PayTabsConfigService;

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
        $provider = ServiceProvider::where('user_id', $user->id)->first();

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Service provider profile not found for the authenticated user.',
            ], 404);
        }

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
     *     description="Create a new subscription. If the user is not yet a service provider, a provider profile will be automatically created.",
     *     operationId="subscribeToAPlan",
     *     tags={"Service Subscriptions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Subscription details with optional provider profile and images",
     *         @OA\JsonContent(
     *             required={"plan_id", "billing_cycle"},
     *             @OA\Property(property="plan_id", type="integer", example=2, description="ID of the subscription plan"),
     *             @OA\Property(property="billing_cycle", type="string", enum={"monthly", "yearly"}, example="monthly"),
     *             @OA\Property(property="payment_method_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="bank_card_id", type="integer", nullable=true, example=5),
     *             @OA\Property(property="business_name", type="string", example="My Car Service"),
     *             @OA\Property(property="business_name_ar", type="string", example="خدمة سيارتي"),
     *             @OA\Property(property="description", type="string", example="Best car service"),
     *             @OA\Property(property="description_ar", type="string", example="أفضل خدمة سيارات"),
     *             @OA\Property(property="phone", type="string", example="+966500000000"),
     *             @OA\Property(property="email", type="string", format="email", example="contact@carservice.com"),
     *             @OA\Property(property="address", type="string", example="123 Main St"),
     *             @OA\Property(property="address_ar", type="string", example="123 شارع الرئيسي"),
     *             @OA\Property(property="city_id", type="integer", example=1),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="latitude", type="number", format="float", example=24.7136),
     *             @OA\Property(property="longitude", type="number", format="float", example=46.6753),
     *             @OA\Property(property="logo", type="string", example="https://example.com/logo.png", description="Provider Logo URL"),
     *             @OA\Property(property="cover_image", type="string", example="https://example.com/cover.png", description="Cover Image URL"),
     *             @OA\Property(
     *                 property="images",
     *                 type="array",
     *                 @OA\Items(type="string", example="https://example.com/image1.png"),
     *                 description="Gallery Image URLs"
     *             )
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
            // Optional provider details
            'business_name' => 'nullable|string|max:255',
            'business_name_ar' => 'nullable|string|max:255',
            'city_id' => 'nullable|exists:cities,id',
            'country_id' => 'nullable|exists:countries,id',
            'address' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address_ar' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'logo' => 'nullable|string',
            'cover_image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Check if user is already a provider, if not create one
        $provider = ServiceProvider::where('user_id', $user->id)->first();

        if (!$provider) {
            // Auto-create provider profile
            try {
                $provider = ServiceProvider::create([
                    'user_id' => $user->id,
                    'business_name' => $request->business_name ?? $user->name ?? $user->first_name . ' ' . $user->last_name,
                    'business_name_ar' => $request->business_name_ar ?? $user->name ?? $user->first_name . ' ' . $user->last_name,
                    'description' => $request->description,
                    'description_ar' => $request->description_ar,
                    'logo' => $request->logo,
                    'cover_image' => $request->cover_image,
                    'email' => $request->email ?? $user->email,
                    'phone' => $request->phone ?? $user->phone,
                    'address' => $request->address,
                    'address_ar' => $request->address_ar,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'is_active' => false, // Inactive until payment is confirmed
                    'is_verified' => false, // Always false initially
                    'rating_average' => 0,
                    'reviews_count' => 0,
                    'services_count' => 0,
                    'completed_orders' => 0,
                    'city_id' => $request->city_id ?? $user->city_id ?? null,
                    'country_id' => $request->country_id ?? $user->country_id ?? null,
                ]);

                // Handle Gallery Images (URLs)
                if ($request->has('images') && is_array($request->images)) {
                    foreach ($request->images as $index => $imageUrl) {
                        ServiceProviderImage::create([
                            'provider_id' => $provider->id,
                            'image_url' => $imageUrl,
                            'order_position' => $index,
                            'is_featured' => $index === 0 // First image as featured by default
                        ]);
                    }
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create provider profile: ' . $e->getMessage(),
                ], 500);
            }
        }

        if ($provider->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active subscription.',
            ], 400);
        }

        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $billingCycle = $request->billing_cycle;
        $amount = $billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;

        DB::beginTransaction();
        try {
            // 1. Create Payment Record (Pending)
            $cartId = 'sub_' . time() . '_' . $provider->id;
            $payment = \App\Models\Payment::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'payment_status' => 'pending',
                'cart_id' => $cartId,
                'listing_id' => null, // Explicitly null
            ]);

            // 2. Create Subscription (Pending)
            $startDate = now();
            $endDate = $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth();

            $subscription = ServiceSubscription::create([
                'provider_id' => $provider->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'status' => 'pending', // Waiting for payment
                'current_period_start' => $startDate,
                'current_period_end' => $endDate,
                // These will be confirmed upon payment success
                'auto_renew' => true,
            ]);

            // 3. Create Transaction (Pending)
            $transaction = SubscriptionTransaction::create([
                'subscription_id' => $subscription->id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'currency' => 'SAR', // Should come from config
                'transaction_type' => 'subscription',
                'status' => 'pending',
                'billing_period_start' => $startDate,
                'billing_period_end' => $endDate,
                'invoice_number' => SubscriptionTransaction::generateInvoiceNumber(),
                'processed_at' => null,
            ]);

            DB::commit();

            // 4. Initiate PayTabs Payment
            // Get active configuration
            $config = PayTabsConfigService::getConfig();
            $baseUrl = PayTabsConfigService::getBaseUrl();

            $callbackUrl = route('api.subscriptions.callback');
            // Append payment_id to return URL so we can verify it even if PayTabs params are missing
            $returnUrl = route('api.subscriptions.return', ['payment_id' => $payment->id]);

            $paymentData = [
                "profile_id" => $config['profile_id'],
                "tran_type" => "sale",
                "tran_class" => "ecom",
                "cart_id" => $cartId,
                "cart_description" => 'Subscription: ' . $plan->name,
                "cart_currency" => $config['currency'],
                "cart_amount" => $amount,
                "callback" => $callbackUrl,
                "return" => $returnUrl,
                "customer_details" => [
                    "name" => $user->name,
                    "email" => $user->email,
                    "phone" => $user->phone ?? '0000000000',
                    "street1" => 'N/A',
                    "city" => 'N/A',
                    "state" => 'N/A',
                    "country" => $config['region'],
                    "zip" => '00000'
                ]
            ];

            Log::info('Creating PayTabs subscription payment', $paymentData);

            $response = Http::withHeaders([
                "Authorization" => $config['server_key'],
                "Content-Type" => "application/json",
                "Accept" => "application/json"
            ])->post($baseUrl . 'payment/request', $paymentData);

            if (!$response->successful()) {
                throw new \Exception('PayTabs API Error: ' . $response->body());
            }

            $responseData = $response->json();

            if (!isset($responseData['redirect_url'])) {
                throw new \Exception('PayTabs API Error: No redirect URL returned');
            }

            // Save tran_ref immediately specific to this payment
            if (isset($responseData['tran_ref'])) {
                $payment->update(['tran_ref' => $responseData['tran_ref']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated',
                'data' => [
                    'payment_url' => $responseData['redirect_url'],
                    'subscription_id' => $subscription->id,
                    'transaction_id' => $transaction->id,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate subscription: ' . $e->getMessage(),
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
        $provider = ServiceProvider::where('user_id', $user->id)->first();

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Service provider profile not found for the authenticated user.',
            ], 404);
        }

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

    /**
     * Handle PayTabs Callback
     */
    public function handleCallback(Request $request)
    {
        $tranRef = $request->input('tran_ref');
        $cartId = $request->input('cart_id');

        if (!$tranRef && !$cartId) {
            return response()->json(['error' => 'Missing data'], 400);
        }

        // Find Payment
        $payment = \App\Models\Payment::where(function ($query) use ($tranRef, $cartId) {
            if ($tranRef)
                $query->where('tran_ref', $tranRef);
            if ($cartId)
                $query->orWhere('cart_id', $cartId);
        })->first();

        if (!$payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        // Find associated Subscription Transaction
        $transaction = SubscriptionTransaction::where('payment_id', $payment->id)->first();
        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        $subscription = $transaction->subscription;

        // Process PayTabs Response
        $responseStatus = $request->input('response_status'); // A = Authorized, P = Pending, ...
        $requestResponseCode = $request->input('response_code');
        $paymentResult = $request->input('payment_result');

        if ($responseStatus === 'A' || strtolower($paymentResult) === 'completed') {
            // Success
            $payment->update([
                'payment_status' => 'completed',
                'tran_ref' => $tranRef,
                'resp_code' => $requestResponseCode,
                'resp_message' => 'Payment successful'
            ]);

            $transaction->update([
                'status' => 'completed',
                'processed_at' => now()
            ]);

            $subscription->update([
                'status' => 'active',
                'next_billing_date' => $subscription->current_period_end
            ]);

            // Activate Provider
            $subscription->provider->update(['is_active' => true]);

            return response()->json(['success' => true, 'message' => 'Subscription activated']);

        } else {
            // Failed
            $payment->update([
                'payment_status' => 'failed',
                'tran_ref' => $tranRef,
                'resp_code' => $requestResponseCode,
                'resp_message' => $paymentResult ?: 'Payment failed'
            ]);

            $transaction->update(['status' => 'failed', 'failure_reason' => 'Payment failed via PayTabs']);
            $subscription->update(['status' => 'payment_failed']);

            return response()->json(['success' => false, 'message' => 'Payment failed']);
        }
    }

    /**
     * Handle PayTabs Return (Redirect)
     */
    public function handleReturn(Request $request)
    {
        Log::info('PayTabs Return Params DEBUG', [
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            'query' => $request->query(),
            'request_all' => $request->all(),
            'raw_content' => $request->getContent()
        ]);

        $tranRef = $request->input('tran_ref') ?? $request->input('tranRef');
        $cartId = $request->input('cart_id') ?? $request->input('cartId');
        $paymentId = $request->input('payment_id') ?? $request->input('paymentId');

        if (!$tranRef && !$cartId && !$paymentId) {
            return response()->json([
                'success' => false,
                'message' => 'No payment data received from PayTabs',
                'data' => $request->all()
            ], 400);
        }

        // Find Payment
        $payment = \App\Models\Payment::where(function ($query) use ($tranRef, $cartId, $paymentId) {
            if ($paymentId)
                $query->where('id', $paymentId);
            // Use proper grouping for OR conditions if needed, but here simple priority is fine
            elseif ($tranRef)
                $query->where('tran_ref', $tranRef);
            elseif ($cartId)
                $query->orWhere('cart_id', $cartId);
        })->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
                'data' => $request->all()
            ], 404);
        }

        // Fallback: Use saved tran_ref if missing in request
        if (!$tranRef && $payment->tran_ref) {
            $tranRef = $payment->tran_ref;
        }

        // Verify with PayTabs API
        $verificationResult = $this->verifyTransaction($tranRef);

        if ($verificationResult && isset($verificationResult['payment_result'])) {
            $responseStatus = $verificationResult['payment_result']['response_status'] ?? '';
            $paymentResult = $verificationResult['payment_result']['response_message'] ?? '';

            // Find Transaction
            $transaction = SubscriptionTransaction::where('payment_id', $payment->id)->first();
            $subscription = $transaction ? $transaction->subscription : null;

            if ($responseStatus === 'A') {
                // Success
                $payment->update([
                    'payment_status' => 'completed',
                    'tran_ref' => $tranRef,
                    'payment_result' => $paymentResult,
                    'completed_at' => now()
                ]);

                if ($transaction) {
                    $transaction->update(['status' => 'completed', 'processed_at' => now()]);
                }

                if ($subscription) {
                    $subscription->update([
                        'status' => 'active',
                        'next_billing_date' => $subscription->billing_cycle === 'yearly'
                            ? now()->addYear()
                            : now()->addMonth() // Roughly, or rely on existing dates
                    ]);

                    // Activate Provider
                    $subscription->provider->update(['is_active' => true]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment successful',
                    'data' => [
                        'payment_status' => 'completed',
                        'subscription_status' => 'active',
                        'tran_ref' => $tranRef
                    ]
                ]);

            } else {
                // Failed
                $payment->update([
                    'payment_status' => 'failed',
                    'tran_ref' => $tranRef,
                    'payment_result' => $paymentResult,
                    'failed_at' => now()
                ]);

                if ($transaction) {
                    $transaction->update(['status' => 'failed']);
                }

                if ($subscription) {
                    $subscription->update(['status' => 'payment_failed']);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Payment failed: ' . $paymentResult,
                    'data' => [
                        'payment_status' => 'failed',
                        'tran_ref' => $tranRef
                    ]
                ], 400);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment verification failed',
            'data' => $request->all()
        ], 400);
    }

    /**
     * Verify transaction with PayTabs API
     */
    private function verifyTransaction($tranRef)
    {
        if (!$tranRef)
            return null;

        $config = PayTabsConfigService::getConfig();
        $baseUrl = PayTabsConfigService::getBaseUrl();

        try {
            $response = Http::withHeaders([
                "Authorization" => $config['server_key'],
                "Content-Type" => "application/json"
            ])->post($baseUrl . 'payment/query', [
                        "profile_id" => $config['profile_id'],
                        "tran_ref" => $tranRef
                    ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error('PayTabs Verification Error: ' . $e->getMessage());
        }

        return null;
    }
}