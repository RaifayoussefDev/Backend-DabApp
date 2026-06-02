<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\InstructorLocation;
use App\Models\RidingInstructor;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;


/**
 * @OA\Tag(
 *     name="Riding Instructors",
 *     description="Browse instructors, check availability, book sessions, and manage provider schedules"
 * )
 */
class RidingInstructorController extends Controller
{
    // Default slots used when no ServiceSchedule is configured for the instructor's service
    private const DEFAULT_SLOTS = [
        ['08:00', '10:00'],
        ['10:00', '12:00'],
        ['13:00', '15:00'],
        ['15:00', '17:00'],
        ['17:00', '19:00'],
    ];

    // ---------------------------------------------------------------
    // Public — list & detail
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/riding-instructors",
     *     summary="List riding instructors",
     *     description="Returns paginated list of available instructors with optional filters.",
     *     operationId="getRidingInstructors",
     *     tags={"Riding Instructors"},
     *     @OA\Parameter(name="city_id",              in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="min_experience_years", in="query", required=false, @OA\Schema(type="integer", example=5)),
     *     @OA\Parameter(name="min_rating",           in="query", required=false, @OA\Schema(type="number",  format="float", example=4.0)),
     *     @OA\Parameter(name="is_available",         in="query", required=false, @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Parameter(name="search",               in="query", required=false, @OA\Schema(type="string",  example="Ahmed")),
     *     @OA\Parameter(name="sort_by",              in="query", required=false,
     *         @OA\Schema(type="string", enum={"rating","experience","sessions"}, default="rating")),
     *     @OA\Parameter(name="per_page",             in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(
     *         response=200,
     *         description="Instructors retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",               type="integer", example=1),
     *                         @OA\Property(property="instructor_name",  type="string",  example="Khalid Al-Mansouri"),
     *                         @OA\Property(property="instructor_name_ar", type="string", example="خالد المنصوري"),
     *                         @OA\Property(property="bio",              type="string"),
     *                         @OA\Property(property="photo",            type="string",  nullable=true),
     *                         @OA\Property(property="experience_years", type="integer", example=12),
     *                         @OA\Property(property="rating_average",   type="number",  format="float", example=4.9),
     *                         @OA\Property(property="total_sessions",   type="integer", example=340),
     *                         @OA\Property(property="is_available",     type="boolean", example=true),
     *                         @OA\Property(property="certifications",   type="array", @OA\Items(type="string")),
     *                         @OA\Property(property="provider", type="object",
     *                             @OA\Property(property="business_name", type="string"),
     *                             @OA\Property(property="city", type="object")
     *                         ),
     *                         @OA\Property(property="locations", type="array",
     *                             @OA\Items(type="object",
     *                                 @OA\Property(property="id",             type="integer"),
     *                                 @OA\Property(property="location_name",  type="string"),
     *                                 @OA\Property(property="location_name_ar", type="string"),
     *                                 @OA\Property(property="latitude",       type="number", format="float", nullable=true),
     *                                 @OA\Property(property="longitude",      type="number", format="float", nullable=true),
     *                                 @OA\Property(property="is_available",   type="boolean"),
     *                                 @OA\Property(property="city",           type="object")
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="per_page", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = RidingInstructor::with(['provider.city', 'locations.city'])
            ->where('is_available', true);

        if ($request->filled('city_id')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('provider', fn ($pq) => $pq->where('city_id', $request->city_id))
                  ->orWhereHas('locations', fn ($lq) => $lq->where('city_id', $request->city_id));
            });
        }

        if ($request->filled('min_experience_years')) {
            $query->where('experience_years', '>=', $request->min_experience_years);
        }

        if ($request->filled('min_rating')) {
            $query->where('rating_average', '>=', $request->min_rating);
        }

        if ($request->filled('is_available')) {
            $query->where('is_available', $request->is_available);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q
                ->where('instructor_name', 'LIKE', "%{$search}%")
                ->orWhere('instructor_name_ar', 'LIKE', "%{$search}%")
            );
        }

        match ($request->get('sort_by', 'rating')) {
            'experience' => $query->orderByDesc('experience_years'),
            'sessions'   => $query->orderByDesc('total_sessions'),
            default      => $query->orderByDesc('rating_average'),
        };

        $instructors = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $instructors,
            'message' => 'Riding instructors retrieved successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/riding-instructors/{id}",
     *     summary="Instructor detail",
     *     description="Full instructor profile with locations, certifications, recent reviews, and other provider services.",
     *     operationId="getRidingInstructor",
     *     tags={"Riding Instructors"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Instructor found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",               type="integer", example=1),
     *                 @OA\Property(property="instructor_name",  type="string",  example="Khalid Al-Mansouri"),
     *                 @OA\Property(property="instructor_name_ar", type="string", example="خالد المنصوري"),
     *                 @OA\Property(property="bio",              type="string"),
     *                 @OA\Property(property="bio_ar",           type="string"),
     *                 @OA\Property(property="photo",            type="string",  nullable=true),
     *                 @OA\Property(property="experience_years", type="integer", example=12),
     *                 @OA\Property(property="rating_average",   type="number",  format="float", example=4.9),
     *                 @OA\Property(property="total_sessions",   type="integer", example=340),
     *                 @OA\Property(property="certifications",   type="array",   @OA\Items(type="string")),
     *                 @OA\Property(property="provider", type="object",
     *                     @OA\Property(property="business_name", type="string"),
     *                     @OA\Property(property="city",          type="object"),
     *                     @OA\Property(property="user", type="object",
     *                         @OA\Property(property="first_name", type="string"),
     *                         @OA\Property(property="last_name",  type="string"),
     *                         @OA\Property(property="phone",      type="string")
     *                     )
     *                 ),
     *                 @OA\Property(property="locations", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",             type="integer"),
     *                         @OA\Property(property="location_name",  type="string"),
     *                         @OA\Property(property="location_name_ar", type="string"),
     *                         @OA\Property(property="latitude",       type="number", format="float", nullable=true),
     *                         @OA\Property(property="longitude",      type="number", format="float", nullable=true),
     *                         @OA\Property(property="is_available",   type="boolean"),
     *                         @OA\Property(property="city",           type="object")
     *                     )
     *                 ),
     *                 @OA\Property(property="recent_reviews",  type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="other_services",  type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Instructor not found")
     * )
     */
    public function show(string $id)
    {
        $instructor = RidingInstructor::with([
            'provider.city',
            'provider.user:id,first_name,last_name,phone',
            'locations.city',
        ])->find($id);

        if (!$instructor) {
            return response()->json(['success' => false, 'message' => 'Riding instructor not found'], 404);
        }

        $recentReviews = \App\Models\ServiceReview::whereHas('booking', function ($q) use ($instructor) {
            $q->whereHas('service', function ($sq) use ($instructor) {
                $sq->where('provider_id', $instructor->provider_id)
                   ->whereHas('category', fn ($cq) => $cq->where('slug', 'riding-instructor'));
            });
        })
        ->where('is_approved', true)
        ->with('user:id,first_name,last_name,avatar')
        ->latest()->limit(5)->get();

        $otherServices = Service::where('provider_id', $instructor->provider_id)
            ->where('is_available', true)
            ->whereDoesntHave('category', fn ($q) => $q->where('slug', 'riding-instructor'))
            ->with('category:id,name,name_ar,icon')
            ->select('id', 'name', 'name_ar', 'price', 'image', 'category_id')
            ->limit(4)->get();

        return response()->json([
            'success' => true,
            'data'    => array_merge($instructor->toArray(), [
                'recent_reviews' => $recentReviews,
                'other_services' => $otherServices,
            ]),
            'message' => 'Instructor found',
        ]);
    }

    // ---------------------------------------------------------------
    // Public — locations
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/instructor-locations",
     *     summary="List instructor locations",
     *     description="All available training locations with GPS coordinates, city, and instructor info.",
     *     operationId="getInstructorLocations",
     *     tags={"Riding Instructors"},
     *     @OA\Parameter(name="city_id",       in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="instructor_id", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Locations retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="count",   type="integer", example=6),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id",             type="integer"),
     *                     @OA\Property(property="location_name",  type="string",  example="Al-Naseem Training Circuit"),
     *                     @OA\Property(property="location_name_ar", type="string", example="حلبة النسيم للتدريب"),
     *                     @OA\Property(property="latitude",       type="number",  format="float", nullable=true, example=24.7250),
     *                     @OA\Property(property="longitude",      type="number",  format="float", nullable=true, example=46.6900),
     *                     @OA\Property(property="is_available",   type="boolean", example=true),
     *                     @OA\Property(property="city",           type="object",
     *                         @OA\Property(property="id",   type="integer"),
     *                         @OA\Property(property="name", type="string", example="Riyadh")
     *                     ),
     *                     @OA\Property(property="instructor", type="object",
     *                         @OA\Property(property="id",              type="integer"),
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
        $query = InstructorLocation::with(['city', 'instructor'])->where('is_available', true);

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        if ($request->filled('instructor_id')) {
            $query->where('instructor_id', $request->instructor_id);
        }

        $locations = $query->get();

        return response()->json([
            'success' => true,
            'data'    => $locations,
            'count'   => $locations->count(),
            'message' => 'Instructor locations retrieved successfully',
        ]);
    }

    // ---------------------------------------------------------------
    // Public — availability
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/riding-instructors/{id}/availability",
     *     summary="Instructor availability slots",
     *     description="Returns available and booked time slots for a given date range. Slots are generated from the instructor's configured schedule (ServiceSchedule). If no schedule is configured, default slots (08:00–19:00) are used. Optionally filter by location.",
     *     operationId="getInstructorAvailability",
     *     tags={"Riding Instructors"},
     *     @OA\Parameter(name="id",          in="path",  required=true,  @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="from_date",   in="query", required=true,  @OA\Schema(type="string", format="date", example="2026-06-10")),
     *     @OA\Parameter(name="to_date",     in="query", required=true,  @OA\Schema(type="string", format="date", example="2026-06-16")),
     *     @OA\Parameter(name="location_id", in="query", required=false, @OA\Schema(type="integer", example=1),
     *         description="Filter by specific instructor location. Returns location coordinates when provided."),
     *     @OA\Response(
     *         response=200,
     *         description="Availability retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="instructor", type="object",
     *                     @OA\Property(property="id",       type="integer", example=1),
     *                     @OA\Property(property="name",     type="string",  example="Khalid Al-Mansouri"),
     *                     @OA\Property(property="name_ar",  type="string",  example="خالد المنصوري")
     *                 ),
     *                 @OA\Property(property="location", type="object", nullable=true,
     *                     description="Populated only when location_id is provided",
     *                     @OA\Property(property="id",             type="integer"),
     *                     @OA\Property(property="name",           type="string"),
     *                     @OA\Property(property="name_ar",        type="string"),
     *                     @OA\Property(property="latitude",       type="number", format="float", nullable=true),
     *                     @OA\Property(property="longitude",      type="number", format="float", nullable=true),
     *                     @OA\Property(property="is_available",   type="boolean")
     *                 ),
     *                 @OA\Property(property="available_slots", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="date",       type="string", format="date", example="2026-06-10"),
     *                         @OA\Property(property="day_name",   type="string", example="Wednesday"),
     *                         @OA\Property(property="time_slots", type="array", @OA\Items(type="string", example="08:00-10:00"))
     *                     )
     *                 ),
     *                 @OA\Property(property="booked_slots", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="date",       type="string", format="date"),
     *                         @OA\Property(property="start_time", type="string", example="10:00"),
     *                         @OA\Property(property="end_time",   type="string", example="12:00")
     *                     )
     *                 ),
     *                 @OA\Property(property="schedule_source", type="string",
     *                     enum={"configured","default"},
     *                     description="'configured' = slots from provider schedule, 'default' = hardcoded fallback"),
     *                 @OA\Property(property="period", type="object",
     *                     @OA\Property(property="from", type="string", format="date"),
     *                     @OA\Property(property="to",   type="string", format="date")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Instructor not found"),
     *     @OA\Response(response=422, description="location_id does not belong to this instructor")
     * )
     */
    public function availability(Request $request, string $id)
    {
        $instructor = RidingInstructor::find($id);

        if (!$instructor) {
            return response()->json(['success' => false, 'message' => 'Riding instructor not found'], 404);
        }

        $validated = $request->validate([
            'from_date'   => 'required|date',
            'to_date'     => 'required|date|after_or_equal:from_date',
            'location_id' => 'nullable|integer|exists:instructor_locations,id',
        ]);

        if (!empty($validated['location_id'])) {
            $belongs = $instructor->locations()->where('id', $validated['location_id'])->exists();
            if (!$belongs) {
                return response()->json(['success' => false, 'message' => 'Location does not belong to this instructor'], 422);
            }
        }

        // Load instructor service to get its configured schedule
        $instructorService = Service::where('provider_id', $instructor->provider_id)
            ->whereHas('category', fn ($q) => $q->where('slug', 'riding-instructor'))
            ->where('is_available', true)
            ->first();

        // Build schedule map keyed by day_of_week (0=Sun … 6=Sat)
        $scheduleMap = [];
        $scheduleSource = 'default';

        if ($instructorService) {
            $schedules = ServiceSchedule::where('service_id', $instructorService->id)
                ->where('is_available', true)
                ->get();

            if ($schedules->isNotEmpty()) {
                $scheduleSource = 'configured';
                foreach ($schedules as $s) {
                    $scheduleMap[$s->day_of_week] = [$s->start_time, $s->end_time];
                }
            }
        }

        // Booked slots in date range
        $bookedSlots = ServiceBooking::when($instructorService, function ($q) use ($instructorService) {
            $q->where('service_id', $instructorService->id);
        }, function ($q) use ($instructor) {
            $q->whereHas('service', fn ($sq) => $sq
                ->where('provider_id', $instructor->provider_id)
                ->whereHas('category', fn ($cq) => $cq->where('slug', 'riding-instructor'))
            );
        })
        ->whereBetween('booking_date', [$validated['from_date'], $validated['to_date']])
        ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
        ->when(!empty($validated['location_id']), fn ($q) => $q->where('instructor_location_id', $validated['location_id']))
        ->select('booking_date', 'start_time', 'end_time')
        ->get();

        // Generate available slots per day
        $availableSlots = [];
        $current = Carbon::parse($validated['from_date']);
        $end     = Carbon::parse($validated['to_date']);

        while ($current <= $end) {
            $dateStr    = $current->format('Y-m-d');
            $dayOfWeek  = (int) $current->dayOfWeek;

            // Determine time slots for this day
            if ($scheduleSource === 'configured' && isset($scheduleMap[$dayOfWeek])) {
                [$dayStart, $dayEnd] = $scheduleMap[$dayOfWeek];
                $slots = $this->generateSlots($dayStart, $dayEnd, 120);
            } elseif ($scheduleSource === 'default') {
                $slots = array_map(fn ($s) => $s[0] . '-' . $s[1], self::DEFAULT_SLOTS);
            } else {
                // Day not in configured schedule → closed
                $current->addDay();
                continue;
            }

            // Remove booked slots
            $freeSlots = array_filter($slots, function ($slot) use ($bookedSlots, $dateStr) {
                [$slotStart, $slotEnd] = explode('-', $slot);
                return !$bookedSlots->contains(function ($b) use ($dateStr, $slotStart, $slotEnd) {
                    return $b->booking_date->format('Y-m-d') === $dateStr
                        && (($b->start_time >= $slotStart && $b->start_time < $slotEnd)
                         || ($b->end_time   >  $slotStart && $b->end_time  <= $slotEnd));
                });
            });

            if (!empty($freeSlots)) {
                $availableSlots[] = [
                    'date'       => $dateStr,
                    'day_name'   => $current->format('l'),
                    'time_slots' => array_values($freeSlots),
                ];
            }

            $current->addDay();
        }

        $locationData = null;
        if (!empty($validated['location_id'])) {
            $loc = $instructor->locations()->find($validated['location_id']);
            $locationData = [
                'id'           => $loc->id,
                'name'         => $loc->location_name,
                'name_ar'      => $loc->location_name_ar,
                'city_id'      => $loc->city_id,
                'latitude'     => $loc->latitude,
                'longitude'    => $loc->longitude,
                'is_available' => $loc->is_available,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'instructor'      => [
                    'id'      => $instructor->id,
                    'name'    => $instructor->instructor_name,
                    'name_ar' => $instructor->instructor_name_ar,
                ],
                'location'        => $locationData,
                'available_slots' => $availableSlots,
                'booked_slots'    => $bookedSlots,
                'schedule_source' => $scheduleSource,
                'period'          => ['from' => $validated['from_date'], 'to' => $validated['to_date']],
            ],
            'message' => 'Availability retrieved successfully',
        ]);
    }

    // ---------------------------------------------------------------
    // Authenticated — book session
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/riding-instructors/{id}/book-session",
     *     summary="Book a riding session",
     *     description="Creates a session booking with the instructor. Price = service price × duration_hours. Checks for time-slot conflicts before confirming. Sends booking to pending status awaiting provider confirmation.",
     *     operationId="bookInstructorSession",
     *     tags={"Riding Instructors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"booking_date","start_time","location_id"},
     *             @OA\Property(property="booking_date",   type="string", format="date",                   example="2026-06-15"),
     *             @OA\Property(property="start_time",     type="string", format="time",                   example="09:00",
     *                 description="24h format HH:MM. Must match an available slot from the availability endpoint."),
     *             @OA\Property(property="duration_hours", type="integer", minimum=1, maximum=4,            example=2,
     *                 description="Session length in hours (default 1)."),
     *             @OA\Property(property="location_id",    type="integer",                                 example=1,
     *                 description="ID from GET /api/instructor-locations or the locations array in instructor detail."),
     *             @OA\Property(property="session_type",   type="string",
     *                 enum={"beginner","intermediate","advanced","custom"},                                 example="beginner"),
     *             @OA\Property(property="notes",          type="string",                                  example="First time on a motorcycle")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Session booked — awaiting provider confirmation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Instructor session booked successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="booking", type="object",
     *                     @OA\Property(property="id",                    type="integer", example=42),
     *                     @OA\Property(property="booking_date",          type="string",  format="date"),
     *                     @OA\Property(property="start_time",            type="string",  example="09:00"),
     *                     @OA\Property(property="end_time",              type="string",  example="11:00"),
     *                     @OA\Property(property="session_type",          type="string",  example="beginner"),
     *                     @OA\Property(property="instructor_location_id",type="integer", example=1),
     *                     @OA\Property(property="status",                type="string",  example="pending"),
     *                     @OA\Property(property="price",                 type="number",  format="float", example=300.00),
     *                     @OA\Property(property="payment_status",        type="string",  example="pending")
     *                 ),
     *                 @OA\Property(property="instructor", type="object"),
     *                 @OA\Property(property="location",   type="object",
     *                     @OA\Property(property="id",             type="integer"),
     *                     @OA\Property(property="location_name",  type="string"),
     *                     @OA\Property(property="location_name_ar", type="string"),
     *                     @OA\Property(property="latitude",       type="number", format="float", nullable=true),
     *                     @OA\Property(property="longitude",      type="number", format="float", nullable=true)
     *                 ),
     *                 @OA\Property(property="duration_hours",  type="integer", example=2),
     *                 @OA\Property(property="price_per_hour",  type="number",  format="float", example=150.00),
     *                 @OA\Property(property="total_price",     type="number",  format="float", example=300.00)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Instructor unavailable, invalid location, or slot already booked"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Instructor not found or no active service")
     * )
     */
    public function bookSession(Request $request, string $id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $instructor = RidingInstructor::with('provider')->find($id);

        if (!$instructor) {
            return response()->json(['success' => false, 'message' => 'Riding instructor not found'], 404);
        }

        $validated = $request->validate([
            'booking_date'   => 'required|date|after:today',
            'start_time'     => 'required|date_format:H:i',
            'duration_hours' => 'nullable|integer|min:1|max:4',
            'location_id'    => 'required|exists:instructor_locations,id',
            'session_type'   => 'nullable|in:beginner,intermediate,advanced,custom',
            'notes'          => 'nullable|string|max:1000',
        ]);

        if (!$instructor->is_available) {
            return response()->json(['success' => false, 'message' => 'Instructor is not currently available'], 400);
        }

        $location = InstructorLocation::find($validated['location_id']);

        if ($location->instructor_id !== $instructor->id) {
            return response()->json(['success' => false, 'message' => 'Invalid location for this instructor'], 400);
        }

        $durationHours = $validated['duration_hours'] ?? 1;
        $endTime = date('H:i', strtotime($validated['start_time'] . " +{$durationHours} hours"));

        $instructorService = Service::where('provider_id', $instructor->provider_id)
            ->whereHas('category', fn ($q) => $q->where('slug', 'riding-instructor'))
            ->where('is_available', true)
            ->first();

        if (!$instructorService) {
            return response()->json(['success' => false, 'message' => 'Instructor service not available'], 404);
        }

        $conflictExists = ServiceBooking::where('service_id', $instructorService->id)
            ->where('instructor_location_id', $location->id)
            ->where('booking_date', $validated['booking_date'])
            ->where(function ($q) use ($validated, $endTime) {
                $q->whereBetween('start_time', [$validated['start_time'], $endTime])
                  ->orWhereBetween('end_time',  [$validated['start_time'], $endTime]);
            })
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->exists();

        if ($conflictExists) {
            return response()->json(['success' => false, 'message' => 'This time slot is already booked'], 400);
        }

        DB::beginTransaction();
        try {
            $totalPrice = $instructorService->price * $durationHours;

            $booking = ServiceBooking::create([
                'service_id'             => $instructorService->id,
                'user_id'                => $user->id,
                'provider_id'            => $instructor->provider_id,
                'instructor_location_id' => $location->id,
                'session_type'           => $validated['session_type'] ?? null,
                'booking_date'           => $validated['booking_date'],
                'start_time'             => $validated['start_time'],
                'end_time'               => $endTime,
                'status'                 => 'pending',
                'price'                  => $totalPrice,
                'payment_status'         => 'pending',
                'pickup_location'        => $location->location_name,
                'notes'                  => $validated['notes'] ?? null,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to book session', 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'booking'       => $booking->load(['service', 'provider']),
                'instructor'    => $instructor,
                'location'      => $location,
                'duration_hours'=> $durationHours,
                'price_per_hour'=> $instructorService->price,
                'total_price'   => $totalPrice,
            ],
            'message' => 'Instructor session booked successfully',
        ], 201);
    }

    // ---------------------------------------------------------------
    // Provider — add instructor
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/provider/riding-instructors",
     *     summary="Add instructor to your team (Provider)",
     *     description="Creates a new riding instructor linked to the authenticated provider.",
     *     operationId="createInstructor",
     *     tags={"Riding Instructors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"instructor_name","experience_years"},
     *             @OA\Property(property="instructor_name",    type="string",  example="Khalid Al-Mansouri"),
     *             @OA\Property(property="instructor_name_ar", type="string",  example="خالد المنصوري"),
     *             @OA\Property(property="bio",                type="string"),
     *             @OA\Property(property="bio_ar",             type="string"),
     *             @OA\Property(property="experience_years",   type="integer", example=10),
     *             @OA\Property(property="certifications",     type="array",   @OA\Items(type="string")),
     *             @OA\Property(property="photo",              type="string",  format="binary")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Instructor created"),
     *     @OA\Response(response=403, description="Not a service provider")
     * )
     */
    public function store(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user->serviceProvider) {
            return response()->json(['success' => false, 'message' => 'You are not a service provider'], 403);
        }

        $validated = $request->validate([
            'instructor_name'    => 'required|string|max:255',
            'instructor_name_ar' => 'nullable|string|max:255',
            'bio'                => 'nullable|string|max:2000',
            'bio_ar'             => 'nullable|string|max:2000',
            'experience_years'   => 'required|integer|min:0|max:50',
            'certifications'     => 'nullable|array',
            'certifications.*'   => 'string|max:255',
            'photo'              => 'nullable|image|max:2048',
        ]);

        DB::beginTransaction();
        try {
            if ($request->hasFile('photo')) {
                $validated['photo'] = $request->file('photo')->store('instructors', 'public');
            }

            $instructor = RidingInstructor::create([
                'provider_id'        => $user->serviceProvider->id,
                'instructor_name'    => $validated['instructor_name'],
                'instructor_name_ar' => $validated['instructor_name_ar'] ?? null,
                'bio'                => $validated['bio'] ?? null,
                'bio_ar'             => $validated['bio_ar'] ?? null,
                'photo'              => $validated['photo'] ?? null,
                'certifications'     => json_encode($validated['certifications'] ?? []),
                'experience_years'   => $validated['experience_years'],
                'rating_average'     => 0,
                'total_sessions'     => 0,
                'is_available'       => true,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to add instructor', 'error' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'data' => $instructor, 'message' => 'Riding instructor added successfully'], 201);
    }

    // ---------------------------------------------------------------
    // Provider — schedule management
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/provider/riding-instructors/{id}/schedule",
     *     summary="Get instructor schedule (Provider)",
     *     description="Returns the weekly schedule (open hours per day) configured for this instructor's service. Days not listed are closed.",
     *     operationId="getInstructorSchedule",
     *     tags={"Riding Instructors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Schedule retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="service_id", type="integer", example=5),
     *                 @OA\Property(property="schedule", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",           type="integer"),
     *                         @OA\Property(property="day_of_week",  type="integer", example=0,
     *                             description="0=Sunday … 6=Saturday"),
     *                         @OA\Property(property="day_name",     type="string",  example="Sunday"),
     *                         @OA\Property(property="day_name_ar",  type="string",  example="الأحد"),
     *                         @OA\Property(property="start_time",   type="string",  example="08:00"),
     *                         @OA\Property(property="end_time",     type="string",  example="18:00"),
     *                         @OA\Property(property="is_available", type="boolean", example=true)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Instructor does not belong to your provider account"),
     *     @OA\Response(response=404, description="Instructor or riding-instructor service not found")
     * )
     */
    public function getSchedule(Request $request, string $id)
    {
        $user       = JWTAuth::parseToken()->authenticate();
        $instructor = RidingInstructor::find($id);

        if (!$instructor) {
            return response()->json(['success' => false, 'message' => 'Instructor not found'], 404);
        }

        if (!$user->serviceProvider || $instructor->provider_id !== $user->serviceProvider->id) {
            return response()->json(['success' => false, 'message' => 'This instructor does not belong to your account'], 403);
        }

        $service = Service::where('provider_id', $instructor->provider_id)
            ->whereHas('category', fn ($q) => $q->where('slug', 'riding-instructor'))
            ->first();

        if (!$service) {
            return response()->json(['success' => false, 'message' => 'No riding-instructor service found for this provider'], 404);
        }

        $schedule = ServiceSchedule::where('service_id', $service->id)->orderBy('day_of_week')->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'service_id' => $service->id,
                'schedule'   => $schedule,
            ],
            'message' => 'Schedule retrieved successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/provider/riding-instructors/{id}/schedule",
     *     summary="Set instructor schedule (Provider)",
     *     description="Upserts the weekly working hours for this instructor's service. Send all days you want to configure. Days omitted are left unchanged. Set is_available=false to close a day without deleting the record.",
     *     operationId="updateInstructorSchedule",
     *     tags={"Riding Instructors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"schedule"},
     *             @OA\Property(property="schedule", type="array",
     *                 @OA\Items(type="object",
     *                     required={"day_of_week","start_time","end_time"},
     *                     @OA\Property(property="day_of_week",  type="integer", minimum=0, maximum=6, example=0,
     *                         description="0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday"),
     *                     @OA\Property(property="start_time",   type="string",  example="08:00",
     *                         description="24h format HH:MM"),
     *                     @OA\Property(property="end_time",     type="string",  example="18:00"),
     *                     @OA\Property(property="is_available", type="boolean", example=true)
     *                 ),
     *                 example={
     *                     {"day_of_week": 0, "start_time": "08:00", "end_time": "18:00", "is_available": true},
     *                     {"day_of_week": 1, "start_time": "08:00", "end_time": "18:00", "is_available": true},
     *                     {"day_of_week": 5, "start_time": "08:00", "end_time": "12:00", "is_available": true},
     *                     {"day_of_week": 6, "start_time": "08:00", "end_time": "12:00", "is_available": false}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Schedule saved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success",  type="boolean", example=true),
     *             @OA\Property(property="message",  type="string",  example="Schedule updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="service_id",     type="integer", example=5),
     *                 @OA\Property(property="updated_days",   type="integer", example=4),
     *                 @OA\Property(property="schedule",       type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Instructor does not belong to your provider account"),
     *     @OA\Response(response=404, description="Instructor or riding-instructor service not found"),
     *     @OA\Response(response=422, description="Validation error — invalid day, time format, or end_time before start_time")
     * )
     */
    public function updateSchedule(Request $request, string $id)
    {
        $user       = JWTAuth::parseToken()->authenticate();
        $instructor = RidingInstructor::find($id);

        if (!$instructor) {
            return response()->json(['success' => false, 'message' => 'Instructor not found'], 404);
        }

        if (!$user->serviceProvider || $instructor->provider_id !== $user->serviceProvider->id) {
            return response()->json(['success' => false, 'message' => 'This instructor does not belong to your account'], 403);
        }

        $validated = $request->validate([
            'schedule'                => 'required|array|min:1',
            'schedule.*.day_of_week'  => 'required|integer|between:0,6',
            'schedule.*.start_time'   => 'required|date_format:H:i',
            'schedule.*.end_time'     => 'required|date_format:H:i|after:schedule.*.start_time',
            'schedule.*.is_available' => 'nullable|boolean',
        ]);

        $service = Service::where('provider_id', $instructor->provider_id)
            ->whereHas('category', fn ($q) => $q->where('slug', 'riding-instructor'))
            ->first();

        if (!$service) {
            return response()->json(['success' => false, 'message' => 'No riding-instructor service found for this provider'], 404);
        }

        foreach ($validated['schedule'] as $day) {
            ServiceSchedule::updateOrCreate(
                ['service_id' => $service->id, 'day_of_week' => $day['day_of_week']],
                [
                    'start_time'   => $day['start_time'],
                    'end_time'     => $day['end_time'],
                    'is_available' => $day['is_available'] ?? true,
                ]
            );
        }

        $schedule = ServiceSchedule::where('service_id', $service->id)->orderBy('day_of_week')->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'service_id'   => $service->id,
                'updated_days' => count($validated['schedule']),
                'schedule'     => $schedule,
            ],
            'message' => 'Schedule updated successfully',
        ]);
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    /**
     * Generate HH:MM-HH:MM slot strings between two times with a fixed duration in minutes.
     */
    private function generateSlots(string $start, string $end, int $durationMinutes = 120): array
    {
        $slots   = [];
        $current = Carbon::createFromFormat('H:i', $start);
        $endTime = Carbon::createFromFormat('H:i', $end);

        while ($current->copy()->addMinutes($durationMinutes)->lte($endTime)) {
            $slotEnd = $current->copy()->addMinutes($durationMinutes);
            $slots[] = $current->format('H:i') . '-' . $slotEnd->format('H:i');
            $current->addMinutes($durationMinutes);
        }

        return $slots;
    }
}
