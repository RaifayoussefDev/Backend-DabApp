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
 *     description="API endpoints pour gérer les réservations de services"
 * )
 */
class ServiceBookingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/bookings",
     *     summary="Mes réservations (User)",
     *     description="Récupère toutes les réservations de l'utilisateur connecté avec filtres",
     *     operationId="getMyBookings",
     *     tags={"Service Bookings"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filtrer par statut",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"pending", "confirmed", "in_progress", "completed", "cancelled", "rejected"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="service_id",
     *         in="query",
     *         description="Filtrer par service",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Date de début (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Date de fin (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="upcoming",
     *         in="query",
     *         description="Réservations à venir uniquement (1=oui)",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste récupérée avec succès",
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

        // Filtre: Période
        if ($request->has('from_date')) {
            $query->where('booking_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('booking_date', '<=', $request->to_date);
        }

        // Réservations à venir uniquement
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
     *     summary="Créer une réservation",
     *     description="Permet à l'utilisateur de créer une nouvelle réservation de service",
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
     *         description="Réservation créée avec succès",
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
     *     @OA\Response(response=400, description="Service non disponible ou données invalides")
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

            // Vérifier disponibilité
            if (!$service->is_available) {
                return response()->json([
                    'success' => false,
                    'message' => 'Service not available'
                ], 400);
            }

            // Vérifier que le provider est actif
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
            // TODO: Créer transaction de paiement

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
     *     summary="Détails d'une réservation",
     *     description="Récupère les détails complets d'une réservation",
     *     operationId="getBooking",
     *     tags={"Service Bookings"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la réservation",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Réservation trouvée"
     *     ),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=404, description="Réservation non trouvée")
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

        // Vérifier autorisation (user ou provider)
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
     *     summary="Annuler une réservation",
     *     description="Permet à l'utilisateur d'annuler sa réservation (minimum 24h avant)",
     *     operationId="cancelBooking",
     *     tags={"Service Bookings"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la réservation",
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
     *     @OA\Response(response=200, description="Réservation annulée"),
     *     @OA\Response(response=400, description="Annulation impossible")
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
     * Calcul de distance (Haversine formula)
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
     * Appliquer code promo
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

        // Vérifier catégorie
        if ($promo->service_category_id && $promo->service_category_id !== $service->category_id) {
            return ['final_price' => $price, 'discount_amount' => 0];
        }

        // Vérifier prix minimum
        if ($promo->min_booking_price && $price < $promo->min_booking_price) {
            return ['final_price' => $price, 'discount_amount' => 0];
        }

        // Calculer réduction
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