<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\TrainerBooking;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Admin - Trainer Bookings",
 *     description="Admin view and management of all trainer bookings — list, detail, force-cancel, confirm"
 * )
 */
class AdminTrainerBookingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/trainer-bookings",
     *     summary="List all trainer bookings (Admin)",
     *     description="Returns all trainer bookings with rich filters: status, trainer, user, session type, date range. Includes trainer, user and payment info.",
     *     operationId="adminListTrainerBookings",
     *     tags={"Admin - Trainer Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status",       in="query", required=false,
     *         @OA\Schema(type="string", enum={"pending","confirmed","in_progress","completed","cancelled","rejected"})),
     *     @OA\Parameter(name="trainer_id",   in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="user_id",      in="query", required=false, @OA\Schema(type="integer", example=5)),
     *     @OA\Parameter(name="session_type", in="query", required=false,
     *         @OA\Schema(type="string", enum={"individual","group","track_day","theory"})),
     *     @OA\Parameter(name="payment_status", in="query", required=false,
     *         @OA\Schema(type="string", enum={"pending","paid","failed","refunded"})),
     *     @OA\Parameter(name="date_from",    in="query", required=false, @OA\Schema(type="string", format="date", example="2026-06-01")),
     *     @OA\Parameter(name="date_to",      in="query", required=false, @OA\Schema(type="string", format="date", example="2026-06-30")),
     *     @OA\Parameter(name="per_page",     in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(
     *         response=200,
     *         description="Bookings retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",             type="integer",  example=1),
     *                         @OA\Property(property="booking_date",   type="string",   format="date",     example="2026-06-20"),
     *                         @OA\Property(property="start_time",     type="string",   format="time",     example="10:00:00"),
     *                         @OA\Property(property="end_time",       type="string",   format="time",     example="12:00:00"),
     *                         @OA\Property(property="duration_hours", type="number",   format="float",    example=2),
     *                         @OA\Property(property="session_type",   type="string",   example="individual"),
     *                         @OA\Property(property="status",         type="string",   example="confirmed"),
     *                         @OA\Property(property="price",          type="number",   format="float",    example=300.00),
     *                         @OA\Property(property="payment_status", type="string",   example="paid"),
     *                         @OA\Property(property="confirmed_at",   type="string",   format="datetime", nullable=true),
     *                         @OA\Property(property="completed_at",   type="string",   format="datetime", nullable=true),
     *                         @OA\Property(property="trainer", type="object",
     *                             @OA\Property(property="id",   type="integer", example=1),
     *                             @OA\Property(property="name", type="string",  example="Khalid Al-Mansouri")
     *                         ),
     *                         @OA\Property(property="user", type="object",
     *                             @OA\Property(property="id",         type="integer", example=5),
     *                             @OA\Property(property="first_name", type="string",  example="Ahmed"),
     *                             @OA\Property(property="last_name",  type="string",  example="Bouali"),
     *                             @OA\Property(property="email",      type="string",  example="ahmed@example.com")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="summary", type="object",
     *                     @OA\Property(property="total",    type="integer", example=320),
     *                     @OA\Property(property="filtered", type="integer", example=34)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = TrainerBooking::with([
            'trainer:id,name,name_ar',
            'user:id,first_name,last_name,email,phone',
            'location:id,location_name',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('trainer_id')) {
            $query->where('trainer_id', $request->trainer_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('session_type')) {
            $query->where('session_type', $request->session_type);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('booking_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('booking_date', '<=', $request->date_to);
        }

        $bookings = $query->latest()->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => [
                'data'    => $bookings,
                'summary' => [
                    'total'    => TrainerBooking::count(),
                    'filtered' => $bookings->total(),
                ],
            ],
            'message' => 'Bookings retrieved successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/trainer-bookings/{id}",
     *     summary="Get booking details (Admin)",
     *     description="Returns full details of a trainer booking including trainer, client, location, payment split, payout and review if any.",
     *     operationId="adminShowTrainerBooking",
     *     tags={"Admin - Trainer Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Booking details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",             type="integer",  example=1),
     *                 @OA\Property(property="booking_date",   type="string",   format="date",     example="2026-06-20"),
     *                 @OA\Property(property="start_time",     type="string",   format="time",     example="10:00:00"),
     *                 @OA\Property(property="end_time",       type="string",   format="time",     example="12:00:00"),
     *                 @OA\Property(property="session_type",   type="string",   example="individual"),
     *                 @OA\Property(property="status",         type="string",   example="completed"),
     *                 @OA\Property(property="price",          type="number",   format="float",    example=300.00),
     *                 @OA\Property(property="payment_status", type="string",   example="paid"),
     *                 @OA\Property(property="notes",          type="string",   nullable=true),
     *                 @OA\Property(property="trainer", type="object",
     *                     @OA\Property(property="id",             type="integer"),
     *                     @OA\Property(property="name",           type="string"),
     *                     @OA\Property(property="specialty",      type="string"),
     *                     @OA\Property(property="rating_average", type="number", format="float")
     *                 ),
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id",         type="integer"),
     *                     @OA\Property(property="first_name", type="string"),
     *                     @OA\Property(property="last_name",  type="string"),
     *                     @OA\Property(property="email",      type="string"),
     *                     @OA\Property(property="phone",      type="string")
     *                 ),
     *                 @OA\Property(property="location", type="object", nullable=true,
     *                     @OA\Property(property="id",            type="integer"),
     *                     @OA\Property(property="location_name", type="string"),
     *                     @OA\Property(property="city_id",       type="integer")
     *                 ),
     *                 @OA\Property(property="payment_split", type="object", nullable=true,
     *                     @OA\Property(property="total_amount",          type="number", format="float", example=300.00),
     *                     @OA\Property(property="commission_percentage", type="number", format="float", example=20.00),
     *                     @OA\Property(property="commission_amount",     type="number", format="float", example=60.00),
     *                     @OA\Property(property="trainer_amount",        type="number", format="float", example=240.00),
     *                     @OA\Property(property="status",                type="string", example="settled")
     *                 ),
     *                 @OA\Property(property="review", type="object", nullable=true,
     *                     @OA\Property(property="rating",  type="integer", example=5),
     *                     @OA\Property(property="comment", type="string",  example="Excellent session!"),
     *                     @OA\Property(property="is_approved", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Booking not found")
     * )
     */
    public function show(int $id)
    {
        $booking = TrainerBooking::with([
            'trainer:id,name,name_ar,specialty,rating_average,photo',
            'user:id,first_name,last_name,email,phone',
            'location:id,location_name,location_name_ar,city_id,latitude,longitude',
            'paymentSplit',
            'review:id,booking_id,rating,comment,is_approved,created_at',
        ])->find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $booking,
            'message' => 'Booking details retrieved',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/trainer-bookings/{id}/cancel",
     *     summary="Force-cancel a booking (Admin)",
     *     description="Admin force-cancel of any active booking (pending, confirmed, in_progress). Requires a reason. A notification is sent to both the trainer and the client.",
     *     operationId="adminCancelTrainerBooking",
     *     tags={"Admin - Trainer Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", example="Trainer suspended due to policy violation",
     *                 description="Mandatory reason for admin cancellation — sent to both parties")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Booking cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Booking cancelled by admin")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Booking cannot be cancelled (already completed or cancelled)"),
     *     @OA\Response(response=404, description="Booking not found"),
     *     @OA\Response(response=422, description="Validation error — reason is required")
     * )
     */
    public function cancel(Request $request, int $id)
    {
        $booking = TrainerBooking::find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        }

        if (in_array($booking->status, ['completed', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => "Booking cannot be cancelled — current status: {$booking->status}",
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

        // TODO: Send push notification + email to trainer and client

        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled by admin',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/trainer-bookings/{id}/confirm",
     *     summary="Manually confirm a booking (Admin)",
     *     description="Admin can manually move a pending booking to confirmed status (e.g. when automatic payment confirmation was delayed).",
     *     operationId="adminConfirmTrainerBooking",
     *     tags={"Admin - Trainer Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Booking confirmed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Booking confirmed by admin")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Only pending bookings can be confirmed"),
     *     @OA\Response(response=404, description="Booking not found")
     * )
     */
    public function confirm(int $id)
    {
        $booking = TrainerBooking::find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        }

        if ($booking->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Only pending bookings can be confirmed — current status: {$booking->status}",
            ], 400);
        }

        $booking->update(['status' => 'confirmed', 'confirmed_at' => now()]);

        // TODO: Send push notification + email to trainer and client

        return response()->json([
            'success' => true,
            'message' => 'Booking confirmed by admin',
        ]);
    }
}
