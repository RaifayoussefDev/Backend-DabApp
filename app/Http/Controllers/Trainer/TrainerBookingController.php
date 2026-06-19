<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Models\TrainerBooking;
use App\Models\TrainerLocation;
use App\Models\TrainerPayment;
use App\Models\PaymentSplit;
use App\Models\TrainerPayout;
use App\Models\TrainerSchedule;
use App\Services\NotificationService;
use App\Services\PayTabsConfigService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Trainer Bookings",
 *     description="Book sessions, check availability, manage booking lifecycle and payments"
 * )
 */
class TrainerBookingController extends Controller
{
    protected NotificationService $notifications;

    public function __construct(NotificationService $notifications)
    {
        $this->notifications = $notifications;
    }

    private const DEFAULT_SLOTS = [
        ['08:00', '10:00'],
        ['10:00', '12:00'],
        ['13:00', '15:00'],
        ['15:00', '17:00'],
        ['17:00', '19:00'],
    ];

    // ---------------------------------------------------------------
    // PUBLIC — Availability
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/trainers/{id}/availability",
     *     summary="Trainer availability slots",
     *     description="Returns available and booked time slots for a given date range. Slots are generated from the trainer's configured schedule. Falls back to default slots (08:00–19:00) if no schedule is configured.",
     *     operationId="getTrainerAvailability",
     *     tags={"Trainer Bookings"},
     *     @OA\Parameter(name="id",          in="path",  required=true,  @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="from_date",   in="query", required=true,  @OA\Schema(type="string", format="date", example="2026-06-20")),
     *     @OA\Parameter(name="to_date",     in="query", required=true,  @OA\Schema(type="string", format="date", example="2026-06-26")),
     *     @OA\Parameter(name="location_id", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Availability retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="trainer", type="object",
     *                     @OA\Property(property="id",              type="integer", example=1),
     *                     @OA\Property(property="name",            type="string",  example="Khalid Al-Mansouri"),
     *                     @OA\Property(property="price_per_hour",  type="number",  format="float", example=150.00)
     *                 ),
     *                 @OA\Property(property="location", type="object", nullable=true,
     *                     @OA\Property(property="id",           type="integer"),
     *                     @OA\Property(property="location_name",type="string"),
     *                     @OA\Property(property="latitude",     type="number", format="float"),
     *                     @OA\Property(property="longitude",    type="number", format="float")
     *                 ),
     *                 @OA\Property(property="available_slots", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="date",       type="string", format="date", example="2026-06-20"),
     *                         @OA\Property(property="day_name",   type="string", example="Saturday"),
     *                         @OA\Property(property="time_slots", type="array",  @OA\Items(type="string", example="08:00-10:00"))
     *                     )
     *                 ),
     *                 @OA\Property(property="schedule_source", type="string", enum={"configured","default"},
     *                     description="'configured' = from trainer schedule, 'default' = fallback"),
     *                 @OA\Property(property="period", type="object",
     *                     @OA\Property(property="from", type="string", format="date"),
     *                     @OA\Property(property="to",   type="string", format="date")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Trainer not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function availability(Request $request, int $id)
    {
        $trainer = Trainer::with('schedules')->approved()->find($id);

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $validated = $request->validate([
            'from_date'   => 'required|date',
            'to_date'     => 'required|date|after_or_equal:from_date',
            'location_id' => 'nullable|integer|exists:trainer_locations,id',
        ]);

        if (!empty($validated['location_id'])) {
            $belongs = $trainer->locations()->where('id', $validated['location_id'])->exists();
            if (!$belongs) {
                return response()->json(['success' => false, 'message' => 'Location does not belong to this trainer'], 422);
            }
        }

        // Build schedule map keyed by day_of_week
        $scheduleMap    = [];
        $scheduleSource = 'default';

        if ($trainer->schedules->isNotEmpty()) {
            $scheduleSource = 'configured';
            foreach ($trainer->schedules->where('is_available', true) as $s) {
                $scheduleMap[$s->day_of_week] = [substr($s->start_time, 0, 5), substr($s->end_time, 0, 5)];
            }
        }

        // Booked slots in the range
        $bookedSlots = TrainerBooking::where('trainer_id', $trainer->id)
            ->whereBetween('booking_date', [$validated['from_date'], $validated['to_date']])
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->when(!empty($validated['location_id']), fn ($q) => $q->where('location_id', $validated['location_id']))
            ->select('booking_date', 'start_time', 'end_time')
            ->get();

        // Generate available slots per day
        $availableSlots = [];
        $current        = Carbon::parse($validated['from_date']);
        $end            = Carbon::parse($validated['to_date']);

        while ($current <= $end) {
            $dateStr   = $current->format('Y-m-d');
            $dayOfWeek = (int) $current->dayOfWeek;

            if ($scheduleSource === 'configured') {
                if (!isset($scheduleMap[$dayOfWeek])) {
                    $current->addDay();
                    continue;
                }
                [$dayStart, $dayEnd] = $scheduleMap[$dayOfWeek];
                $slots = $this->generateSlots($dayStart, $dayEnd, 120);
            } else {
                $slots = array_map(fn ($s) => $s[0] . '-' . $s[1], self::DEFAULT_SLOTS);
            }

            $freeSlots = array_values(array_filter($slots, function ($slot) use ($bookedSlots, $dateStr) {
                [$slotStart, $slotEnd] = explode('-', $slot);
                return !$bookedSlots->contains(function ($b) use ($dateStr, $slotStart, $slotEnd) {
                    $bStart = substr($b->start_time, 0, 5);
                    $bEnd   = substr($b->end_time,   0, 5);
                    return $b->booking_date->format('Y-m-d') === $dateStr
                        && (($bStart >= $slotStart && $bStart < $slotEnd) || ($bEnd > $slotStart && $bEnd <= $slotEnd));
                });
            }));

            if (!empty($freeSlots)) {
                $availableSlots[] = [
                    'date'       => $dateStr,
                    'day_name'   => $current->format('l'),
                    'time_slots' => $freeSlots,
                ];
            }

            $current->addDay();
        }

        $locationData = null;
        if (!empty($validated['location_id'])) {
            $loc = $trainer->locations()->with('city')->find($validated['location_id']);
            $locationData = [
                'id'           => $loc->id,
                'location_name'     => $loc->location_name,
                'location_name_ar'  => $loc->location_name_ar,
                'latitude'     => $loc->latitude,
                'longitude'    => $loc->longitude,
                'is_available' => $loc->is_available,
                'city'         => $loc->city,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'trainer'         => ['id' => $trainer->id, 'name' => $trainer->name, 'price_per_hour' => $trainer->price_per_hour],
                'location'        => $locationData,
                'available_slots' => $availableSlots,
                'schedule_source' => $scheduleSource,
                'period'          => ['from' => $validated['from_date'], 'to' => $validated['to_date']],
            ],
            'message' => 'Availability retrieved successfully',
        ]);
    }

    // ---------------------------------------------------------------
    // AUTH — Book a session
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/trainers/{id}/book",
     *     summary="Book a training session",
     *     description="Creates a session booking and initiates a PayTabs payment. Returns a payment_url to redirect the user to the payment page. Booking stays 'pending' until PayTabs webhook confirms payment.",
     *     operationId="bookTrainerSession",
     *     tags={"Trainer Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"booking_date","start_time","location_id"},
     *             @OA\Property(property="booking_date",   type="string", format="date",  example="2026-06-21",
     *                 description="Must be a future date"),
     *             @OA\Property(property="start_time",     type="string", format="time",  example="10:00",
     *                 description="24h format HH:MM — must match an available slot from the availability endpoint"),
     *             @OA\Property(property="duration_hours", type="integer", minimum=1, maximum=4, example=2,
     *                 description="Session duration in hours (default: 1)"),
     *             @OA\Property(property="location_id",    type="integer", example=1,
     *                 description="ID from GET /api/trainer-locations or the trainer detail locations array"),
     *             @OA\Property(property="session_type",   type="string", enum={"beginner","intermediate","advanced","custom"}, example="beginner"),
     *             @OA\Property(property="notes",          type="string", example="First time on a track — please start with basics")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Booking created — payment URL returned",
     *         @OA\JsonContent(
     *             @OA\Property(property="success",      type="boolean", example=true),
     *             @OA\Property(property="message",      type="string",  example="Booking created. Please complete payment."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="booking_id",    type="integer", example=42),
     *                 @OA\Property(property="payment_url",   type="string",  example="https://secure.paytabs.com/payment/page/...",
     *                     description="Redirect user to this URL to complete payment"),
     *                 @OA\Property(property="total_price",   type="number",  format="float", example=300.00),
     *                 @OA\Property(property="duration_hours",type="integer", example=2),
     *                 @OA\Property(property="price_per_hour",type="number",  format="float", example=150.00),
     *                 @OA\Property(property="session_type",  type="string",  example="beginner"),
     *                 @OA\Property(property="booking_date",  type="string",  format="date",  example="2026-06-21"),
     *                 @OA\Property(property="start_time",    type="string",  example="10:00"),
     *                 @OA\Property(property="end_time",      type="string",  example="12:00"),
     *                 @OA\Property(property="location", type="object",
     *                     @OA\Property(property="id",            type="integer"),
     *                     @OA\Property(property="location_name", type="string"),
     *                     @OA\Property(property="latitude",      type="number", format="float"),
     *                     @OA\Property(property="longitude",     type="number", format="float")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Trainer unavailable, invalid location, or slot already booked"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Trainer not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function book(Request $request, int $id)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::approved()->find($id);

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        if (!$trainer->is_available) {
            return response()->json(['success' => false, 'message' => 'Trainer is not currently available'], 400);
        }

        $validated = $request->validate([
            'booking_date'   => 'required|date|after:today',
            'start_time'     => 'required|date_format:H:i',
            'duration_hours' => 'nullable|integer|min:1|max:4',
            'location_id'    => 'required|exists:trainer_locations,id',
            'session_type'   => 'nullable|in:beginner,intermediate,advanced,custom',
            'notes'          => 'nullable|string|max:1000',
        ]);

        $location = TrainerLocation::find($validated['location_id']);

        if ($location->trainer_id !== $trainer->id) {
            return response()->json(['success' => false, 'message' => 'Invalid location for this trainer'], 400);
        }

        $durationHours = $validated['duration_hours'] ?? 1;
        $startTime     = $validated['start_time'];
        $endTime       = date('H:i', strtotime($startTime . " +{$durationHours} hours"));
        $totalPrice    = $trainer->price_per_hour * $durationHours;

        // Strict bidirectional overlap check — slot is busy if any existing booking overlaps
        $conflict = TrainerBooking::where('trainer_id', $trainer->id)
            ->where('booking_date', $validated['booking_date'])
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->where(fn ($q) => $q
                ->where(fn ($inner) => $inner
                    ->where('start_time', '<', $endTime)
                    ->where('end_time',   '>', $startTime)
                )
            )->exists();

        if ($conflict) {
            return response()->json(['success' => false, 'message' => 'This time slot is already booked'], 400);
        }

        DB::beginTransaction();
        try {
            // 1. Create payment record
            $payment = TrainerPayment::create([
                'user_id'        => $user->id,
                'amount'         => $totalPrice,
                'payment_status' => 'pending',
            ]);

            // 2. Auto-confirm booking immediately — no manual trainer accept needed
            $booking = TrainerBooking::create([
                'trainer_id'     => $trainer->id,
                'user_id'        => $user->id,
                'location_id'    => $location->id,
                'booking_date'   => $validated['booking_date'],
                'start_time'     => $startTime,
                'end_time'       => $endTime,
                'duration_hours' => $durationHours,
                'session_type'   => $validated['session_type'] ?? 'beginner',
                'status'         => 'confirmed',
                'price'          => $totalPrice,
                'payment_id'     => $payment->id,
                'payment_status' => 'pending',
                'notes'          => $validated['notes'] ?? null,
                'confirmed_at'   => now(),
            ]);

            // 3. Immediately initiate PayTabs payment — return URL to client
            $paymentUrl = null;
            if (!config('app.skip_paytabs', false)) {
                $paymentUrl = $this->initiatePayTabsPayment(
                    $payment,
                    $booking,
                    $user,
                    $trainer,
                    $location
                );
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('TrainerBooking: booking creation failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create booking', 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking confirmed. Please complete payment to secure your slot.',
            'data'    => [
                'booking_id'     => $booking->id,
                'status'         => 'confirmed',
                'payment_url'    => $paymentUrl,
                'total_price'    => $totalPrice,
                'duration_hours' => $durationHours,
                'price_per_hour' => $trainer->price_per_hour,
                'session_type'   => $booking->session_type,
                'booking_date'   => $booking->booking_date->format('Y-m-d'),
                'start_time'     => $booking->start_time,
                'end_time'       => $booking->end_time,
                'location'       => $location->load('city'),
            ],
        ], 201);
    }

    // ---------------------------------------------------------------
    // WEBHOOK — PayTabs callback
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/trainer/payments/callback",
     *     summary="PayTabs payment webhook",
     *     description="Called by PayTabs after payment is processed. Updates booking and payment status, creates payment split and payout record. This endpoint is called server-to-server — do not call from the mobile app.",
     *     operationId="trainerPaymentCallback",
     *     tags={"Trainer Bookings"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="tran_ref",     type="string",  example="TST2026123456789"),
     *             @OA\Property(property="cart_id",      type="string",  example="TRAINER_42"),
     *             @OA\Property(property="cart_amount",  type="number",  format="float", example=300.00),
     *             @OA\Property(property="cart_currency",type="string",  example="SAR"),
     *             @OA\Property(property="payment_result", type="object",
     *                 @OA\Property(property="response_status",  type="string", example="A",
     *                     description="A = Approved, D = Declined, E = Error, H = Hold, P = Pending, V = Voided"),
     *                 @OA\Property(property="response_code",    type="string", example="000"),
     *                 @OA\Property(property="response_message", type="string", example="Authorised")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Webhook processed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Payment confirmed. Booking status: confirmed")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Payment not found for this cart_id")
     * )
     */
    public function paymentCallback(Request $request)
    {
        $payload  = $request->all();
        $cartId   = $payload['cart_id'] ?? null;
        $tranRef  = $payload['tran_ref'] ?? null;
        $respCode = $payload['payment_result']['response_code'] ?? null;
        $respMsg  = $payload['payment_result']['response_message'] ?? null;
        $status   = $payload['payment_result']['response_status'] ?? null;

        Log::info('Trainer PayTabs callback', ['cart_id' => $cartId, 'status' => $status]);

        // cart_id format: "TRAINER_{booking_id}"
        $bookingId = str_replace('TRAINER_', '', $cartId);
        $booking   = TrainerBooking::with(['trainer', 'payment'])->find($bookingId);

        if (!$booking) {
            Log::error('Trainer PayTabs callback: booking not found', ['cart_id' => $cartId]);
            return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        }

        DB::beginTransaction();
        try {
            // Update payment record
            $booking->payment->update([
                'tran_ref'         => $tranRef,
                'cart_id'          => $cartId,
                'resp_code'        => $respCode,
                'resp_message'     => $respMsg,
                'paytabs_response' => $payload,
                'payment_status'   => $status === 'A' ? 'paid' : 'failed',
            ]);

            if ($status === 'A') {
                // Payment approved — confirm booking
                $booking->update([
                    'status'         => 'confirmed',
                    'payment_status' => 'paid',
                    'confirmed_at'   => now(),
                ]);

                // Calculate and record commission split
                $commissionPct = $booking->trainer->getEffectiveCommissionPercentage();
                $split         = PaymentSplit::calculate($booking->price, $commissionPct);

                $paymentSplit = PaymentSplit::create([
                    'payment_id'            => $booking->payment_id,
                    'booking_id'            => $booking->id,
                    'trainer_id'            => $booking->trainer_id,
                    'total_amount'          => $booking->price,
                    'commission_percentage' => $commissionPct,
                    'commission_amount'     => $split['commission_amount'],
                    'trainer_amount'        => $split['trainer_amount'],
                    'currency'              => $booking->payment->currency ?? 'SAR',
                    'status'                => 'pending',
                ]);

                // Create payout record (pending admin approval)
                TrainerPayout::create([
                    'trainer_id'       => $booking->trainer_id,
                    'payment_split_id' => $paymentSplit->id,
                    'amount'           => $split['trainer_amount'],
                    'currency'         => $booking->payment->currency ?? 'SAR',
                    'status'           => 'pending',
                ]);

                $booking->trainer->incrementTotalSessions();

                DB::commit();
                return response()->json(['success' => true, 'message' => 'Payment confirmed. Booking status: confirmed']);
            } else {
                // Payment declined
                $booking->update([
                    'status'         => 'cancelled',
                    'payment_status' => 'failed',
                    'cancelled_at'   => now(),
                ]);

                DB::commit();
                return response()->json(['success' => true, 'message' => 'Payment declined. Booking cancelled.']);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Trainer PayTabs callback processing failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Callback processing failed'], 500);
        }
    }

    // ---------------------------------------------------------------
    // AUTH — User bookings
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/trainer/bookings",
     *     summary="My session bookings (Client)",
     *     description="Returns the authenticated user's trainer session bookings with optional filters.",
     *     operationId="myTrainerBookings",
     *     tags={"Trainer Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status",    in="query", required=false, @OA\Schema(type="string", enum={"pending","confirmed","in_progress","completed","cancelled","rejected"})),
     *     @OA\Parameter(name="upcoming",  in="query", required=false, @OA\Schema(type="integer", enum={0,1}, example=1)),
     *     @OA\Parameter(name="per_page",  in="query", required=false, @OA\Schema(type="integer", example=10)),
     *     @OA\Response(
     *         response=200,
     *         description="Bookings retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",            type="integer", example=42),
     *                         @OA\Property(property="booking_date",  type="string",  format="date"),
     *                         @OA\Property(property="start_time",    type="string",  example="10:00"),
     *                         @OA\Property(property="end_time",      type="string",  example="12:00"),
     *                         @OA\Property(property="duration_hours",type="integer", example=2),
     *                         @OA\Property(property="session_type",  type="string",  example="beginner"),
     *                         @OA\Property(property="status",        type="string",  example="confirmed"),
     *                         @OA\Property(property="price",         type="number",  format="float", example=300.00),
     *                         @OA\Property(property="payment_status",type="string",  example="paid"),
     *                         @OA\Property(property="trainer", type="object",
     *                             @OA\Property(property="id",       type="integer"),
     *                             @OA\Property(property="name",     type="string"),
     *                             @OA\Property(property="photo_url",type="string")
     *                         ),
     *                         @OA\Property(property="location", type="object"),
     *                         @OA\Property(property="can_review", type="boolean", example=false)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    // ---------------------------------------------------------------
    // CLIENT — Initiate payment for an accepted booking
    // ---------------------------------------------------------------
    public function initiatePayment(int $id)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $booking = TrainerBooking::with(['trainer', 'payment', 'location.city'])->find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        }

        if ($booking->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (!in_array($booking->status, ['confirmed', 'accepted'])) {
            return response()->json(['success' => false, 'message' => 'Booking must be confirmed before payment'], 400);
        }

        if ($booking->payment_status === 'paid') {
            return response()->json(['success' => false, 'message' => 'Booking is already paid'], 400);
        }

        $paymentUrl = null;
        if (!config('app.skip_paytabs', false)) {
            $paymentUrl = $this->initiatePayTabsPayment(
                $booking->payment,
                $booking,
                $user,
                $booking->trainer,
                $booking->location
            );
        }

        return response()->json([
            'success'     => true,
            'message'     => 'Payment initiated.',
            'data'        => ['payment_url' => $paymentUrl],
        ]);
    }

    public function myBookings(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $query = TrainerBooking::with([
            'trainer:id,name,name_ar,photo',
            'location.city',
            'review:id,booking_id,rating',
        ])->where('user_id', $user->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }

        $bookings = $query->latest()->paginate($request->get('per_page', 10));

        $bookings->getCollection()->transform(function ($b) {
            $b->can_review = $b->canBeReviewed();
            $b->trainer->photo_url = $b->trainer->photo_url ?? null;
            return $b;
        });

        return response()->json(['success' => true, 'data' => $bookings, 'message' => 'Bookings retrieved successfully']);
    }

    // ---------------------------------------------------------------
    // AUTH — Cancel booking
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/trainer/bookings/{id}/cancel",
     *     summary="Cancel a booking",
     *     description="Cancel a pending or confirmed booking. Refund eligibility depends on cancellation policy (to be configured). Only the booking owner can cancel.",
     *     operationId="cancelTrainerBooking",
     *     tags={"Trainer Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=42)),
     *     @OA\Response(response=200, description="Booking cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Booking cancelled successfully")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Booking cannot be cancelled (wrong status)"),
     *     @OA\Response(response=403, description="Not your booking"),
     *     @OA\Response(response=404, description="Booking not found")
     * )
     */
    public function cancel(int $id)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $booking = TrainerBooking::find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        }

        if ($booking->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Not your booking'], 403);
        }

        if (!$booking->canBeCancelled()) {
            return response()->json(['success' => false, 'message' => 'This booking cannot be cancelled in its current status'], 400);
        }

        $booking->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);

        if ($booking->payment_status === 'paid' && $booking->payment && $booking->payment->tran_ref) {
            $this->processTrainerRefund($booking->payment, $booking->id);
        }

        return response()->json(['success' => true, 'message' => 'Booking cancelled successfully']);
    }

    // ---------------------------------------------------------------
    // PROVIDER — Provider bookings management
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/trainer/sessions",
     *     summary="My sessions (Trainer / Provider)",
     *     description="Returns all session bookings for the authenticated trainer.",
     *     operationId="myTrainerSessions",
     *     tags={"Trainer Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status",   in="query", required=false, @OA\Schema(type="string", enum={"pending","confirmed","in_progress","completed","cancelled","rejected"})),
     *     @OA\Parameter(name="upcoming", in="query", required=false, @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=10)),
     *     @OA\Response(response=200, description="Sessions retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="No trainer profile found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function mySessions(Request $request)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->first();

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $query = TrainerBooking::with(['user:id,first_name,last_name,profile_picture,phone', 'location.city'])
            ->where('trainer_id', $trainer->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }

        return response()->json([
            'success' => true,
            'data'    => $query->latest()->paginate($request->get('per_page', 10)),
            'message' => 'Sessions retrieved successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/trainer/sessions/{id}/start",
     *     summary="Mark session as In Progress",
     *     description="Trainer marks a confirmed session as in progress (session has started).",
     *     operationId="startTrainerSession",
     *     tags={"Trainer Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=42)),
     *     @OA\Response(response=200, description="Session started",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Session marked as in progress")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Session is not in confirmed status"),
     *     @OA\Response(response=403, description="Not your session")
     * )
     */

    // ---------------------------------------------------------------
    // TRAINER — Accept booking
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/trainer/sessions/{id}/accept",
     *     summary="Trainer accepts a pending booking",
     *     description="Trainer confirms a pending booking. Status changes from 'pending' to 'confirmed'. Client is notified.",
     *     operationId="trainerAcceptBooking",
     *     tags={"Trainer Sessions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Booking accepted"),
     *     @OA\Response(response=400, description="Booking is not pending"),
     *     @OA\Response(response=403, description="Not your booking"),
     *     @OA\Response(response=404, description="Booking not found")
     * )
     */
    /**
     * @deprecated Bookings are now auto-confirmed at creation. This endpoint is kept for backward compatibility.
     */
    public function acceptBooking(int $id)
    {
        return response()->json([
            'success' => false,
            'message' => 'This endpoint is deprecated. Bookings are now automatically confirmed when created. The client receives a payment URL immediately.',
            'code'    => 'ENDPOINT_DEPRECATED',
        ], 410);
    }

    // ---------------------------------------------------------------
    // TRAINER — Reject booking
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/trainer/sessions/{id}/reject",
     *     summary="Trainer rejects a pending booking",
     *     description="Trainer rejects a pending booking. Status changes to 'cancelled'. If the booking was paid, a PayTabs refund is triggered automatically. Client is notified with the reason.",
     *     operationId="trainerRejectBooking",
     *     tags={"Trainer Sessions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="Unavailable due to personal reasons.")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Booking rejected"),
     *     @OA\Response(response=400, description="Booking is not pending"),
     *     @OA\Response(response=403, description="Not your booking"),
     *     @OA\Response(response=404, description="Booking not found")
     * )
     */
    /**
     * @deprecated Bookings are now auto-confirmed at creation. Use POST /api/trainer/bookings/{id}/cancel instead.
     */
    public function rejectBooking(Request $request, int $id)
    {
        return response()->json([
            'success' => false,
            'message' => 'This endpoint is deprecated. Bookings are now automatically confirmed. To cancel a booking, use POST /api/trainer/bookings/{id}/cancel.',
            'code'    => 'ENDPOINT_DEPRECATED',
        ], 410);
    }

    public function startSession(int $id)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->first();
        $booking = TrainerBooking::find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Session not found'], 404);
        }

        if (!$trainer || $booking->trainer_id !== $trainer->id) {
            return response()->json(['success' => false, 'message' => 'Not your session'], 403);
        }

        if ($booking->status !== 'confirmed') {
            return response()->json(['success' => false, 'message' => 'Session must be confirmed before it can start'], 400);
        }

        $booking->update(['status' => 'in_progress']);

        return response()->json(['success' => true, 'message' => 'Session marked as in progress']);
    }

    /**
     * @OA\Post(
     *     path="/api/trainer/sessions/{id}/complete",
     *     summary="Mark session as Completed",
     *     description="Trainer marks a session as completed. This triggers the review request notification to the client.",
     *     operationId="completeTrainerSession",
     *     tags={"Trainer Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=42)),
     *     @OA\Response(response=200, description="Session completed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Session marked as completed")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Session is not in_progress status"),
     *     @OA\Response(response=403, description="Not your session")
     * )
     */
    public function completeSession(int $id)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->first();
        $booking = TrainerBooking::find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Session not found'], 404);
        }

        if (!$trainer || $booking->trainer_id !== $trainer->id) {
            return response()->json(['success' => false, 'message' => 'Not your session'], 403);
        }

        if ($booking->status !== 'in_progress') {
            return response()->json(['success' => false, 'message' => 'Session must be in progress before completing'], 400);
        }

        $booking->update(['status' => 'completed', 'completed_at' => now()]);

        try {
            $this->notifications->notifyTrainerSessionCompleted($booking->user, $booking, $trainer);
        } catch (\Exception $e) {
            Log::error('TrainerBookingController@completeSession notify failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Session marked as completed']);
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function processTrainerRefund(TrainerPayment $payment, int $bookingId): void
    {
        try {
            $config = PayTabsConfigService::getConfig();

            $response = Http::withHeaders([
                'authorization' => $config['server_key'],
                'content-type'  => 'application/json',
            ])->post(PayTabsConfigService::getBaseUrl() . '/payment/request', [
                'profile_id'       => $config['profile_id'],
                'tran_type'        => 'refund',
                'tran_class'       => 'ecom',
                'cart_id'          => $payment->cart_id,
                'cart_currency'    => $payment->currency ?? $config['currency'] ?? 'SAR',
                'cart_amount'      => (float) $payment->amount,
                'cart_description' => 'Refund — trainer booking #' . $bookingId,
                'tran_ref'         => $payment->tran_ref,
            ]);

            if ($response->successful()) {
                $payment->update(['payment_status' => 'refunded']);
                TrainerBooking::where('id', $bookingId)->update(['payment_status' => 'refunded']);
                Log::info("Trainer booking #{$bookingId} refunded via PayTabs. tran_ref: {$payment->tran_ref}");
            } else {
                Log::error("PayTabs refund failed for trainer booking #{$bookingId}: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("PayTabs refund exception for trainer booking #{$bookingId}: " . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------
    // BROWSER RETURN — after PayTabs payment page
    // ---------------------------------------------------------------
    public function paymentReturn(Request $request)
    {
        $cartId    = $request->get('cart_id', '');
        $bookingId = str_replace('TRAINER_', '', $cartId);
        $tranRef   = $request->get('tran_ref', '');
        $frontend  = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:4200')), '/');

        if ($bookingId && $tranRef) {
            return redirect($frontend . '/trainers/booking-confirmation?booking_id=' . $bookingId . '&status=success');
        }

        return redirect($frontend . '/trainers/booking-confirmation?status=failed');
    }

    private function initiatePayTabsPayment(TrainerPayment $payment, TrainerBooking $booking, $user, Trainer $trainer, TrainerLocation $location): string
    {
        $config = PayTabsConfigService::getConfig();

        $payload = [
            'profile_id'   => $config['profile_id'],
            'tran_type'    => 'sale',
            'tran_class'   => 'ecom',
            'cart_id'      => 'TRAINER_' . $booking->id,
            'cart_currency'=> $config['currency'] ?? 'SAR',
            'cart_amount'  => (float) $payment->amount,
            'cart_description' => "DabApp — Training session with {$trainer->name} on {$booking->booking_date->format('Y-m-d')} at {$booking->start_time}",
            'return'       => config('app.url') . '/api/trainer/payments/return',
            'callback'     => config('app.url') . '/api/trainer/payments/callback',
            'customer_details' => [
                'name'   => $user->first_name . ' ' . $user->last_name,
                'email'  => $user->email,
                'phone'  => $user->phone ?? '',
                'street1'=> $location->location_name,
                'city'   => $location->city->name ?? 'Unknown',
                'country'=> 'SA',
            ],
        ];

        $response = Http::withHeaders([
            'authorization' => $config['server_key'],
            'content-type'  => 'application/json',
        ])->post(PayTabsConfigService::getBaseUrl() . '/payment/request', $payload);

        if (!$response->successful()) {
            throw new \Exception('PayTabs API error: ' . $response->body());
        }

        $data = $response->json();

        if (empty($data['redirect_url'])) {
            throw new \Exception('PayTabs did not return a redirect URL');
        }

        // Update payment with cart_id
        $payment->update(['cart_id' => 'TRAINER_' . $booking->id]);

        return $data['redirect_url'];
    }

    private function generateSlots(string $start, string $end, int $durationMinutes = 120): array
    {
        $slots   = [];
        $current = Carbon::createFromFormat('H:i', substr($start, 0, 5));
        $endTime = Carbon::createFromFormat('H:i', substr($end,   0, 5));

        while ($current->copy()->addMinutes($durationMinutes)->lte($endTime)) {
            $slotEnd = $current->copy()->addMinutes($durationMinutes);
            $slots[] = $current->format('H:i') . '-' . $slotEnd->format('H:i');
            $current->addMinutes($durationMinutes);
        }

        return $slots;
    }
}
