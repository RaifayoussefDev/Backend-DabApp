<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\PaymentSplit;
use App\Models\Trainer;
use App\Models\TrainerBooking;
use App\Models\TrainerCourse;
use App\Models\TrainerCourseBooking;
use App\Models\TrainerCourseBookingSession;
use App\Models\TrainerLocation;
use App\Models\TrainerPayment;
use App\Models\TrainerPayout;
use App\Models\TrainerReview;
use App\Services\NotificationService;
use App\Services\PayTabsConfigService;
use App\Traits\GeneratesTrainerSlots;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Trainer Course Bookings",
 *     description="Trainee books all sessions of a multi-session trainer course under one payment; trainer starts/completes each dated session using the trainee's Start/Completion OTP"
 * )
 */
class TrainerCourseBookingController extends Controller
{
    use GeneratesTrainerSlots;

    protected NotificationService $notifications;

    public function __construct(NotificationService $notifications)
    {
        $this->notifications = $notifications;
    }

    // ---------------------------------------------------------------
    // PUBLIC — Availability for a specific course
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/trainers/{id}/courses/{courseId}/availability",
     *     summary="Available session slots for a course",
     *     description="Returns available date/time slots (sized to the course's hours_per_session) for the given date range, excluding the trainer's existing flat bookings and course-session bookings.",
     *     operationId="getTrainerCourseAvailability",
     *     tags={"Trainer Course Bookings"},
     *     @OA\Parameter(name="id",        in="path",  required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="courseId",  in="path",  required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="from_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to_date",   in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Availability retrieved"),
     *     @OA\Response(response=404, description="Course not found")
     * )
     */
    public function availability(Request $request, int $trainerId, int $courseId)
    {
        $course = TrainerCourse::with('trainer.schedules')
            ->where('id', $courseId)
            ->where('trainer_id', $trainerId)
            ->where('status', 'published')
            ->where('is_active', true)
            ->first();

        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course not found'], 404);
        }

        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
        ]);

        $result = $this->computeAvailableSlots(
            $course->trainer,
            $validated['from_date'],
            $validated['to_date'],
            $course->hours_per_session * 60
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'course'          => ['id' => $course->id, 'title' => $course->title, 'hours_per_session' => $course->hours_per_session, 'total_sessions' => $course->total_sessions],
                'available_slots' => $result['slots'],
                'schedule_source' => $result['schedule_source'],
                'period'          => ['from' => $validated['from_date'], 'to' => $validated['to_date']],
            ],
            'message' => 'Availability retrieved successfully',
        ]);
    }

    // ---------------------------------------------------------------
    // AUTH — Book all sessions of a course
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/trainers/{id}/courses/{courseId}/book",
     *     summary="Book all sessions of a course",
     *     description="Schedules a date/time for every session of the course (must equal total_sessions, each on a distinct day) and initiates one PayTabs payment for the whole course.",
     *     operationId="bookTrainerCourse",
     *     tags={"Trainer Course Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id",       in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="courseId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"sessions"},
     *             @OA\Property(property="sessions", type="array",
     *                 @OA\Items(
     *                     required={"session_number","booking_date","start_time"},
     *                     @OA\Property(property="session_number", type="integer", example=1),
     *                     @OA\Property(property="booking_date",   type="string", format="date", example="2026-07-20"),
     *                     @OA\Property(property="start_time",     type="string", example="10:00")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Course booked — payment URL returned"),
     *     @OA\Response(response=400, description="Course not available for booking"),
     *     @OA\Response(response=404, description="Course not found"),
     *     @OA\Response(response=422, description="Validation error — wrong session count/numbers, duplicate dates, or slot unavailable")
     * )
     */
    public function book(Request $request, int $trainerId, int $courseId)
    {
        $user   = JWTAuth::parseToken()->authenticate();
        $course = TrainerCourse::with(['trainer.schedules', 'location', 'sessions'])
            ->where('id', $courseId)
            ->where('trainer_id', $trainerId)
            ->where('status', 'published')
            ->where('is_active', true)
            ->first();

        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course not found or not published'], 404);
        }

        $trainer = $course->trainer;

        if (!$trainer->is_available) {
            return response()->json(['success' => false, 'message' => 'Trainer is not currently available'], 400);
        }

        $validated = $request->validate([
            'sessions'                  => 'required|array',
            'sessions.*.session_number' => 'required|integer|min:1',
            'sessions.*.booking_date'   => 'required|date|after_or_equal:today',
            'sessions.*.start_time'     => 'required|date_format:H:i',
        ]);

        $requested = $validated['sessions'];

        if (count($requested) !== $course->total_sessions) {
            return response()->json(['success' => false, 'message' => "This course requires exactly {$course->total_sessions} session(s) to be scheduled."], 422);
        }

        $sessionNumbers = array_column($requested, 'session_number');
        sort($sessionNumbers);
        if ($sessionNumbers !== range(1, $course->total_sessions)) {
            return response()->json(['success' => false, 'message' => "session_number values must be exactly 1.." . $course->total_sessions . ', each appearing once.'], 422);
        }

        $dates = array_column($requested, 'booking_date');
        if (count(array_unique($dates)) !== count($dates)) {
            return response()->json(['success' => false, 'message' => 'Each session of this course must be booked on a different day.'], 422);
        }

        $durationMinutes = $course->hours_per_session * 60;
        $descriptiveSessions = $course->sessions->keyBy('session_number');

        foreach ($requested as $s) {
            if ($course->start_date && $s['booking_date'] < $course->start_date->format('Y-m-d')) {
                return response()->json(['success' => false, 'message' => "Booking date must be on or after the course's start date"], 422);
            }

            $result    = $this->computeAvailableSlots($trainer, $s['booking_date'], $s['booking_date'], $durationMinutes);
            $daySlots  = collect($result['slots'])->firstWhere('date', $s['booking_date'])['time_slots'] ?? [];
            $endTime   = date('H:i', strtotime($s['start_time'] . " +{$course->hours_per_session} hours"));
            $requestedSlot = $s['start_time'] . '-' . $endTime;

            if (!in_array($requestedSlot, $daySlots, true)) {
                return response()->json(['success' => false, 'message' => "The requested slot for session {$s['session_number']} ({$s['booking_date']} {$s['start_time']}) is not available."], 422);
            }
        }

        $totalPrice = (float) $course->total_price;

        DB::beginTransaction();
        try {
            $payment = TrainerPayment::create([
                'user_id'        => $user->id,
                'amount'         => $totalPrice,
                'payment_status' => 'pending',
            ]);

            $courseBooking = TrainerCourseBooking::create([
                'course_id'      => $course->id,
                'trainer_id'     => $trainer->id,
                'user_id'        => $user->id,
                'status'         => 'confirmed',
                'total_price'    => $totalPrice,
                'payment_id'     => $payment->id,
                'payment_status' => 'pending',
                'confirmed_at'   => now(),
            ]);

            foreach ($requested as $s) {
                $endTime = date('H:i', strtotime($s['start_time'] . " +{$course->hours_per_session} hours"));

                $session = new TrainerCourseBookingSession([
                    'course_booking_id' => $courseBooking->id,
                    'trainer_id'        => $trainer->id,
                    'course_session_id' => $descriptiveSessions->get($s['session_number'])?->id,
                    'session_number'    => $s['session_number'],
                    'booking_date'      => $s['booking_date'],
                    'start_time'        => $s['start_time'],
                    'end_time'          => $endTime,
                    'status'            => 'scheduled',
                ]);
                $session->generateOtps();
                $session->save();
            }

            $paymentUrl = null;
            if (!config('app.skip_paytabs', false)) {
                $paymentUrl = $this->initiatePayTabsPayment($payment, $courseBooking, $user, $trainer, $course->location, $course);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('TrainerCourseBooking: booking creation failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create course booking', 'error' => $e->getMessage()], 500);
        }

        $sessions = $courseBooking->sessions()->get();
        $sessions->each->makeVisible(['start_otp', 'completion_otp']);

        return response()->json([
            'success' => true,
            'message' => 'Course booked. Please complete payment to secure your sessions.',
            'data'    => [
                'course_booking_id' => $courseBooking->id,
                'status'            => $courseBooking->status,
                'payment_url'       => $paymentUrl,
                'total_price'       => $totalPrice,
                'sessions'          => $sessions,
            ],
        ], 201);
    }

    // ---------------------------------------------------------------
    // WEBHOOK — PayTabs callback
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/trainer/course-bookings/payments/callback",
     *     summary="PayTabs payment webhook (course bookings)",
     *     description="Called by PayTabs after payment is processed for a course booking. Server-to-server — do not call from the mobile app.",
     *     operationId="trainerCourseBookingPaymentCallback",
     *     tags={"Trainer Course Bookings"},
     *     @OA\Response(response=200, description="Webhook processed"),
     *     @OA\Response(response=404, description="Course booking not found for this cart_id")
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

        Log::info('Trainer course booking PayTabs callback', ['cart_id' => $cartId, 'status' => $status]);

        // cart_id format: "TRAINERCOURSE_{course_booking_id}"
        $courseBookingId = str_replace('TRAINERCOURSE_', '', $cartId);
        $courseBooking   = TrainerCourseBooking::with(['trainer.user', 'payment', 'course', 'sessions'])->find($courseBookingId);

        if (!$courseBooking) {
            Log::error('Trainer course booking PayTabs callback: booking not found', ['cart_id' => $cartId]);
            return response()->json(['success' => false, 'message' => 'Course booking not found'], 404);
        }

        DB::beginTransaction();
        try {
            $courseBooking->payment->update([
                'tran_ref'         => $tranRef,
                'cart_id'          => $cartId,
                'resp_code'        => $respCode,
                'resp_message'     => $respMsg,
                'paytabs_response' => $payload,
                'payment_status'   => $status === 'A' ? 'paid' : 'failed',
            ]);

            if ($status === 'A') {
                $courseBooking->update([
                    'status'         => 'confirmed',
                    'payment_status' => 'paid',
                    'confirmed_at'   => now(),
                ]);

                $commissionPct = $courseBooking->trainer->getEffectiveCommissionPercentage();
                $split         = PaymentSplit::calculate((float) $courseBooking->total_price, $commissionPct);

                $paymentSplit = PaymentSplit::create([
                    'payment_id'            => $courseBooking->payment_id,
                    'course_booking_id'     => $courseBooking->id,
                    'trainer_id'            => $courseBooking->trainer_id,
                    'total_amount'          => $courseBooking->total_price,
                    'commission_percentage' => $commissionPct,
                    'commission_amount'     => $split['commission_amount'],
                    'trainer_amount'        => $split['trainer_amount'],
                    'currency'              => $courseBooking->payment->currency ?? 'SAR',
                    'status'                => 'pending',
                ]);

                TrainerPayout::create([
                    'trainer_id'       => $courseBooking->trainer_id,
                    'payment_split_id' => $paymentSplit->id,
                    'amount'           => $split['trainer_amount'],
                    'currency'         => $courseBooking->payment->currency ?? 'SAR',
                    'status'           => 'pending',
                ]);

                DB::commit();

                try {
                    $this->notifications->notifyTrainerNewCourseBooking($courseBooking->trainer->user, $courseBooking);
                } catch (\Exception $e) {
                    Log::error('TrainerCourseBookingController@paymentCallback notify failed: ' . $e->getMessage());
                }

                return response()->json(['success' => true, 'message' => 'Payment confirmed. Course booking status: confirmed']);
            }

            $courseBooking->update([
                'status'         => 'cancelled',
                'payment_status' => 'failed',
                'cancelled_at'   => now(),
            ]);
            $courseBooking->sessions()->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Payment declined. Course booking cancelled.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Trainer course booking PayTabs callback processing failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Callback processing failed'], 500);
        }
    }

    public function paymentReturn(Request $request)
    {
        $cartId          = $request->get('cart_id', '');
        $courseBookingId = str_replace('TRAINERCOURSE_', '', $cartId);
        $tranRef         = $request->get('tran_ref', '');
        $frontend        = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:4200')), '/');

        if ($courseBookingId && $tranRef) {
            return redirect($frontend . '/trainers/booking-confirmation?course_booking_id=' . $courseBookingId . '&status=success');
        }

        return redirect($frontend . '/trainers/booking-confirmation?status=failed');
    }

    // ---------------------------------------------------------------
    // AUTH — Trainee: initiate payment / my course bookings / cancel / review
    // ---------------------------------------------------------------

    public function initiatePayment(int $id)
    {
        $user          = JWTAuth::parseToken()->authenticate();
        $courseBooking = TrainerCourseBooking::with(['trainer', 'payment', 'course.location.city'])->find($id);

        if (!$courseBooking) {
            return response()->json(['success' => false, 'message' => 'Course booking not found'], 404);
        }
        if ($courseBooking->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        if ($courseBooking->payment_status === 'paid') {
            return response()->json(['success' => false, 'message' => 'Course booking is already paid'], 400);
        }

        $paymentUrl = null;
        if (!config('app.skip_paytabs', false)) {
            $paymentUrl = $this->initiatePayTabsPayment(
                $courseBooking->payment,
                $courseBooking,
                $user,
                $courseBooking->trainer,
                $courseBooking->course->location,
                $courseBooking->course
            );
        }

        return response()->json(['success' => true, 'message' => 'Payment initiated.', 'data' => ['payment_url' => $paymentUrl]]);
    }

    /**
     * @OA\Get(
     *     path="/api/trainer/course-bookings",
     *     summary="My course bookings (Trainee)",
     *     description="Returns the authenticated trainee's course bookings grouped into in_progress and completed.",
     *     operationId="myTrainerCourseBookings",
     *     tags={"Trainer Course Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Course bookings retrieved")
     * )
     */
    public function myCourseBookings(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $bookings = TrainerCourseBooking::with([
                'course:id,title,title_ar,image,price_type,original_price,promo_price,hours_per_session,total_sessions',
                'trainer:id,name,name_ar,photo',
                'sessions',
            ])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $bookings->each(function ($b) {
            $b->can_review = $b->canBeReviewed();
            $b->sessions->each->makeVisible(['start_otp', 'completion_otp']);
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'in_progress' => $bookings->whereIn('status', ['pending', 'confirmed', 'in_progress'])->values(),
                'completed'   => $bookings->where('status', 'completed')->values(),
            ],
            'message' => 'Course bookings retrieved successfully',
        ]);
    }

    public function showCourseBooking(int $id)
    {
        $user          = JWTAuth::parseToken()->authenticate();
        $courseBooking = TrainerCourseBooking::with([
                'course.level', 'course.location.city',
                'trainer:id,name,name_ar,photo,rating_average,experience_years',
                'sessions',
                'review:id,course_booking_id,rating,comment,created_at',
            ])
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$courseBooking) {
            return response()->json(['success' => false, 'message' => 'Course booking not found'], 404);
        }

        $courseBooking->can_review = $courseBooking->canBeReviewed();
        $courseBooking->sessions->each->makeVisible(['start_otp', 'completion_otp']);

        return response()->json(['success' => true, 'data' => $courseBooking, 'message' => 'Course booking retrieved successfully']);
    }

    public function cancel(int $id)
    {
        $user          = JWTAuth::parseToken()->authenticate();
        $courseBooking = TrainerCourseBooking::find($id);

        if (!$courseBooking) {
            return response()->json(['success' => false, 'message' => 'Course booking not found'], 404);
        }
        if ($courseBooking->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Not your course booking'], 403);
        }
        if (!$courseBooking->canBeCancelled()) {
            return response()->json(['success' => false, 'message' => 'This course booking cannot be cancelled in its current status'], 400);
        }

        $courseBooking->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $courseBooking->sessions()->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        if ($courseBooking->payment_status === 'paid' && $courseBooking->payment && $courseBooking->payment->tran_ref) {
            $this->processTrainerRefund($courseBooking->payment, $courseBooking->id);
        }

        return response()->json(['success' => true, 'message' => 'Course booking cancelled successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/trainer/course-bookings/{id}/review",
     *     summary="Submit a review for a completed course",
     *     operationId="submitTrainerCourseReview",
     *     tags={"Trainer Course Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rating"},
     *             @OA\Property(property="rating",  type="integer", minimum=1, maximum=5, example=5),
     *             @OA\Property(property="comment", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Review submitted — pending moderation"),
     *     @OA\Response(response=400, description="Course not completed or already reviewed"),
     *     @OA\Response(response=403, description="Not your course booking")
     * )
     */
    public function review(Request $request, int $id)
    {
        $user          = JWTAuth::parseToken()->authenticate();
        $courseBooking = TrainerCourseBooking::find($id);

        if (!$courseBooking) {
            return response()->json(['success' => false, 'message' => 'Course booking not found'], 404);
        }
        if ($courseBooking->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Not your course booking'], 403);
        }
        if (!$courseBooking->canBeReviewed()) {
            return response()->json(['success' => false, 'message' => 'Course is not completed or already reviewed'], 400);
        }

        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        TrainerReview::create([
            'course_booking_id' => $courseBooking->id,
            'trainer_id'        => $courseBooking->trainer_id,
            'user_id'           => $user->id,
            'rating'            => $validated['rating'],
            'comment'           => $validated['comment'] ?? null,
            'is_approved'       => false,
        ]);

        return response()->json(['success' => true, 'message' => 'Review submitted and pending moderation'], 201);
    }

    // ---------------------------------------------------------------
    // AUTH — Trainer: calendar, start/complete sessions
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/trainer/course-sessions",
     *     summary="My course sessions calendar (Trainer)",
     *     description="Returns the authenticated trainer's booked course sessions. OTPs are never exposed here.",
     *     operationId="myTrainerCourseSessions",
     *     tags={"Trainer Course Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="from_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to_date",   in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="status",    in="query", required=false, @OA\Schema(type="string", enum={"scheduled","in_progress","completed","cancelled"})),
     *     @OA\Response(response=200, description="Sessions retrieved"),
     *     @OA\Response(response=403, description="No trainer profile found")
     * )
     */
    public function mySessions(Request $request)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->first();

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $query = TrainerCourseBookingSession::with([
                'courseBooking.user:id,first_name,last_name,profile_picture,phone',
                'courseBooking.course:id,title,title_ar,price_type,original_price,promo_price,hours_per_session,total_sessions',
            ])
            ->where('trainer_id', $trainer->id);

        if ($request->filled('from_date')) {
            $query->where('booking_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->where('booking_date', '<=', $request->to_date);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->orderBy('booking_date')->orderBy('start_time')->paginate($request->get('per_page', 20)),
            'message' => 'Course sessions retrieved successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/trainer/my-course-bookings",
     *     summary="My course bookings (Trainer)",
     *     description="Returns the authenticated trainer's course bookings — one row per trainee reservation (course + trainee + total price), grouped into in_progress and completed. A booking stays in in_progress until every session is completed and the course is marked completed. OTPs are never exposed here.",
     *     operationId="myTrainerCourseBookingsAsTrainer",
     *     tags={"Trainer Course Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Course bookings retrieved"),
     *     @OA\Response(response=403, description="No trainer profile found")
     * )
     */
    public function myTrainerCourseBookings(Request $request)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->first();

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $bookings = TrainerCourseBooking::with([
                'course:id,title,title_ar,image,price_type,original_price,promo_price,hours_per_session,total_sessions',
                'user:id,first_name,last_name,profile_picture,phone',
                'sessions',
            ])
            ->where('trainer_id', $trainer->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'in_progress' => $bookings->whereIn('status', ['pending', 'confirmed', 'in_progress'])->values(),
                'completed'   => $bookings->where('status', 'completed')->values(),
            ],
            'message' => 'Course bookings retrieved successfully',
        ]);
    }

    public function showSession(int $id)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->first();

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $session = TrainerCourseBookingSession::with([
                'courseBooking.user:id,first_name,last_name,profile_picture,phone,email',
                'courseBooking.course:id,title,title_ar,price_type,original_price,promo_price,hours_per_session,total_sessions,location_id',
                'courseBooking.course.location.city',
            ])
            ->where('id', $id)
            ->where('trainer_id', $trainer->id)
            ->first();

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $session, 'message' => 'Session retrieved successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/trainer/course-sessions/{id}/start",
     *     summary="Start a course session (OTP-gated)",
     *     description="Trainer enters the Start OTP the trainee showed them in person.",
     *     operationId="startTrainerCourseSession",
     *     tags={"Trainer Course Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"otp"}, @OA\Property(property="otp", type="string", example="482913"))),
     *     @OA\Response(response=200, description="Session started"),
     *     @OA\Response(response=400, description="Session not in a startable state / booking not paid"),
     *     @OA\Response(response=403, description="Not your session"),
     *     @OA\Response(response=422, description="Invalid code")
     * )
     */
    public function startSession(Request $request, int $id)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->first();
        $session = TrainerCourseBookingSession::with('courseBooking')->find($id);

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session not found'], 404);
        }
        if (!$trainer || $session->trainer_id !== $trainer->id) {
            return response()->json(['success' => false, 'message' => 'Not your session'], 403);
        }
        if ($session->courseBooking->payment_status !== 'paid') {
            return response()->json(['success' => false, 'message' => 'Course booking must be paid before a session can start'], 400);
        }
        if ($session->status !== 'scheduled') {
            return response()->json(['success' => false, 'message' => 'Session must be scheduled before it can start'], 400);
        }

        $validated = $request->validate(['otp' => 'required|string']);

        if (!$session->verifyStartOtp($validated['otp'])) {
            return response()->json(['success' => false, 'message' => 'Invalid code'], 422);
        }

        $session->update(['status' => 'in_progress', 'started_at' => now()]);

        if ($session->courseBooking->status === 'confirmed') {
            $session->courseBooking->update(['status' => 'in_progress']);
        }

        return response()->json(['success' => true, 'message' => 'Session marked as in progress']);
    }

    /**
     * @OA\Post(
     *     path="/api/trainer/course-sessions/{id}/complete",
     *     summary="Complete a course session (OTP-gated)",
     *     description="Trainer enters the Completion OTP the trainee showed them in person. Marks the session completed immediately. When it's the last remaining session, the whole course booking is marked completed and the trainee is notified to leave a review.",
     *     operationId="completeTrainerCourseSession",
     *     tags={"Trainer Course Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"otp"}, @OA\Property(property="otp", type="string", example="317025"))),
     *     @OA\Response(response=200, description="Session completed"),
     *     @OA\Response(response=400, description="Session not in progress"),
     *     @OA\Response(response=403, description="Not your session"),
     *     @OA\Response(response=422, description="Invalid code")
     * )
     */
    public function completeSession(Request $request, int $id)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->first();
        $session = TrainerCourseBookingSession::with('courseBooking.user')->find($id);

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session not found'], 404);
        }
        if (!$trainer || $session->trainer_id !== $trainer->id) {
            return response()->json(['success' => false, 'message' => 'Not your session'], 403);
        }
        if ($session->status !== 'in_progress') {
            return response()->json(['success' => false, 'message' => 'Session must be in progress before completing'], 400);
        }

        $validated = $request->validate(['otp' => 'required|string']);

        if (!$session->verifyCompletionOtp($validated['otp'])) {
            return response()->json(['success' => false, 'message' => 'Invalid code'], 422);
        }

        $session->update(['status' => 'completed', 'completed_at' => now()]);
        $trainer->incrementTotalSessions();

        $courseBooking = $session->courseBooking;
        $remaining = $courseBooking->sessions()->whereNotIn('status', ['completed', 'cancelled'])->exists();

        if (!$remaining) {
            $courseBooking->update(['status' => 'completed', 'completed_at' => now()]);

            try {
                $this->notifications->notifyTraineeCourseCompleted($courseBooking->user, $courseBooking);
            } catch (\Exception $e) {
                Log::error('TrainerCourseBookingController@completeSession notify failed: ' . $e->getMessage());
            }
        }

        return response()->json(['success' => true, 'message' => 'Session marked as completed']);
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    /**
     * Builds available date/time slots for a trainer over a date range, filtering out
     * conflicts from both flat bookings (trainer_bookings) and course-session bookings.
     */
    private function computeAvailableSlots(Trainer $trainer, string $fromDate, string $toDate, int $durationMinutes): array
    {
        $scheduleMap    = [];
        $scheduleSource = 'default';

        $schedules = $trainer->relationLoaded('schedules') ? $trainer->schedules : $trainer->schedules()->get();

        if ($schedules->isNotEmpty()) {
            $scheduleSource = 'configured';
            foreach ($schedules->where('is_available', true) as $s) {
                $scheduleMap[$s->day_of_week] = [substr($s->start_time, 0, 5), substr($s->end_time, 0, 5)];
            }
        }

        $flatBooked = TrainerBooking::where('trainer_id', $trainer->id)
            ->whereBetween('booking_date', [$fromDate, $toDate])
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->select('booking_date', 'start_time', 'end_time')
            ->get();

        $courseBooked = TrainerCourseBookingSession::where('trainer_id', $trainer->id)
            ->whereBetween('booking_date', [$fromDate, $toDate])
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->select('booking_date', 'start_time', 'end_time')
            ->get();

        $bookedSlots = $flatBooked->concat($courseBooked);

        $availableSlots = [];
        $current = Carbon::parse($fromDate);
        $end     = Carbon::parse($toDate);

        while ($current <= $end) {
            $dateStr   = $current->format('Y-m-d');
            $dayOfWeek = (int) $current->dayOfWeek;

            if ($scheduleSource === 'configured') {
                if (!isset($scheduleMap[$dayOfWeek])) {
                    $current->addDay();
                    continue;
                }
                [$dayStart, $dayEnd] = $scheduleMap[$dayOfWeek];
                $slots = $this->generateSlots($dayStart, $dayEnd, $durationMinutes);
            } else {
                $slots = $this->generateSlots('08:00', '19:00', $durationMinutes);
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

        return ['slots' => $availableSlots, 'schedule_source' => $scheduleSource];
    }

    private function processTrainerRefund(TrainerPayment $payment, int $courseBookingId): void
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
                'cart_description' => 'Refund — trainer course booking #' . $courseBookingId,
                'tran_ref'         => $payment->tran_ref,
            ]);

            if ($response->successful()) {
                $payment->update(['payment_status' => 'refunded']);
                TrainerCourseBooking::where('id', $courseBookingId)->update(['payment_status' => 'refunded']);
                Log::info("Trainer course booking #{$courseBookingId} refunded via PayTabs. tran_ref: {$payment->tran_ref}");
            } else {
                Log::error("PayTabs refund failed for trainer course booking #{$courseBookingId}: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("PayTabs refund exception for trainer course booking #{$courseBookingId}: " . $e->getMessage());
        }
    }

    private function initiatePayTabsPayment(TrainerPayment $payment, TrainerCourseBooking $courseBooking, $user, Trainer $trainer, ?TrainerLocation $location, TrainerCourse $course): string
    {
        $config = PayTabsConfigService::getConfig();

        $payload = [
            'profile_id'   => $config['profile_id'],
            'tran_type'    => 'sale',
            'tran_class'   => 'ecom',
            'cart_id'      => 'TRAINERCOURSE_' . $courseBooking->id,
            'cart_currency'=> $config['currency'] ?? 'SAR',
            'cart_amount'  => (float) $payment->amount,
            'cart_description' => "DabApp — Course \"{$course->title}\" with {$trainer->name} ({$course->total_sessions} session(s))",
            'return'       => rtrim(env('FRONTEND_URL', 'http://localhost:4200'), '/') . '/trainers/booking-confirmation?course_booking_id=' . $courseBooking->id . '&status=success',
            'callback'     => rtrim(config('app.url'), '/') . '/api/trainer/course-bookings/payments/callback',
            'customer_details' => [
                'name'   => $user->first_name . ' ' . $user->last_name,
                'email'  => $user->email,
                'phone'  => $user->phone ?? '',
                'street1'=> $location?->location_name ?? 'N/A',
                'city'   => $location?->city->name ?? 'Unknown',
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

        $payment->update(['cart_id' => 'TRAINERCOURSE_' . $courseBooking->id]);

        return $data['redirect_url'];
    }
}
