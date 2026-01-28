<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\TransportRoute;
use App\Models\TransportRouteStop;
use App\Models\ServiceBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="Transport Routes",
 *     description="API endpoints pour les routes de transport programmées de motos"
 * )
 */
class TransportRouteController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/transport-routes",
     *     summary="Routes de transport disponibles",
     *     description="Récupère toutes les routes de transport programmées avec places disponibles",
     *     operationId="getTransportRoutes",
     *     tags={"Transport Routes"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="departure_city",
     *         in="query",
     *         description="Ville de départ",
     *         required=false,
     *         @OA\Schema(type="string", example="Riyadh")
     *     ),
     *     @OA\Parameter(
     *         name="arrival_city",
     *         in="query",
     *         description="Ville d'arrivée",
     *         required=false,
     *         @OA\Schema(type="string", example="Jeddah")
     *     ),
     *     @OA\Parameter(
     *         name="route_date",
     *         in="query",
     *         description="Date de route (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-02-15")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Date de début",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-02-01")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Date de fin",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-02-28")
     *     ),
     *     @OA\Parameter(
     *         name="min_slots_available",
     *         in="query",
     *         description="Nombre minimum de places disponibles",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre par page",
     *         required=false,
     *         @OA\Schema(type="integer", example=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Routes récupérées avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="route_date", type="string", format="date", example="2025-02-15"),
     *                         @OA\Property(property="departure_point", type="string", example="Riyadh Central Station"),
     *                         @OA\Property(property="departure_point_ar", type="string", example="محطة الرياض المركزية"),
     *                         @OA\Property(property="arrival_point", type="string", example="Jeddah Port"),
     *                         @OA\Property(property="arrival_point_ar", type="string", example="ميناء جدة"),
     *                         @OA\Property(property="departure_time", type="string", format="time", example="08:00:00"),
     *                         @OA\Property(property="arrival_time", type="string", format="time", example="17:00:00"),
     *                         @OA\Property(property="available_slots", type="integer", example=10),
     *                         @OA\Property(property="booked_slots", type="integer", example=3),
     *                         @OA\Property(property="price_per_slot", type="number", format="float", example=250.00),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(
     *                             property="provider",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="business_name", type="string"),
     *                             @OA\Property(property="rating_average", type="number")
     *                         ),
     *                         @OA\Property(
     *                             property="stops",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="stop_name", type="string"),
     *                                 @OA\Property(property="arrival_time", type="string", format="time")
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = TransportRoute::with([
            'provider.city',
            'stops' => function($q) {
                $q->orderBy('stop_order');
            }
        ])
        ->where('is_active', true)
        ->where('route_date', '>=', now()->toDateString());

        // Filtre: Ville de départ
        if ($request->has('departure_city')) {
            $query->where('departure_point', 'LIKE', "%{$request->departure_city}%");
        }

        // Filtre: Ville d'arrivée
        if ($request->has('arrival_city')) {
            $query->where('arrival_point', 'LIKE', "%{$request->arrival_city}%");
        }

        // Filtre: Date spécifique
        if ($request->has('route_date')) {
            $query->where('route_date', $request->route_date);
        }

        // Filtre: Période
        if ($request->has('from_date')) {
            $query->where('route_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('route_date', '<=', $request->to_date);
        }

        // Filtre: Places disponibles minimum
        if ($request->has('min_slots_available')) {
            $query->whereRaw('(available_slots - booked_slots) >= ?', [$request->min_slots_available]);
        }

        // Ajouter le nombre de places restantes
        $query->selectRaw('*, (available_slots - booked_slots) as remaining_slots');

        $perPage = $request->get('per_page', 20);
        $routes = $query->orderBy('route_date')->orderBy('departure_time')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $routes,
            'message' => 'Transport routes retrieved successfully'
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/transport-routes/{id}",
     *     summary="Détails d'une route",
     *     description="Récupère les détails complets d'une route de transport avec tous ses arrêts",
     *     operationId="getTransportRoute",
     *     tags={"Transport Routes"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la route",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Route trouvée"
     *     ),
     *     @OA\Response(response=404, description="Route non trouvée")
     * )
     */
    public function show($id)
    {
        $route = TransportRoute::with([
            'provider.city',
            'provider.user:id,full_name,phone',
            'stops' => function($q) {
                $q->orderBy('stop_order');
            }
        ])
        ->selectRaw('*, (available_slots - booked_slots) as remaining_slots')
        ->find($id);

        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Transport route not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $route,
            'message' => 'Route found'
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/transport-routes/{id}/book",
     *     summary="Réserver une place sur une route",
     *     description="Permet de réserver une ou plusieurs places sur une route de transport programmée",
     *     operationId="bookTransportRoute",
     *     tags={"Transport Routes"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la route",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"slots_count"},
     *             @OA\Property(property="slots_count", type="integer", minimum=1, example=2, description="Nombre de places à réserver"),
     *             @OA\Property(property="pickup_stop_id", type="integer", example=1, description="ID du point de montée (optionnel, sinon départ)"),
     *             @OA\Property(property="dropoff_stop_id", type="integer", example=3, description="ID du point de descente (optionnel, sinon arrivée)"),
     *             @OA\Property(property="notes", type="string", example="2 motorcycles - Yamaha R1 and Kawasaki Ninja"),
     *             @OA\Property(property="promo_code", type="string", example="TRANSPORT10")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Réservation créée",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Places insuffisantes ou route non disponible")
     * )
     */
    public function book(Request $request, $id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $route = TransportRoute::find($id);

        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Transport route not found'
            ], 404);
        }

        $validated = $request->validate([
            'slots_count' => 'required|integer|min:1',
            'pickup_stop_id' => 'nullable|exists:transport_route_stops,id',
            'dropoff_stop_id' => 'nullable|exists:transport_route_stops,id',
            'notes' => 'nullable|string|max:1000',
            'promo_code' => 'nullable|string|exists:service_promo_codes,code'
        ]);

        DB::beginTransaction();
        try {
            // Vérifier disponibilité
            if (!$route->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'This route is not active'
                ], 400);
            }

            // Vérifier date
            if ($route->route_date < now()->toDateString()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This route has already departed'
                ], 400);
            }

            // Vérifier places disponibles
            $remainingSlots = $route->available_slots - $route->booked_slots;
            if ($validated['slots_count'] > $remainingSlots) {
                return response()->json([
                    'success' => false,
                    'message' => "Only {$remainingSlots} slots available",
                    'available_slots' => $remainingSlots
                ], 400);
            }

            // Calculer le prix
            $pricePerSlot = $route->price_per_slot;
            $totalPrice = $pricePerSlot * $validated['slots_count'];

            // Appliquer code promo
            $discountAmount = 0;
            if ($request->promo_code) {
                $promoResult = $this->applyPromoCode($totalPrice, $request->promo_code);
                $totalPrice = $promoResult['final_price'];
                $discountAmount = $promoResult['discount_amount'];
            }

            // Trouver le service de transport associé
            $transportService = \App\Models\Service::whereHas('category', function($q) {
                $q->where('slug', 'bike-transport');
            })
            ->where('provider_id', $route->provider_id)
            ->where('is_available', true)
            ->first();

            if (!$transportService) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transport service not available'
                ], 400);
            }

            // Déterminer pickup et dropoff
            $pickupLocation = $route->departure_point;
            $dropoffLocation = $route->arrival_point;

            if ($validated['pickup_stop_id']) {
                $pickupStop = TransportRouteStop::find($validated['pickup_stop_id']);
                $pickupLocation = $pickupStop->stop_name;
            }

            if ($validated['dropoff_stop_id']) {
                $dropoffStop = TransportRouteStop::find($validated['dropoff_stop_id']);
                $dropoffLocation = $dropoffStop->stop_name;
            }

            // Créer la réservation
            $booking = ServiceBooking::create([
                'service_id' => $transportService->id,
                'user_id' => $user->id,
                'provider_id' => $route->provider_id,
                'booking_date' => $route->route_date,
                'start_time' => $route->departure_time,
                'end_time' => $route->arrival_time,
                'status' => 'pending',
                'price' => $totalPrice,
                'payment_status' => 'pending',
                'pickup_location' => $pickupLocation,
                'dropoff_location' => $dropoffLocation,
                'notes' => ($validated['notes'] ?? '') . " | Slots: {$validated['slots_count']}"
            ]);

            // Mettre à jour la route
            $route->increment('booked_slots', $validated['slots_count']);
            $route->update(['booking_id' => $booking->id]);

            DB::commit();

            // TODO: Notifier le provider
            // TODO: Créer transaction de paiement

            return response()->json([
                'success' => true,
                'data' => [
                    'booking' => $booking->load(['service', 'provider']),
                    'route' => $route,
                    'slots_booked' => $validated['slots_count'],
                    'price_per_slot' => $pricePerSlot,
                    'total_price' => $totalPrice,
                    'discount_amount' => $discountAmount,
                    'payment_required' => true
                ],
                'message' => 'Transport route booked successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to book route',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/provider/transport-routes",
     *     summary="Créer une route (Provider)",
     *     description="Permet au fournisseur de créer une nouvelle route de transport programmée",
     *     operationId="createTransportRoute",
     *     tags={"Transport Routes"},
     *     security={{"bearer":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"route_date", "departure_point", "arrival_point", "departure_time", "arrival_time", "available_slots", "price_per_slot"},
     *             @OA\Property(property="route_date", type="string", format="date", example="2025-02-15"),
     *             @OA\Property(property="departure_point", type="string", example="Riyadh Central Station"),
     *             @OA\Property(property="departure_point_ar", type="string", example="محطة الرياض المركزية"),
     *             @OA\Property(property="arrival_point", type="string", example="Jeddah Port"),
     *             @OA\Property(property="arrival_point_ar", type="string", example="ميناء جدة"),
     *             @OA\Property(property="departure_time", type="string", format="time", example="08:00"),
     *             @OA\Property(property="arrival_time", type="string", format="time", example="17:00"),
     *             @OA\Property(property="available_slots", type="integer", example=10),
     *             @OA\Property(property="price_per_slot", type="number", format="float", example=250.00),
     *             @OA\Property(
     *                 property="stops",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="stop_name", type="string"),
     *                     @OA\Property(property="stop_name_ar", type="string"),
     *                     @OA\Property(property="arrival_time", type="string", format="time"),
     *                     @OA\Property(property="latitude", type="number"),
     *                     @OA\Property(property="longitude", type="number")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Route créée"),
     *     @OA\Response(response=403, description="Vous n'êtes pas fournisseur")
     * )
     */
    public function store(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user->serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a service provider'
            ], 403);
        }

        $validated = $request->validate([
            'route_date' => 'required|date|after:today',
            'departure_point' => 'required|string|max:500',
            'departure_point_ar' => 'nullable|string|max:500',
            'arrival_point' => 'required|string|max:500',
            'arrival_point_ar' => 'nullable|string|max:500',
            'departure_time' => 'required|date_format:H:i',
            'arrival_time' => 'required|date_format:H:i|after:departure_time',
            'available_slots' => 'required|integer|min:1|max:50',
            'price_per_slot' => 'required|numeric|min:0',
            'stops' => 'nullable|array',
            'stops.*.stop_name' => 'required|string|max:255',
            'stops.*.stop_name_ar' => 'nullable|string|max:255',
            'stops.*.arrival_time' => 'required|date_format:H:i',
            'stops.*.departure_time' => 'nullable|date_format:H:i',
            'stops.*.latitude' => 'nullable|numeric|between:-90,90',
            'stops.*.longitude' => 'nullable|numeric|between:-180,180'
        ]);

        DB::beginTransaction();
        try {
            $route = TransportRoute::create([
                'provider_id' => $user->serviceProvider->id,
                'route_date' => $validated['route_date'],
                'departure_point' => $validated['departure_point'],
                'departure_point_ar' => $validated['departure_point_ar'] ?? null,
                'arrival_point' => $validated['arrival_point'],
                'arrival_point_ar' => $validated['arrival_point_ar'] ?? null,
                'departure_time' => $validated['departure_time'],
                'arrival_time' => $validated['arrival_time'],
                'available_slots' => $validated['available_slots'],
                'booked_slots' => 0,
                'price_per_slot' => $validated['price_per_slot'],
                'is_active' => true
            ]);

            // Créer les arrêts
            if (!empty($validated['stops'])) {
                foreach ($validated['stops'] as $index => $stop) {
                    TransportRouteStop::create([
                        'route_id' => $route->id,
                        'stop_name' => $stop['stop_name'],
                        'stop_name_ar' => $stop['stop_name_ar'] ?? null,
                        'stop_order' => $index + 1,
                        'arrival_time' => $stop['arrival_time'],
                        'departure_time' => $stop['departure_time'] ?? null,
                        'latitude' => $stop['latitude'] ?? null,
                        'longitude' => $stop['longitude'] ?? null
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $route->load('stops'),
                'message' => 'Transport route created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create route',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/provider/transport-routes",
     *     summary="Mes routes (Provider)",
     *     description="Liste toutes les routes créées par le fournisseur",
     *     operationId="getMyRoutes",
     *     tags={"Transport Routes"},
     *     security={{"bearer":{}}},
     *     @OA\Response(response=200, description="Routes récupérées"),
     *     @OA\Response(response=403, description="Vous n'êtes pas fournisseur")
     * )
     */
    public function myRoutes()
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user->serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a service provider'
            ], 403);
        }

        $routes = TransportRoute::where('provider_id', $user->serviceProvider->id)
            ->with(['stops' => function($q) {
                $q->orderBy('stop_order');
            }])
            ->selectRaw('*, (available_slots - booked_slots) as remaining_slots')
            ->orderByDesc('route_date')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $routes,
            'message' => 'Routes retrieved successfully'
        ], 200);
    }

    /**
     * Appliquer code promo
     */
    private function applyPromoCode($price, $code)
    {
        $promo = \App\Models\ServicePromoCode::where('code', $code)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->first();

        if (!$promo) {
            return ['final_price' => $price, 'discount_amount' => 0];
        }

        if ($promo->min_booking_price && $price < $promo->min_booking_price) {
            return ['final_price' => $price, 'discount_amount' => 0];
        }

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