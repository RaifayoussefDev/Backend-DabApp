<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Models\TrainerBooking;
use App\Models\TrainerComment;
use App\Models\TrainerPayout;
use App\Models\TrainerReview;
use App\Services\NotificationService;
use App\Traits\ExportsToExcel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Admin - Trainers",
 *     description="Admin management of trainer profiles, reviews, and comments"
 * )
 */
class AdminTrainerController extends Controller
{
    use ExportsToExcel;

    protected NotificationService $notifications;

    public function __construct(NotificationService $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * @OA\Get(
     *     path="/api/admin/trainers",
     *     summary="List all trainers (Admin)",
     *     description="Returns all trainers with optional status filter and search. Admin only.",
     *     operationId="adminListTrainers",
     *     tags={"Admin - Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status",   in="query", required=false, @OA\Schema(type="string", enum={"pending","approved","rejected","suspended"})),
     *     @OA\Parameter(name="search",   in="query", required=false, @OA\Schema(type="string", example="Khalid")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(
     *         response=200,
     *         description="Trainers retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",             type="integer", example=1),
     *                         @OA\Property(property="name",           type="string",  example="Khalid Al-Mansouri"),
     *                         @OA\Property(property="specialty",      type="string",  example="coaching"),
     *                         @OA\Property(property="status",         type="string",  example="pending"),
     *                         @OA\Property(property="experience_years",type="integer",example=8),
     *                         @OA\Property(property="total_sessions", type="integer", example=0),
     *                         @OA\Property(property="rating_average", type="number",  format="float", example=0.00),
     *                         @OA\Property(property="created_at",     type="string",  format="datetime"),
     *                         @OA\Property(property="user", type="object",
     *                             @OA\Property(property="id",         type="integer"),
     *                             @OA\Property(property="first_name", type="string"),
     *                             @OA\Property(property="last_name",  type="string"),
     *                             @OA\Property(property="email",      type="string"),
     *                             @OA\Property(property="phone",      type="string")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="stats", type="object",
     *                     @OA\Property(property="total",     type="integer", example=50),
     *                     @OA\Property(property="pending",   type="integer", example=8),
     *                     @OA\Property(property="approved",  type="integer", example=35),
     *                     @OA\Property(property="rejected",  type="integer", example=5),
     *                     @OA\Property(property="suspended", type="integer", example=2)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Trainer::with('user:id,first_name,last_name,email,phone');

        if ($request->filled('status'))    { $query->where('status', $request->status); }
        if ($request->filled('specialty')) { $query->where('specialty', $request->specialty); }
        if ($request->filled('min_rating')){ $query->where('rating_average', '>=', $request->min_rating); }
        if ($request->filled('min_price')) { $query->where('price_per_hour', '>=', $request->min_price); }
        if ($request->filled('max_price')) { $query->where('price_per_hour', '<=', $request->max_price); }
        if ($request->filled('created_from')) { $query->whereDate('created_at', '>=', $request->created_from); }
        if ($request->filled('created_to'))   { $query->whereDate('created_at', '<=', $request->created_to); }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'LIKE', "%{$s}%")->orWhere('name_ar', 'LIKE', "%{$s}%"));
        }

        $trainers = $query->latest()->paginate($request->get('per_page', 20));

        $stats = [
            'total'     => Trainer::count(),
            'pending'   => Trainer::where('status', 'pending')->count(),
            'approved'  => Trainer::where('status', 'approved')->count(),
            'rejected'  => Trainer::where('status', 'rejected')->count(),
            'suspended' => Trainer::where('status', 'suspended')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => ['data' => $trainers, 'stats' => $stats],
            'message' => 'Trainers retrieved successfully',
        ]);
    }

    public function export(Request $request)
    {
        $query = Trainer::with('user:id,first_name,last_name,email,phone');

        if ($request->filled('status'))       { $query->where('status', $request->status); }
        if ($request->filled('specialty'))    { $query->where('specialty', $request->specialty); }
        if ($request->filled('min_rating'))   { $query->where('rating_average', '>=', $request->min_rating); }
        if ($request->filled('min_price'))    { $query->where('price_per_hour', '>=', $request->min_price); }
        if ($request->filled('max_price'))    { $query->where('price_per_hour', '<=', $request->max_price); }
        if ($request->filled('created_from')) { $query->whereDate('created_at', '>=', $request->created_from); }
        if ($request->filled('created_to'))   { $query->whereDate('created_at', '<=', $request->created_to); }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'LIKE', "%{$s}%")->orWhere('name_ar', 'LIKE', "%{$s}%"));
        }

        $items   = $query->latest()->get();
        $cols    = ['ID', 'Name', 'Name AR', 'Specialty', 'Experience (yrs)', 'Price/hr', 'Rating', 'Sessions', 'Status', 'Available', 'Email', 'Phone', 'Created At'];
        $filename = 'trainers-' . now()->format('Y-m-d');

        $rowMapper = fn ($t) => [
            $t->id, $t->name, $t->name_ar, $t->specialty,
            $t->experience_years, $t->price_per_hour, $t->rating_average,
            $t->total_sessions, $t->status,
            $t->is_available ? 'Yes' : 'No',
            $t->user?->email, $t->user?->phone,
            $t->created_at?->format('Y-m-d H:i'),
        ];

        if ($request->get('format') === 'excel') {
            return $this->excelResponse($filename, $cols, $items->map($rowMapper));
        }

        return response()->stream(function () use ($items, $cols, $rowMapper) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $cols);
            foreach ($items as $t) { fputcsv($file, $rowMapper($t)); }
            fclose($file);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/trainers/{id}/approve",
     *     summary="Approve a trainer",
     *     description="Approves a pending trainer profile. The trainer's profile becomes visible to all users. A push notification + email is sent to the trainer.",
     *     operationId="adminApproveTrainer",
     *     tags={"Admin - Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Trainer approved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Trainer approved successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Trainer not found"),
     *     @OA\Response(response=400, description="Trainer is already approved")
     * )
     */
    public function approve(int $id)
    {
        $trainer = Trainer::find($id);

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        if ($trainer->status === 'approved') {
            return response()->json(['success' => false, 'message' => 'Trainer is already approved'], 400);
        }

        $trainer->update(['status' => 'approved', 'is_available' => true]);

        try {
            $this->notifications->notifyTrainerApproved($trainer->user, $trainer);
        } catch (\Exception $e) {
            Log::error('AdminTrainerController@approve notify failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Trainer approved successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/trainers/{id}/reject",
     *     summary="Reject a trainer",
     *     description="Rejects a pending trainer profile. A push notification + email with the reason is sent to the trainer.",
     *     operationId="adminRejectTrainer",
     *     tags={"Admin - Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="Certifications are missing or unverifiable")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Trainer rejected",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Trainer rejected")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Trainer not found")
     * )
     */
    public function reject(Request $request, int $id)
    {
        $trainer = Trainer::find($id);

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $request->validate(['reason' => 'nullable|string|max:1000']);

        $trainer->update(['status' => 'rejected', 'is_available' => false]);

        try {
            $this->notifications->notifyTrainerRejected($trainer->user, $trainer, $request->reason);
        } catch (\Exception $e) {
            Log::error('AdminTrainerController@reject notify failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Trainer rejected']);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/trainers/{id}/suspend",
     *     summary="Suspend a trainer",
     *     description="Suspends an approved trainer. Profile hidden from public. Push notification + email sent.",
     *     operationId="adminSuspendTrainer",
     *     tags={"Admin - Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="Multiple complaints received from clients")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Trainer suspended",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Trainer suspended")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Trainer not found")
     * )
     */
    public function suspend(Request $request, int $id)
    {
        $trainer = Trainer::find($id);

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $request->validate(['reason' => 'nullable|string|max:1000']);

        $trainer->update(['status' => 'suspended', 'is_available' => false]);

        try {
            $this->notifications->notifyTrainerSuspended($trainer->user, $trainer, $request->reason);
        } catch (\Exception $e) {
            Log::error('AdminTrainerController@suspend notify failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Trainer suspended']);
    }

    // ---------------------------------------------------------------
    // Admin — Moderate reviews
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/admin/trainer-reviews",
     *     summary="Pending reviews (Admin)",
     *     description="Returns trainer reviews pending admin approval.",
     *     operationId="adminPendingTrainerReviews",
     *     tags={"Admin - Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="approved", in="query", required=false, @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Reviews retrieved")
     * )
     */
    public function reviews(Request $request)
    {
        $query = TrainerReview::with([
            'trainer:id,name',
            'user:id,first_name,last_name',
            'booking:id,booking_date,session_type',
        ]);

        if ($request->has('approved')) {
            $query->where('is_approved', $request->boolean('approved'));
        } else {
            $query->where('is_approved', false); // default: pending only
        }

        return response()->json([
            'success' => true,
            'data'    => $query->latest()->paginate($request->get('per_page', 20)),
            'message' => 'Reviews retrieved successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/trainer-reviews/{id}/approve",
     *     summary="Approve a review",
     *     description="Approves a trainer review, making it visible on the trainer's profile. Triggers rating recalculation and sends a notification to the trainer.",
     *     operationId="adminApproveTrainerReview",
     *     tags={"Admin - Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=5)),
     *     @OA\Response(response=200, description="Review approved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success",        type="boolean", example=true),
     *             @OA\Property(property="message",        type="string",  example="Review approved"),
     *             @OA\Property(property="new_rating_avg", type="number",  format="float", example=4.8)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Review not found")
     * )
     */
    public function approveReview(int $id)
    {
        $review = TrainerReview::with('trainer')->find($id);

        if (!$review) {
            return response()->json(['success' => false, 'message' => 'Review not found'], 404);
        }

        $review->update(['is_approved' => true]);
        $review->trainer->recalculateRating();

        try {
            $this->notifications->notifyTrainerReviewApproved($review->trainer->user, $review->trainer);
        } catch (\Exception $e) {
            Log::error('AdminTrainerController@approveReview notify failed: ' . $e->getMessage());
        }

        return response()->json([
            'success'        => true,
            'message'        => 'Review approved',
            'new_rating_avg' => $review->trainer->fresh()->rating_average,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/trainer-reviews/{id}",
     *     summary="Delete a review",
     *     description="Deletes a trainer review (rejected or spam). Triggers rating recalculation.",
     *     operationId="adminDeleteTrainerReview",
     *     tags={"Admin - Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=5)),
     *     @OA\Response(response=200, description="Review deleted"),
     *     @OA\Response(response=404, description="Review not found")
     * )
     */
    public function deleteReview(int $id)
    {
        $review = TrainerReview::with('trainer')->find($id);

        if (!$review) {
            return response()->json(['success' => false, 'message' => 'Review not found'], 404);
        }

        $trainer = $review->trainer;
        $review->delete();
        $trainer->recalculateRating();

        return response()->json(['success' => true, 'message' => 'Review deleted']);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/trainer-comments/{id}/approve",
     *     summary="Approve a comment",
     *     description="Approves a trainer comment, making it visible on the profile.",
     *     operationId="adminApproveTrainerComment",
     *     tags={"Admin - Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=3)),
     *     @OA\Response(response=200, description="Comment approved"),
     *     @OA\Response(response=404, description="Comment not found")
     * )
     */
    public function approveComment(int $id)
    {
        $comment = TrainerComment::find($id);

        if (!$comment) {
            return response()->json(['success' => false, 'message' => 'Comment not found'], 404);
        }

        $comment->update(['is_approved' => true]);

        return response()->json(['success' => true, 'message' => 'Comment approved']);
    }

    // ---------------------------------------------------------------
    // Admin — Single trainer profile
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/admin/trainers/{id}",
     *     summary="Get full trainer profile (Admin)",
     *     description="Returns the complete trainer profile with user info, locations, schedules, last 5 bookings, rating details, payout summary and commission setting.",
     *     operationId="adminShowTrainer",
     *     tags={"Admin - Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Trainer profile",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",              type="integer", example=1),
     *                 @OA\Property(property="name",            type="string",  example="Khalid Al-Mansouri"),
     *                 @OA\Property(property="name_ar",         type="string",  example="خالد المنصوري"),
     *                 @OA\Property(property="specialty",       type="string",  example="coaching"),
     *                 @OA\Property(property="status",          type="string",  example="approved"),
     *                 @OA\Property(property="experience_years",type="integer", example=8),
     *                 @OA\Property(property="price_per_hour",  type="number",  format="float", example=150.00),
     *                 @OA\Property(property="rating_average",  type="number",  format="float", example=4.8),
     *                 @OA\Property(property="total_sessions",  type="integer", example=48),
     *                 @OA\Property(property="likes_count",     type="integer", example=130),
     *                 @OA\Property(property="is_available",    type="boolean", example=true),
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id",         type="integer"),
     *                     @OA\Property(property="first_name", type="string"),
     *                     @OA\Property(property="last_name",  type="string"),
     *                     @OA\Property(property="email",      type="string"),
     *                     @OA\Property(property="phone",      type="string")
     *                 ),
     *                 @OA\Property(property="stats", type="object",
     *                     @OA\Property(property="total_bookings",       type="integer", example=52),
     *                     @OA\Property(property="completed_bookings",   type="integer", example=48),
     *                     @OA\Property(property="cancelled_bookings",   type="integer", example=3),
     *                     @OA\Property(property="total_revenue",        type="number",  format="float", example=7200.00),
     *                     @OA\Property(property="total_commission",     type="number",  format="float", example=1440.00),
     *                     @OA\Property(property="pending_payout",       type="number",  format="float", example=600.00),
     *                     @OA\Property(property="pending_reviews",      type="integer", example=2)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Trainer not found")
     * )
     */
    public function show(int $id)
    {
        $trainer = Trainer::with([
            'user:id,first_name,last_name,email,phone',
            'locations',
            'schedules',
            'commissionSetting',
        ])->find($id);

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $splitModel = \App\Models\PaymentSplit::where('trainer_id', $trainer->id);

        $stats = [
            'total_bookings'     => TrainerBooking::where('trainer_id', $trainer->id)->count(),
            'completed_bookings' => TrainerBooking::where('trainer_id', $trainer->id)->where('status', 'completed')->count(),
            'cancelled_bookings' => TrainerBooking::where('trainer_id', $trainer->id)->where('status', 'cancelled')->count(),
            'total_revenue'      => (float) (clone $splitModel)->sum('total_amount'),
            'total_commission'   => (float) (clone $splitModel)->sum('commission_amount'),
            'pending_payout'     => (float) TrainerPayout::where('trainer_id', $trainer->id)->where('status', 'pending')->sum('amount'),
            'pending_reviews'    => TrainerReview::where('trainer_id', $trainer->id)->where('is_approved', false)->count(),
        ];

        $recentBookings = TrainerBooking::where('trainer_id', $trainer->id)
            ->with('user:id,first_name,last_name')
            ->latest()
            ->limit(5)
            ->get(['id', 'user_id', 'booking_date', 'status', 'price', 'session_type']);

        return response()->json([
            'success' => true,
            'data'    => array_merge($trainer->append('photo_url')->toArray(), [
                'stats'           => $stats,
                'recent_bookings' => $recentBookings,
            ]),
            'message' => 'Trainer profile retrieved',
        ]);
    }

    // ---------------------------------------------------------------
    // Admin — Reactivate / Delete trainer
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/admin/trainers/{id}/reactivate",
     *     summary="Reactivate a suspended trainer",
     *     description="Moves a suspended trainer back to approved status, restoring their public visibility. A push notification + email is sent.",
     *     operationId="adminReactivateTrainer",
     *     tags={"Admin - Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Trainer reactivated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Trainer reactivated")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Trainer is not suspended"),
     *     @OA\Response(response=404, description="Trainer not found")
     * )
     */
    public function reactivate(int $id)
    {
        $trainer = Trainer::find($id);

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        if ($trainer->status !== 'suspended') {
            return response()->json(['success' => false, 'message' => 'Only suspended trainers can be reactivated'], 400);
        }

        $trainer->update(['status' => 'approved', 'is_available' => true]);

        try {
            $this->notifications->notifyTrainerReactivated($trainer->user, $trainer);
        } catch (\Exception $e) {
            Log::error('AdminTrainerController@reactivate notify failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Trainer reactivated']);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/trainers/{id}",
     *     summary="Delete a trainer profile (Admin)",
     *     description="Permanently removes a trainer profile. Only allowed for rejected trainers with no confirmed or completed bookings. This action is irreversible.",
     *     operationId="adminDeleteTrainer",
     *     tags={"Admin - Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Trainer deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Trainer profile deleted")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot delete — active bookings exist"),
     *     @OA\Response(response=404, description="Trainer not found")
     * )
     */
    public function destroy(int $id)
    {
        $trainer = Trainer::find($id);

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $activeBookings = TrainerBooking::where('trainer_id', $trainer->id)
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->count();

        if ($activeBookings > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete trainer with {$activeBookings} active booking(s). Cancel them first.",
            ], 400);
        }

        $trainer->delete();

        return response()->json(['success' => true, 'message' => 'Trainer profile deleted']);
    }

    // ---------------------------------------------------------------
    // Admin — List comments
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/admin/trainer-comments",
     *     summary="List all trainer comments (Admin)",
     *     description="Returns all trainer comments with optional filters: approved/pending, trainer, user.",
     *     operationId="adminListTrainerComments",
     *     tags={"Admin - Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="approved",   in="query", required=false, @OA\Schema(type="integer", enum={0,1},
     *         description="Filter by approval status. Omit to get all.")),
     *     @OA\Parameter(name="trainer_id", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="user_id",    in="query", required=false, @OA\Schema(type="integer", example=5)),
     *     @OA\Parameter(name="per_page",   in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(
     *         response=200,
     *         description="Comments retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",          type="integer", example=1),
     *                         @OA\Property(property="content",     type="string",  example="Great trainer!"),
     *                         @OA\Property(property="is_approved", type="boolean", example=false),
     *                         @OA\Property(property="parent_id",   type="integer", nullable=true),
     *                         @OA\Property(property="created_at",  type="string",  format="datetime"),
     *                         @OA\Property(property="trainer", type="object",
     *                             @OA\Property(property="id",   type="integer"),
     *                             @OA\Property(property="name", type="string")
     *                         ),
     *                         @OA\Property(property="user", type="object",
     *                             @OA\Property(property="id",         type="integer"),
     *                             @OA\Property(property="first_name", type="string"),
     *                             @OA\Property(property="last_name",  type="string")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function comments(Request $request)
    {
        $query = TrainerComment::with([
            'trainer:id,name',
            'user:id,first_name,last_name',
        ]);

        if ($request->has('approved')) {
            $query->where('is_approved', $request->boolean('approved'));
        }
        if ($request->filled('trainer_id')) {
            $query->where('trainer_id', $request->trainer_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->latest()->paginate($request->get('per_page', 20)),
            'message' => 'Comments retrieved successfully',
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/trainer-comments/{id}",
     *     summary="Delete a trainer comment (Admin)",
     *     description="Hard-deletes a trainer comment (spam, abusive, or flagged). Also removes all child replies.",
     *     operationId="adminDeleteTrainerComment",
     *     tags={"Admin - Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=3)),
     *     @OA\Response(response=200, description="Comment deleted"),
     *     @OA\Response(response=404, description="Comment not found")
     * )
     */
    public function deleteComment(int $id)
    {
        $comment = TrainerComment::find($id);

        if (!$comment) {
            return response()->json(['success' => false, 'message' => 'Comment not found'], 404);
        }

        // Delete replies first to avoid FK violation
        $comment->replies()->delete();
        $comment->delete();

        return response()->json(['success' => true, 'message' => 'Comment deleted']);
    }
}
