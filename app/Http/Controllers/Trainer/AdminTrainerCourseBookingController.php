<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\TrainerCourseBooking;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Admin - Trainer Course Bookings",
 *     description="Admin read-only oversight of trainee course bookings, with a force-cancel escape hatch"
 * )
 */
class AdminTrainerCourseBookingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/trainer-course-bookings",
     *     summary="List all trainer course bookings (Admin)",
     *     description="Returns all course bookings with filters: status, trainer, course, user, payment status, date range (matched against any of the booking's session dates).",
     *     operationId="adminListTrainerCourseBookings",
     *     tags={"Admin - Trainer Course Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status",         in="query", required=false, @OA\Schema(type="string", enum={"pending","confirmed","in_progress","completed","cancelled"})),
     *     @OA\Parameter(name="trainer_id",     in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="course_id",      in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="user_id",        in="query", required=false, @OA\Schema(type="integer", example=5)),
     *     @OA\Parameter(name="payment_status", in="query", required=false, @OA\Schema(type="string", enum={"pending","paid","failed","refunded"})),
     *     @OA\Parameter(name="date_from",      in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to",        in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="per_page",       in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Course bookings retrieved")
     * )
     */
    public function index(Request $request)
    {
        $query = TrainerCourseBooking::with([
            'course:id,title,title_ar,price_type,original_price,promo_price,hours_per_session,total_sessions',
            'trainer:id,name,name_ar',
            'user:id,first_name,last_name,email,phone',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('trainer_id')) {
            $query->where('trainer_id', $request->trainer_id);
        }
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('date_from')) {
            $query->whereHas('sessions', fn ($q) => $q->whereDate('booking_date', '>=', $request->date_from));
        }
        if ($request->filled('date_to')) {
            $query->whereHas('sessions', fn ($q) => $q->whereDate('booking_date', '<=', $request->date_to));
        }

        $bookings = $query->latest()->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => [
                'data'    => $bookings,
                'summary' => [
                    'total'    => TrainerCourseBooking::count(),
                    'filtered' => $bookings->total(),
                ],
            ],
            'message' => 'Course bookings retrieved successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/trainer-course-bookings/{id}",
     *     summary="Get course booking details (Admin)",
     *     description="Returns full details of a course booking including every scheduled session (dates/times/status — never the OTP codes), payment split and review if any.",
     *     operationId="adminShowTrainerCourseBooking",
     *     tags={"Admin - Trainer Course Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Course booking details"),
     *     @OA\Response(response=404, description="Course booking not found")
     * )
     */
    public function show(int $id)
    {
        $booking = TrainerCourseBooking::with([
            'course:id,title,title_ar,price_type,original_price,promo_price,hours_per_session,total_sessions',
            'trainer:id,name,name_ar,specialty,rating_average,photo',
            'user:id,first_name,last_name,email,phone',
            'sessions',
            'paymentSplit',
            'review:id,course_booking_id,rating,comment,is_approved,created_at',
        ])->find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Course booking not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $booking,
            'message' => 'Course booking details retrieved',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/trainer-course-bookings/{id}/cancel",
     *     summary="Force-cancel a course booking (Admin)",
     *     description="Admin force-cancel of any active course booking, cascading to every non-completed session. Requires a reason.",
     *     operationId="adminCancelTrainerCourseBooking",
     *     tags={"Admin - Trainer Course Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(required={"reason"}, @OA\Property(property="reason", type="string", example="Trainer suspended due to policy violation"))
     *     ),
     *     @OA\Response(response=200, description="Course booking cancelled"),
     *     @OA\Response(response=400, description="Course booking cannot be cancelled (already completed or cancelled)"),
     *     @OA\Response(response=404, description="Course booking not found"),
     *     @OA\Response(response=422, description="Validation error — reason is required")
     * )
     */
    public function cancel(Request $request, int $id)
    {
        $booking = TrainerCourseBooking::find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Course booking not found'], 404);
        }

        if (in_array($booking->status, ['completed', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => "Course booking cannot be cancelled — current status: {$booking->status}",
            ], 400);
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $booking->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'notes'        => '[ADMIN CANCEL] ' . $request->reason,
        ]);
        $booking->sessions()->whereNotIn('status', ['completed'])->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Course booking cancelled by admin',
        ]);
    }
}
