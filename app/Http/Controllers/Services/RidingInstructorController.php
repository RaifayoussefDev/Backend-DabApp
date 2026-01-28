<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\RidingInstructor;
use App\Models\InstructorLocation;
use App\Models\ServiceBooking;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Riding Instructors",
 *     description="API endpoints pour les instructeurs de conduite moto"
 * )
 */
class RidingInstructorController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/riding-instructors",
     *     summary="Liste des instructeurs",
     *     description="Récupère tous les instructeurs de conduite disponibles avec filtres",
     *     operationId="getRidingInstructors",
     *     tags={"Riding Instructors"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="city_id",
     *         in="query",
     *         description="Filtrer par ville",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="min_experience_years",
     *         in="query",
     *         description="Années d'expérience minimum",
     *         required=false,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Parameter(
     *         name="min_rating",
     *         in="query",
     *         description="Note minimale (1-5)",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=4.0)
     *     ),
     *     @OA\Parameter(
     *         name="is_available",
     *         in="query",
     *         description="Disponibilité",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1})
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Rechercher par nom",
     *         required=false,
     *         @OA\Schema(type="string", example="Ahmed")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Trier par (rating, experience, sessions)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"rating", "experience", "sessions"})
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
     *         description="Instructeurs récupérés avec succès",
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
     *                         @OA\Property(property="instructor_name", type="string", example="Ahmed Al-Rashid"),
     *                         @OA\Property(property="instructor_name_ar", type="string", example="أحمد الراشد"),
     *                         @OA\Property(property="bio", type="string", example="Professional motorcycle instructor with 10+ years experience"),
     *                         @OA\Property(property="photo", type="string", example="https://example.com/ahmed.jpg"),
     *                         @OA\Property(property="experience_years", type="integer", example=10),
     *                         @OA\Property(property="rating_average", type="number", format="float", example=4.8),
     *                         @OA\Property(property="total_sessions", type="integer", example=256),
     *                         @OA\Property(property="is_available", type="boolean", example=true),
     *                         @OA\Property(
     *                             property="certifications",
     *                             type="array",
     *                             @OA\Items(type="string", example="Advanced Riding Techniques")
     *                         ),
     *                         @OA\Property(
     *                             property="provider",
     *                             type="object",
     *                             @OA\Property(property="business_name", type="string"),
     *                             @OA\Property(property="city", type="object")
     *                         ),
     *                         @OA\Property(
     *                             property="locations",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="location_name", type="string"),
     *                                 @OA\Property(property="city", type="object")
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
        $query = RidingInstructor::with([
            'provider.city',
            'locations.city'
        ])
        ->where('is_available', true);

        // Filtre: Ville (via provider ou locations)
        if ($request->has('city_id')) {
            $query->where(function($q) use ($request) {
                $q->whereHas('provider', function($pq) use ($request) {
                    $pq->where('city_id', $request->city_id);
                })
                ->orWhereHas('locations', function($lq) use ($request) {
                    $lq->where('city_id', $request->city_id);
                });
            });
        }

        // Filtre: Expérience minimum
        if ($request->has('min_experience_years')) {
            $query->where('experience_years', '>=', $request->min_experience_years);
        }

        // Filtre: Note minimale
        if ($request->has('min_rating')) {
            $query->where('rating_average', '>=', $request->min_rating);
        }

        // Filtre: Disponibilité
        if ($request->has('is_available')) {
            $query->where('is_available', $request->is_available);
        }

        // Recherche par nom
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('instructor_name', 'LIKE', "%{$search}%")
                  ->orWhere('instructor_name_ar', 'LIKE', "%{$search}%");
            });
        }

        // Tri
        $sortBy = $request->get('sort_by', 'rating');
        switch ($sortBy) {
            case 'experience':
                $query->orderByDesc('experience_years');
                break;
            case 'sessions':
                $query->orderByDesc('total_sessions');
                break;
            default: // rating
                $query->orderByDesc('rating_average');
                break;
        }

        $perPage = $request->get('per_page', 20);
        $instructors = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $instructors,
            'message' => 'Riding instructors retrieved successfully'
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/riding-instructors/{id}",
     *     summary="Détails d'un instructeur",
     *     description="Récupère les détails complets d'un instructeur avec avis et disponibilités",
     *     operationId="getRidingInstructor",
     *     tags={"Riding Instructors"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'instructeur",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Instructeur trouvé",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Instructeur non trouvé")
     * )
     */
    public function show($id)
    {
        $instructor = RidingInstructor::with([
            'provider.city',
            'provider.user:id,full_name,phone',
            'locations.city'
        ])->find($id);

        if (!$instructor) {
            return response()->json([
                'success' => false,
                'message' => 'Riding instructor not found'
            ], 404);
        }

        // Récupérer quelques avis récents
        $recentReviews = \App\Models\ServiceReview::whereHas('booking', function($q) use ($instructor) {
            $q->whereHas('service', function($sq) use ($instructor) {
                $sq->where('provider_id', $instructor->provider_id)
                   ->whereHas('category', function($cq) {
                       $cq->where('slug', 'riding-instructor');
                   });
            });
        })
        ->where('is_approved', true)
        ->with('user:id,full_name,avatar')
        ->latest()
        ->limit(5)
        ->get();

        $instructorData = $instructor->toArray();
        $instructorData['recent_reviews'] = $recentReviews;

        return response()->json([
            'success' => true,
            'data' => $instructorData,
            'message' => 'Instructor found'
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/instructor-locations",
     *     summary="Lieux de cours disponibles",
     *     description="Récupère tous les lieux où les cours de conduite sont dispensés",
     *     operationId="getInstructorLocations",
     *     tags={"Riding Instructors"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="city_id",
     *         in="query",
     *         description="Filtrer par ville",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="instructor_id",
     *         in="query",
     *         description="Filtrer par instructeur",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lieux récupérés",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="location_name", type="string"),
     *                     @OA\Property(property="location_name_ar", type="string"),
     *                     @OA\Property(property="is_available", type="boolean"),
     *                     @OA\Property(
     *                         property="city",
     *                         type="object",
     *                         @OA\Property(property="name", type="string")
     *                     ),
     *                     @OA\Property(
     *                         property="instructor",
     *                         type="object",
     *                         @OA\Property(property="instructor_name", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function locations(Request $request)
    {
        $query = InstructorLocation::with(['city', 'instructor'])
            ->where('is_available', true);

        // Filtre: Ville
        if ($request->has('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        // Filtre: Instructeur
        if ($request->has('instructor_id')) {
            $query->where('instructor_id', $request->instructor_id);
        }

        $locations = $query->get();

        return response()->json([
            'success' => true,
            'data' => $locations,
            'count' => $locations->count(),
            'message' => 'Instructor locations retrieved successfully'
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/riding-instructors/{id}/book-session",
     *     summary="Réserver une session",
     *     description="Réserve une session de cours avec un instructeur",
     *     operationId="bookInstructorSession",
     *     tags={"Riding Instructors"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'instructeur",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"booking_date", "start_time", "location_id"},
     *             @OA\Property(property="booking_date", type="string", format="date", example="2025-02-15"),
     *             @OA\Property(property="start_time", type="string", format="time", example="09:00"),
     *             @OA\Property(property="duration_hours", type="integer", example=2, description="Durée en heures (1-4)"),
     *             @OA\Property(property="location_id", type="integer", example=1, description="ID du lieu de cours"),
     *             @OA\Property(property="session_type", type="string", enum={"beginner", "intermediate", "advanced", "custom"}, example="beginner"),
     *             @OA\Property(property="notes", type="string", example="First time riding, need basic training"),
     *             @OA\Property(property="promo_code", type="string", example="FIRST10")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Session réservée",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Instructeur non disponible ou créneau occupé")
     * )
     */
    public function bookSession(Request $request, $id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $instructor = RidingInstructor::with('provider')->find($id);

        if (!$instructor) {
            return response()->json([
                'success' => false,
                'message' => 'Riding instructor not found'
            ], 404);
        }

        $validated = $request->validate([
            'booking_date' => 'required|date|after:today',
            'start_time' => 'required|date_format:H:i',
            'duration_hours' => 'nullable|integer|min:1|max:4',
            'location_id' => 'required|exists:instructor_locations,id',
            'session_type' => 'nullable|in:beginner,intermediate,advanced,custom',
            'notes' => 'nullable|string|max:1000',
            'promo_code' => 'nullable|string|exists:service_promo_codes,code'
        ]);

        DB::beginTransaction();
        try {
            // Vérifier disponibilité de l'instructeur
            if (!$instructor->is_available) {
                return response()->json([
                    'success' => false,
                    'message' => 'Instructor is not currently available'
                ], 400);
            }

            // Vérifier que le lieu appartient à cet instructeur
            $location = InstructorLocation::find($validated['location_id']);
            if ($location->instructor_id !== $instructor->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid location for this instructor'
                ], 400);
            }

            // Vérifier qu'il n'y a pas déjà une réservation à ce créneau
            $durationHours = $validated['duration_hours'] ?? 1;
            $endTime = date('H:i', strtotime($validated['start_time'] . " +{$durationHours} hours"));

            $conflictExists = ServiceBooking::whereHas('service', function($q) use ($instructor) {
                $q->where('provider_id', $instructor->provider_id)
                  ->whereHas('category', function($cq) {
                      $cq->where('slug', 'riding-instructor');
                  });
            })
            ->where('booking_date', $validated['booking_date'])
            ->where(function($q) use ($validated, $endTime) {
                $q->whereBetween('start_time', [$validated['start_time'], $endTime])
                  ->orWhereBetween('end_time', [$validated['start_time'], $endTime]);
            })
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->exists();

            if ($conflictExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'This time slot is already booked'
                ], 400);
            }

            // Trouver le service d'instructeur
            $instructorService = Service::whereHas('category', function($q) {
                $q->where('slug', 'riding-instructor');
            })
            ->where('provider_id', $instructor->provider_id)
            ->where('is_available', true)
            ->first();

            if (!$instructorService) {
                return response()->json([
                    'success' => false,
                    'message' => 'Instructor service not available'
                ], 400);
            }

            // Calculer le prix (prix par heure)
            $pricePerHour = $instructorService->price;
            $totalPrice = $pricePerHour * $durationHours;

            // Appliquer code promo
            $discountAmount = 0;
            if ($request->promo_code) {
                $promoResult = $this->applyPromoCode($totalPrice, $request->promo_code);
                $totalPrice = $promoResult['final_price'];
                $discountAmount = $promoResult['discount_amount'];
            }

            // Créer la réservation
            $booking = ServiceBooking::create([
                'service_id' => $instructorService->id,
                'user_id' => $user->id,
                'provider_id' => $instructor->provider_id,
                'booking_date' => $validated['booking_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $endTime,
                'status' => 'pending',
                'price' => $totalPrice,
                'payment_status' => 'pending',
                'pickup_location' => $location->location_name, // Lieu du cours
                'notes' => ($validated['notes'] ?? '') . " | Session: " . ($validated['session_type'] ?? 'standard') . " | Duration: {$durationHours}h"
            ]);

            DB::commit();

            // TODO: Notifier l'instructeur
            // TODO: Créer transaction de paiement

            return response()->json([
                'success' => true,
                'data' => [
                    'booking' => $booking->load(['service', 'provider']),
                    'instructor' => $instructor,
                    'location' => $location,
                    'duration_hours' => $durationHours,
                    'price_per_hour' => $pricePerHour,
                    'total_price' => $totalPrice,
                    'discount_amount' => $discountAmount,
                    'payment_required' => true
                ],
                'message' => 'Instructor session booked successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to book session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/riding-instructors/{id}/availability",
     *     summary="Disponibilités d'un instructeur",
     *     description="Récupère les créneaux disponibles d'un instructeur pour une période donnée",
     *     operationId="getInstructorAvailability",
     *     tags={"Riding Instructors"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'instructeur",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Date de début",
     *         required=true,
     *         @OA\Schema(type="string", format="date", example="2025-02-01")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Date de fin",
     *         required=true,
     *         @OA\Schema(type="string", format="date", example="2025-02-07")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Disponibilités récupérées",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="available_slots",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="date", type="string", format="date"),
     *                         @OA\Property(property="time_slots", type="array", @OA\Items(type="string", example="09:00-11:00"))
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="booked_slots",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="date", type="string"),
     *                         @OA\Property(property="start_time", type="string"),
     *                         @OA\Property(property="end_time", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function availability(Request $request, $id)
    {
        $instructor = RidingInstructor::find($id);

        if (!$instructor) {
            return response()->json([
                'success' => false,
                'message' => 'Riding instructor not found'
            ], 404);
        }

        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date'
        ]);

        // Récupérer les réservations existantes
        $bookedSlots = ServiceBooking::whereHas('service', function($q) use ($instructor) {
            $q->where('provider_id', $instructor->provider_id)
              ->whereHas('category', function($cq) {
                  $cq->where('slug', 'riding-instructor');
              });
        })
        ->whereBetween('booking_date', [$validated['from_date'], $validated['to_date']])
        ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
        ->select('booking_date', 'start_time', 'end_time')
        ->get();

        // Générer les créneaux disponibles (exemple: 8h-18h, sessions de 2h)
        $availableSlots = [];
        $currentDate = \Carbon\Carbon::parse($validated['from_date']);
        $endDate = \Carbon\Carbon::parse($validated['to_date']);

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $slots = [];

            // Créneaux: 08:00-10:00, 10:00-12:00, 13:00-15:00, 15:00-17:00, 17:00-19:00
            $timeSlots = [
                ['08:00', '10:00'],
                ['10:00', '12:00'],
                ['13:00', '15:00'],
                ['15:00', '17:00'],
                ['17:00', '19:00']
            ];

            foreach ($timeSlots as $slot) {
                $isBooked = $bookedSlots->contains(function($booking) use ($dateStr, $slot) {
                    return $booking->booking_date->format('Y-m-d') === $dateStr &&
                           (($booking->start_time >= $slot[0] && $booking->start_time < $slot[1]) ||
                            ($booking->end_time > $slot[0] && $booking->end_time <= $slot[1]));
                });

                if (!$isBooked) {
                    $slots[] = $slot[0] . '-' . $slot[1];
                }
            }

            if (!empty($slots)) {
                $availableSlots[] = [
                    'date' => $dateStr,
                    'time_slots' => $slots
                ];
            }

            $currentDate->addDay();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'instructor' => [
                    'id' => $instructor->id,
                    'name' => $instructor->instructor_name,
                    'name_ar' => $instructor->instructor_name_ar
                ],
                'available_slots' => $availableSlots,
                'booked_slots' => $bookedSlots,
                'period' => [
                    'from' => $validated['from_date'],
                    'to' => $validated['to_date']
                ]
            ],
            'message' => 'Availability retrieved successfully'
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/provider/riding-instructors",
     *     summary="Ajouter un instructeur (Provider)",
     *     description="Permet au fournisseur d'ajouter un instructeur à son équipe",
     *     operationId="createInstructor",
     *     tags={"Riding Instructors"},
     *     security={{"bearer":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"instructor_name", "experience_years"},
     *             @OA\Property(property="instructor_name", type="string", example="Ahmed Al-Rashid"),
     *             @OA\Property(property="instructor_name_ar", type="string", example="أحمد الراشد"),
     *             @OA\Property(property="bio", type="string"),
     *             @OA\Property(property="bio_ar", type="string"),
     *             @OA\Property(property="experience_years", type="integer", example=10),
     *             @OA\Property(
     *                 property="certifications",
     *                 type="array",
     *                 @OA\Items(type="string", example="Advanced Riding Techniques")
     *             ),
     *             @OA\Property(property="photo", type="string", format="binary")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Instructeur ajouté"),
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
            'instructor_name' => 'required|string|max:255',
            'instructor_name_ar' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:2000',
            'bio_ar' => 'nullable|string|max:2000',
            'experience_years' => 'required|integer|min:0|max:50',
            'certifications' => 'nullable|array',
            'certifications.*' => 'string|max:255',
            'photo' => 'nullable|image|max:2048'
        ]);

        DB::beginTransaction();
        try {
            // Upload photo
            if ($request->hasFile('photo')) {
                $validated['photo'] = $request->file('photo')->store('instructors', 'public');
            }

            $instructor = RidingInstructor::create([
                'provider_id' => $user->serviceProvider->id,
                'instructor_name' => $validated['instructor_name'],
                'instructor_name_ar' => $validated['instructor_name_ar'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'bio_ar' => $validated['bio_ar'] ?? null,
                'photo' => $validated['photo'] ?? null,
                'certifications' => json_encode($validated['certifications'] ?? []),
                'experience_years' => $validated['experience_years'],
                'rating_average' => 0,
                'total_sessions' => 0,
                'is_available' => true
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $instructor,
                'message' => 'Riding instructor added successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add instructor',
                'error' => $e->getMessage()
            ], 500);
        }
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