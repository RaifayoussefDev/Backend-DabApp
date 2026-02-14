<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\TowType;
use App\Models\Service;
use App\Models\ServiceBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Tow Service",
 *     description="API endpoints pour les services de remorquage de motos"
 * )
 */
class TowServiceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/tow-types",
     *     summary="Types de remorquage",
     *     description="Récupère tous les types de remorquage disponibles avec tarifs",
     *     operationId="getTowTypes",
     *     tags={"Tow Service"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filtrer par statut actif",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Types récupérés avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Flatbed Tow"),
     *                     @OA\Property(property="name_ar", type="string", example="سحب بسطح مستوي"),
     *                     @OA\Property(property="description", type="string", example="Most secure method for transporting motorcycles"),
     *                     @OA\Property(property="description_ar", type="string", example="الطريقة الأكثر أماناً لنقل الدراجات النارية"),
     *                     @OA\Property(property="icon", type="string", example="flatbed-truck"),
     *                     @OA\Property(property="image", type="string", example="https://example.com/flatbed.jpg"),
     *                     @OA\Property(property="base_price", type="number", format="float", example=100.00),
     *                     @OA\Property(property="price_per_km", type="number", format="float", example=5.00),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="order_position", type="integer", example=1)
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Tow types retrieved successfully")
     *         )
     *     )
     * )
     */
    public function types(Request $request)
    {
        $query = TowType::query();

        // Filtre par statut actif
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $towTypes = $query->orderBy('order_position')->get();

        return response()->json([
            'success' => true,
            'data' => $towTypes,
            'message' => 'Tow types retrieved successfully'
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/tow-types/{id}",
     *     summary="Détails d'un type de remorquage",
     *     description="Récupère les détails d'un type de remorquage spécifique",
     *     operationId="getTowType",
     *     tags={"Tow Service"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du type de remorquage",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Type trouvé",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Type non trouvé")
     * )
     */
    public function show($id)
    {
        $towType = TowType::find($id);

        if (!$towType) {
            return response()->json([
                'success' => false,
                'message' => 'Tow type not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $towType,
            'message' => 'Tow type found'
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/tow-service/calculate-price",
     *     summary="Calculer le prix de remorquage",
     *     description="Calcule le prix estimé d'un service de remorquage selon la distance et le type",
     *     operationId="calculateTowPrice",
     *     tags={"Tow Service"},
     *     security={{"bearer":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"pickup_latitude", "pickup_longitude", "dropoff_latitude", "dropoff_longitude"},
     *             @OA\Property(property="tow_type_id", type="integer", example=1, description="ID du type (Required if service_id is missing)"),
     *             @OA\Property(property="service_id", type="integer", example=10, description="Override with Provider Service ID (Optional)"),
     *             @OA\Property(property="pickup_latitude", type="number", format="float", example=24.7136, description="Latitude du point de départ"),
     *             @OA\Property(property="pickup_longitude", type="number", format="float", example=46.6753, description="Longitude du point de départ"),
     *             @OA\Property(property="dropoff_latitude", type="number", format="float", example=21.4858, description="Latitude de destination"),
     *             @OA\Property(property="dropoff_longitude", type="number", format="float", example=39.1925, description="Longitude de destination"),
     *             @OA\Property(property="promo_code", type="string", example="SUMMER2025", description="Code promo optionnel")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Prix calculé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="tow_type", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Flatbed Tow"),
     *                     @OA\Property(property="name_ar", type="string", example="سحب بسطح مستوي")
     *                 ),
     *                 @OA\Property(property="distance_km", type="number", format="float", example=857.23),
     *                 @OA\Property(property="base_price", type="number", format="float", example=100.00),
     *                 @OA\Property(property="distance_price", type="number", format="float", example=4286.15),
     *                 @OA\Property(property="subtotal", type="number", format="float", example=4386.15),
     *                 @OA\Property(property="discount_amount", type="number", format="float", example=0.00),
     *                 @OA\Property(property="total_price", type="number", format="float", example=4386.15),
     *                 @OA\Property(property="currency", type="string", example="SAR"),
     *                 @OA\Property(property="estimated_duration_minutes", type="integer", example=514)
     *             ),
     *             @OA\Property(property="message", type="string", example="Price calculated successfully")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Paramètres invalides"),
     *     @OA\Response(response=404, description="Type de remorquage non trouvé")
     * )
     */
    public function calculatePrice(Request $request)
    {
        $validated = $request->validate([
            'tow_type_id' => 'required_without:service_id|exists:tow_types,id',
            'service_id' => 'nullable|exists:services,id',
            'pickup_latitude' => 'required|numeric|between:-90,90',
            'pickup_longitude' => 'required|numeric|between:-180,180',
            'dropoff_latitude' => 'required|numeric|between:-90,90',
            'dropoff_longitude' => 'required|numeric|between:-180,180',
            'pickup_city_id' => 'nullable|exists:cities,id',
            'dropoff_city_id' => 'nullable|exists:cities,id',
            'promo_code' => 'nullable|string|exists:service_promo_codes,code'
        ]);

        try {
            $basePrice = 0;
            $pricePerKm = 0;
            $fixedPrice = null; // New logic for fixed pricing
            $towTypeData = null;
            $serviceData = null;

            // Logique de tarification
            if ($request->has('service_id')) {
                $service = Service::with(['category', 'pricingRules'])->findOrFail($request->service_id);

                // Priority 1: Check for Fixed Route Rule
                if ($request->pickup_city_id && $request->dropoff_city_id) {
                    $rule = $service->pricingRules->where('type', 'fixed_route')
                        ->where('origin_city_id', $request->pickup_city_id)
                        ->where('destination_city_id', $request->dropoff_city_id)
                        ->first();

                    if ($rule) {
                        $fixedPrice = $rule->price;
                        $basePrice = 0; // Flat fee implies included base
                        $pricePerKm = 0;
                    }
                }

                if ($fixedPrice === null) {
                    // Fallback to standard service pricing
                    $basePrice = $service->base_price ?? 0;
                    if ($service->price_type === 'per_km') {
                        $pricePerKm = $service->price;
                    }
                }

                $serviceData = [
                    'id' => $service->id,
                    'name' => $service->name,
                    'is_specific' => true
                ];

            } elseif ($request->has('tow_type_id')) {
                $towType = TowType::findOrFail($request->tow_type_id);

                if (!$towType->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This tow type is not currently available'
                    ], 400);
                }

                $basePrice = $towType->base_price;
                $pricePerKm = $towType->price_per_km;

                $towTypeData = [
                    'id' => $towType->id,
                    'name' => $towType->name,
                    'name_ar' => $towType->name_ar,
                    'icon' => $towType->icon
                ];
            }

            // Calculer la distance
            $distance = $this->calculateDistance(
                $validated['pickup_latitude'],
                $validated['pickup_longitude'],
                $validated['dropoff_latitude'],
                $validated['dropoff_longitude']
            );

            // Prix final
            $subtotal = 0;
            $distancePrice = 0;

            if ($fixedPrice !== null) {
                // Fixed Price override
                $subtotal = $fixedPrice;
                $distancePrice = 0; // Included
            } else {
                // Standard Formula: Base + (Km * Prix/Km)
                $distancePrice = $distance * $pricePerKm;
                $subtotal = $basePrice + $distancePrice;
            }

            // Appliquer code promo si fourni
            $discountAmount = 0;
            if ($request->promo_code) {
                $promoResult = $this->applyPromoCode($subtotal, $request->promo_code);
                $discountAmount = $promoResult['discount_amount'];
            }

            $totalPrice = $subtotal - $discountAmount;

            // Estimer la durée (50 km/h en moyenne)
            $estimatedDurationMinutes = ceil(($distance / 50) * 60);

            return response()->json([
                'success' => true,
                'data' => [
                    'tow_type' => $towTypeData, // Peut être null si service_id utilisé
                    'service' => $serviceData,  // Nouveau champ
                    'pricing_model' => $fixedPrice !== null ? 'fixed_route' : ($request->has('service_id') ? 'provider_specific' : 'standard'),
                    'distance_km' => round($distance, 2),
                    'base_price' => round($basePrice, 2),
                    'price_per_km' => round($pricePerKm, 2),
                    'distance_price' => round($distancePrice, 2),
                    'subtotal' => round($subtotal, 2),
                    'discount_amount' => round($discountAmount, 2),
                    'total_price' => round($totalPrice, 2),
                    'currency' => 'SAR',
                    'estimated_duration_minutes' => $estimatedDurationMinutes
                ],
                'message' => 'Price calculated successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate price',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/tow-service/request",
     *     summary="Demander un service de remorquage",
     *     description="Crée une demande de remorquage immédiat",
     *     operationId="requestTowService",
     *     tags={"Tow Service"},
     *     security={{"bearer":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"tow_type_id", "pickup_location", "pickup_latitude", "pickup_longitude", "dropoff_location", "dropoff_latitude", "dropoff_longitude"},
     *             @OA\Property(property="tow_type_id", type="integer", example=1),
     *             @OA\Property(property="pickup_location", type="string", example="King Fahd Road, Riyadh"),
     *             @OA\Property(property="pickup_latitude", type="number", format="float", example=24.7136),
     *             @OA\Property(property="pickup_longitude", type="number", format="float", example=46.6753),
     *             @OA\Property(property="dropoff_location", type="string", example="Jeddah Corniche"),
     *             @OA\Property(property="dropoff_latitude", type="number", format="float", example=21.4858),
     *             @OA\Property(property="dropoff_longitude", type="number", format="float", example=39.1925),
     *             @OA\Property(property="notes", type="string", example="Motorcycle won't start, battery dead"),
     *             @OA\Property(property="phone", type="string", example="+966501234567"),
     *             @OA\Property(property="promo_code", type="string", example="SUMMER2025")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Demande créée avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function request(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validated = $request->validate([
            'tow_type_id' => 'required_without:service_id|exists:tow_types,id',
            'service_id' => 'nullable|exists:services,id',
            'pickup_location' => 'required|string|max:500',
            'pickup_latitude' => 'required|numeric|between:-90,90',
            'pickup_longitude' => 'required|numeric|between:-180,180',
            'dropoff_location' => 'required|string|max:500',
            'dropoff_latitude' => 'required|numeric|between:-90,90',
            'dropoff_longitude' => 'required|numeric|between:-180,180',
            'pickup_city_id' => 'nullable|exists:cities,id',
            'dropoff_city_id' => 'nullable|exists:cities,id',
            'notes' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:20',
            'promo_code' => 'nullable|string|exists:service_promo_codes,code'
        ]);

        DB::beginTransaction();
        try {
            // Calculer distance
            $distance = $this->calculateDistance(
                $validated['pickup_latitude'],
                $validated['pickup_longitude'],
                $validated['dropoff_latitude'],
                $validated['dropoff_longitude']
            );

            $towService = null;
            $totalPrice = 0;
            $towType = null; // Can be null if using provider service directly

            if ($request->has('service_id')) {
                // Use specific provider service
                $towService = Service::with(['category', 'pricingRules'])->findOrFail($request->service_id);

                // Calculate Price based on Service Rules
                $basePrice = $towService->base_price ?? 0;
                $pricePerKm = ($towService->price_type === 'per_km') ? $towService->price : 0;
                $fixedPrice = null;

                if ($request->pickup_city_id && $request->dropoff_city_id) {
                    $rule = $towService->pricingRules->where('type', 'fixed_route')
                        ->where('origin_city_id', $request->pickup_city_id)
                        ->where('destination_city_id', $request->dropoff_city_id)
                        ->first();
                    if ($rule) {
                        $fixedPrice = $rule->price;
                    }
                }

                if ($fixedPrice !== null) {
                    $totalPrice = $fixedPrice;
                } else {
                    $totalPrice = $basePrice + ($distance * $pricePerKm);
                }

            } else {
                // Fallback to Tow Type logic and find random provider
                $towType = TowType::findOrFail($validated['tow_type_id']);
                $basePrice = $towType->base_price;
                $distancePrice = $distance * $towType->price_per_km;
                $totalPrice = $basePrice + $distancePrice;

                // Trouver un service de remorquage disponible (catégorie "tow-service")
                $towService = Service::whereHas('category', function ($q) {
                    $q->where('slug', 'tow-service');
                })
                    ->where('is_available', true)
                    ->first();
            }

            // Appliquer promo code (moved down)
            if ($request->promo_code) {
                $promoResult = $this->applyPromoCode($totalPrice, $request->promo_code);
                $totalPrice = $promoResult['final_price'];
            }

            if (!$towService) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tow service available at the moment'
                ], 400);
            }

            // Créer la réservation
            $booking = ServiceBooking::create([
                'service_id' => $towService->id,
                'user_id' => $user->id,
                'provider_id' => $towService->provider_id,
                'booking_date' => now()->toDateString(),
                'start_time' => now()->format('H:i'),
                'status' => 'pending',
                'price' => $totalPrice,
                'payment_status' => 'pending',
                'pickup_location' => $validated['pickup_location'],
                'pickup_latitude' => $validated['pickup_latitude'],
                'pickup_longitude' => $validated['pickup_longitude'],
                'dropoff_location' => $validated['dropoff_location'],
                'dropoff_latitude' => $validated['dropoff_latitude'],
                'dropoff_longitude' => $validated['dropoff_longitude'],
                'distance_km' => $distance,
                'notes' => $validated['notes'] ?? null
            ]);

            DB::commit();

            // TODO: Notifier les providers de remorquage disponibles
            // TODO: Créer transaction de paiement

            return response()->json([
                'success' => true,
                'data' => [
                    'booking' => $booking->load(['service', 'provider']),
                    'tow_type' => $towType,
                    'distance_km' => round($distance, 2),
                    'total_price' => round($totalPrice, 2),
                    'payment_required' => true
                ],
                'message' => 'Tow service request created successfully. A provider will be assigned shortly.'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tow request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/tow-service/available-providers",
     *     summary="Providers de remorquage disponibles",
     *     description="Trouve les providers de remorquage à proximité d'une position",
     *     operationId="getAvailableTowProviders",
     *     tags={"Tow Service"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="latitude",
     *         in="query",
     *         description="Latitude de la position",
     *         required=true,
     *         @OA\Schema(type="number", format="float", example=24.7136)
     *     ),
     *     @OA\Parameter(
     *         name="longitude",
     *         in="query",
     *         description="Longitude de la position",
     *         required=true,
     *         @OA\Schema(type="number", format="float", example=46.6753)
     *     ),
     *     @OA\Parameter(
     *         name="radius",
     *         in="query",
     *         description="Rayon de recherche en km",
     *         required=false,
     *         @OA\Schema(type="integer", example=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Providers trouvés",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="count", type="integer")
     *         )
     *     )
     * )
     */
    public function availableProviders(Request $request)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|integer|min:1|max:100'
        ]);

        $radius = $validated['radius'] ?? 20;

        // Trouver la catégorie "tow-service"
        $towCategory = \App\Models\ServiceCategory::where('slug', 'tow-service')->first();

        if (!$towCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Tow service category not found'
            ], 404);
        }

        // Trouver les providers à proximité qui offrent du remorquage
        // Utilisation de raw SQL pour la distance au lieu du scope nearby() pour contrôler le tri
        $providers = \App\Models\ServiceProvider::select('service_providers.*')
            ->selectRaw(
                "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                [$validated['latitude'], $validated['longitude'], $validated['latitude']]
            )
            ->leftJoin('service_subscriptions', function ($join) {
                $join->on('service_providers.id', '=', 'service_subscriptions.provider_id')
                    ->where('service_subscriptions.status', 'active')
                    ->whereDate('service_subscriptions.current_period_end', '>=', now());
            })
            ->leftJoin('subscription_plans', 'service_subscriptions.plan_id', '=', 'subscription_plans.id')
            ->addSelect(DB::raw('COALESCE(subscription_plans.priority, 0) as plan_priority'))
            ->having('distance', '<', $radius)
            ->active()
            ->verified()
            ->whereHas('services', function ($q) use ($towCategory) {
                $q->where('category_id', $towCategory->id)
                    ->where('is_available', true);
            })
            ->with([
                'services' => function ($q) use ($towCategory) {
                    $q->where('category_id', $towCategory->id);
                },
                'city',
                'activeSubscription.plan' // Eager load plan for UI display
            ])
            ->orderByDesc('plan_priority') // Priorité plan
            ->orderBy('distance')      // Puis distance
            ->get();

        return response()->json([
            'success' => true,
            'data' => $providers,
            'count' => $providers->count(),
            'search_radius_km' => $radius,
            'message' => 'Available tow providers found'
        ], 200);
    }

    /**
     * Calculer la distance (Haversine formula)
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        return round($distance, 2);
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