<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ServiceBooking;
use App\Models\Service;
use App\Models\ServicePromoCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="Service Bookings",
 *     description="API endpoints for managing service bookings"
 * )
 */
class ServiceBookingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/bookings",
     *     summary="My bookings (User)",
     *     description="Retrieve all bookings for the authenticated user with optional filters",
     *     operationId="getMyBookings",
     *     tags={"Service Bookings"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"pending", "confirmed", "in_progress", "completed", "cancelled", "rejected"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="service_id",
     *         in="query",
     *         description="Filter by service ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Start date (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="End date (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="upcoming",
     *         in="query",
     *         description="Upcoming bookings only (1=yes)",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bookings retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $query = ServiceBooking::with([
            'service.category',
            'provider.city',
            'payment'
        ])->where('user_id', $user->id);

        // Filtre: Statut
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filtre: Service
        if ($request->has('service_id')) {
            $query->where('service_id', $request->service_id);
        }

        // Filter: Date range
        if ($request->has('from_date')) {
            $query->where('booking_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('booking_date', '<=', $request->to_date);
        }

        // Upcoming bookings only
        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }

        $perPage = $request->get('per_page', 20);
        $bookings = $query->orderByDesc('booking_date')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $bookings,
            'message' => 'Bookings retrieved successfully'
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/bookings",
     *     summary="Create a booking",
     *     description="Allows the authenticated user to create a new service booking",
     *     operationId="createBooking",
     *     tags={"Service Bookings"},
     *     security={{"bearer":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"service_id", "booking_date", "start_time"},
     *             @OA\Property(property="service_id", type="integer", example=1),
     *             @OA\Property(property="booking_date", type="string", format="date", example="2025-02-15"),
     *             @OA\Property(property="start_time", type="string", format="time", example="09:00"),
     *             @OA\Property(property="end_time", type="string", format="time", example="10:00"),
     *             @OA\Property(property="pickup_location", type="string", example="123 King Fahd Road, Riyadh"),
     *             @OA\Property(property="pickup_latitude", type="number", format="float", example=24.7136),
     *             @OA\Property(property="pickup_longitude", type="number", format="float", example=46.6753),
     *             @OA\Property(property="dropoff_location", type="string", example="456 Prince Sultan Rd, Jeddah"),
     *             @OA\Property(property="dropoff_latitude", type="number", format="float", example=21.4858),
     *             @OA\Property(property="dropoff_longitude", type="number", format="float", example=39.1925),
     *             @OA\Property(property="notes", type="string", example="Please handle with care"),
     *             @OA\Property(property="promo_code", type="string", example="SUMMER2025")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Booking created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="booking", type="object"),
     *                 @OA\Property(property="payment_required", type="boolean", example=true),
     *                 @OA\Property(property="amount", type="number", format="float", example=250.00)
     *             ),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Service unavailable or invalid data")
     * )
     */
    public function store(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'booking_date' => 'required|date|after:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'pickup_location' => 'nullable|string|max:500',
            'pickup_latitude' => 'nullable|numeric|between:-90,90',
            'pickup_longitude' => 'nullable|numeric|between:-180,180',
            'dropoff_location' => 'nullable|string|max:500',
            'dropoff_latitude' => 'nullable|numeric|between:-90,90',
            'dropoff_longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string|max:1000',
            'promo_code' => 'nullable|string|exists:service_promo_codes,code'
        ]);

        DB::beginTransaction();
        try {
            $service = Service::with('provider')->findOrFail($validated['service_id']);

            // Check availability
            if (!$service->is_available) {
                return response()->json([
                    'success' => false,
                    'message' => 'Service not available'
                ], 400);
            }

            // Check provider is active
            if (!$service->provider->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provider is not active'
                ], 400);
            }

            // Calculer le prix
            $price = $service->price;
            $discountAmount = 0;

            // Calculer distance pour transport/tow
            if ($validated['pickup_latitude'] && $validated['dropoff_latitude']) {
                $distance = $this->calculateDistance(
                    $validated['pickup_latitude'],
                    $validated['pickup_longitude'],
                    $validated['dropoff_latitude'],
                    $validated['dropoff_longitude']
                );
                $validated['distance_km'] = $distance;

                // Ajuster prix selon distance si price_type = per_km
                if ($service->price_type === 'per_km') {
                    $price = $service->price * $distance;
                }
            }

            // Appliquer promo code si fourni
            if ($request->promo_code) {
                $promoResult = $this->applyPromoCode($price, $request->promo_code, $service);
                $price = $promoResult['final_price'];
                $discountAmount = $promoResult['discount_amount'];
            }

            $booking = ServiceBooking::create([
                ...$validated,
                'user_id' => $user->id,
                'provider_id' => $service->provider_id,
                'price' => $price,
                'status' => 'pending',
                'payment_status' => 'pending'
            ]);

            DB::commit();

            // TODO: Envoyer notification au provider
            // TODO: Create payment transaction

            return response()->json([
                'success' => true,
                'data' => [
                    'booking' => $booking->load(['service', 'provider']),
                    'payment_required' => true,
                    'amount' => $price,
                    'original_price' => $service->price,
                    'discount_amount' => $discountAmount,
                    'distance_km' => $validated['distance_km'] ?? null
                ],
                'message' => 'Booking created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/bookings/{id}",
     *     summary="Booking details (User)",
     *     description="Retrieve full details of a specific booking",
     *     operationId="getBooking",
     *     tags={"Service Bookings"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Booking ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Booking found"
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Booking not found")
     * )
     */
    public function show($id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $booking = ServiceBooking::with([
            'service.category',
            'provider.user',
            'provider.city',
            'payment',
            'review',
            'chatSession'
        ])->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        }

        // Check authorization (user or provider)
        if ($booking->user_id !== $user->id &&
            $booking->provider->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this booking'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $booking,
            'message' => 'Booking found'
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/bookings/{id}/cancel",
     *     summary="Cancel a booking (User)",
     *     description="Allows the user to cancel their booking (at least 24h before the booking date)",
     *     operationId="cancelBooking",
     *     tags={"Service Bookings"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Booking ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"cancellation_reason"},
     *             @OA\Property(property="cancellation_reason", type="string", example="Changed my mind")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Booking cancelled successfully"),
     *     @OA\Response(response=400, description="Cancellation not allowed")
     * )
     */
    public function cancel(Request $request, $id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $booking = ServiceBooking::find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        }

        if ($booking->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to cancel this booking'
            ], 403);
        }

        if (!$booking->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'Booking cannot be cancelled (must be at least 24 hours before booking date)'
            ], 400);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:500'
        ]);

        DB::beginTransaction();
        try {
            $booking->update([
                'status' => 'cancelled',
                'cancellation_reason' => $validated['cancellation_reason'],
                'cancelled_by' => $user->id,
                'cancelled_at' => now()
            ]);

            // TODO: Process refund si payment completed
            if ($booking->payment_status === 'completed') {
                // Refund logic here
            }

            DB::commit();

            // TODO: Notifier le provider

            return response()->json([
                'success' => true,
                'data' => $booking->fresh(),
                'message' => 'Booking cancelled successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/provider/bookings",
     *     summary="Received bookings list (Provider)",
     *     description="Retrieve all bookings received by the authenticated provider",
     *     operationId="getProviderBookings",
     *     tags={"Service Bookings"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(name="status", in="query", description="pending|confirmed|in_progress|completed|cancelled|rejected", @OA\Schema(type="string")),
     *     @OA\Parameter(name="service_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Bookings retrieved"),
     *     @OA\Response(response=403, description="Not a provider")
     * )
     */
    public function providerBookings(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user->serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a service provider'
            ], 403);
        }

        $query = ServiceBooking::with([
            'service.category',
            'user:id,first_name,last_name,email,phone,avatar',
            'payment'
        ])->where('provider_id', $user->serviceProvider->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('service_id')) {
            $query->where('service_id', $request->service_id);
        }

        if ($request->has('from_date')) {
            $query->where('booking_date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('booking_date', '<=', $request->to_date);
        }

        $perPage = $request->get('per_page', 20);
        $bookings = $query->orderByDesc('booking_date')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $bookings,
            'message' => 'Provider bookings retrieved successfully'
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/provider/bookings/{id}",
     *     summary="Booking details (Provider)",
     *     operationId="getProviderBooking",
     *     tags={"Service Bookings"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Booking found"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function providerBookingShow($id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user->serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a service provider'
            ], 403);
        }

        $booking = ServiceBooking::with([
            'service.category',
            'user:id,first_name,last_name,email,phone,avatar',
            'payment',
            'review',
            'chatSession'
        ])->where('provider_id', $user->serviceProvider->id)->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $booking,
            'message' => 'Booking found'
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/provider/bookings/{id}/update-status",
     *     summary="Update booking status (Provider)",
     *     description="The provider can confirm, reject, start or complete a booking",
     *     operationId="updateBookingStatus",
     *     tags={"Service Bookings"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"confirmed","rejected","in_progress","completed"}, example="confirmed"),
     *             @OA\Property(property="rejection_reason", type="string", example="Not available on this date")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Status updated"),
     *     @OA\Response(response=400, description="Invalid status transition"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function updateBookingStatus(Request $request, $id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user->serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a service provider'
            ], 403);
        }

        $booking = ServiceBooking::where('provider_id', $user->serviceProvider->id)->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        }

        $validated = $request->validate([
            'status'           => 'required|in:confirmed,rejected,in_progress,completed',
            'rejection_reason' => 'required_if:status,rejected|nullable|string|max:500',
        ]);

        // Allowed transitions
        $allowedTransitions = [
            'pending'     => ['confirmed', 'rejected'],
            'confirmed'   => ['in_progress', 'rejected'],
            'in_progress' => ['completed'],
        ];

        $currentStatus = $booking->status;

        if (!isset($allowedTransitions[$currentStatus]) ||
            !in_array($validated['status'], $allowedTransitions[$currentStatus])) {
            return response()->json([
                'success' => false,
                'message' => "Cannot change status from '{$currentStatus}' to '{$validated['status']}'"
            ], 400);
        }

        $updateData = ['status' => $validated['status']];

        if ($validated['status'] === 'rejected') {
            $updateData['cancellation_reason'] = $validated['rejection_reason'] ?? null;
            $updateData['cancelled_by'] = $user->id;
            $updateData['cancelled_at'] = now();
        }

        if ($validated['status'] === 'completed') {
            $updateData['payment_status'] = 'completed';
            $user->serviceProvider->increment('completed_orders');
        }

        $booking->update($updateData);

        return response()->json([
            'success' => true,
            'data'    => $booking->fresh(['service', 'user', 'payment']),
            'message' => "Booking status updated to '{$validated['status']}' successfully"
        ], 200);
    }

    /**
     * Distance calculation (Haversine formula)
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earthRadius * $c;

        return round($distance, 2);
    }

    /**
     * Apply promo code
     */
    private function applyPromoCode($price, $code, $service)
    {
        $promo = ServicePromoCode::where('code', $code)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->first();

        if (!$promo) {
            return ['final_price' => $price, 'discount_amount' => 0];
        }

        // Check category
        if ($promo->service_category_id && $promo->service_category_id !== $service->category_id) {
            return ['final_price' => $price, 'discount_amount' => 0];
        }

        // Check minimum price
        if ($promo->min_booking_price && $price < $promo->min_booking_price) {
            return ['final_price' => $price, 'discount_amount' => 0];
        }

        // Calculate discount
        $discountAmount = 0;
        if ($promo->discount_type === 'percentage') {
            $discountAmount = ($price * $promo->discount_value) / 100;
            if ($promo->max_discount && $discountAmount > $promo->max_discount) {
                $discountAmount = $promo->max_discount;
            }
        } else {
            $discountAmount = $promo->discount_value;
        }

        $finalPrice = max(0, $price - $discountAmount);

        return [
            'final_price' => round($finalPrice, 2),
            'discount_amount' => round($discountAmount, 2)
        ];
    }
}