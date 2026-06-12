<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\TrainerBooking;
use App\Models\TrainerReview;
use App\Models\Trainer;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Trainer Reviews",
 *     description="Submit and browse trainer reviews"
 * )
 */
class TrainerReviewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/trainers/{id}/reviews",
     *     summary="Trainer reviews",
     *     description="Returns approved reviews for a trainer, paginated.",
     *     operationId="getTrainerReviews",
     *     tags={"Trainer Reviews"},
     *     @OA\Parameter(name="id",       in="path",  required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=10)),
     *     @OA\Response(
     *         response=200,
     *         description="Reviews retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",         type="integer", example=5),
     *                         @OA\Property(property="rating",     type="integer", example=5),
     *                         @OA\Property(property="comment",    type="string",  example="Excellent coach!"),
     *                         @OA\Property(property="created_at", type="string",  format="datetime"),
     *                         @OA\Property(property="user", type="object",
     *                             @OA\Property(property="id",         type="integer"),
     *                             @OA\Property(property="first_name", type="string"),
     *                             @OA\Property(property="last_name",  type="string"),
     *                             @OA\Property(property="avatar",     type="string", nullable=true)
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="rating_summary", type="object",
     *                     @OA\Property(property="average",      type="number", format="float", example=4.8),
     *                     @OA\Property(property="total",        type="integer", example=24),
     *                     @OA\Property(property="distribution", type="object",
     *                         @OA\Property(property="5", type="integer", example=18),
     *                         @OA\Property(property="4", type="integer", example=4),
     *                         @OA\Property(property="3", type="integer", example=1),
     *                         @OA\Property(property="2", type="integer", example=1),
     *                         @OA\Property(property="1", type="integer", example=0)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Trainer not found")
     * )
     */
    public function index(Request $request, int $id)
    {
        $trainer = Trainer::approved()->find($id);

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $reviews = TrainerReview::where('trainer_id', $trainer->id)
            ->approved()
            ->with('user:id,first_name,last_name,avatar')
            ->latest()
            ->paginate($request->get('per_page', 10));

        $dist = TrainerReview::where('trainer_id', $trainer->id)->approved()
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $ratingSummary = [
            'average'      => $trainer->rating_average,
            'total'        => $reviews->total(),
            'distribution' => [
                '5' => $dist[5] ?? 0,
                '4' => $dist[4] ?? 0,
                '3' => $dist[3] ?? 0,
                '2' => $dist[2] ?? 0,
                '1' => $dist[1] ?? 0,
            ],
        ];

        return response()->json([
            'success' => true,
            'data'    => ['data' => $reviews, 'rating_summary' => $ratingSummary],
            'message' => 'Reviews retrieved successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/trainer/bookings/{bookingId}/review",
     *     summary="Submit a review",
     *     description="Submit a review for a completed training session. Only the client who booked can review. Only one review per booking. Review goes through moderation before appearing publicly.",
     *     operationId="submitTrainerReview",
     *     tags={"Trainer Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="bookingId", in="path", required=true, @OA\Schema(type="integer", example=42)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rating"},
     *             @OA\Property(property="rating",  type="integer", minimum=1, maximum=5, example=5),
     *             @OA\Property(property="comment", type="string",  example="Khalid is an outstanding coach. Very patient and professional.")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Review submitted — pending moderation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Review submitted and pending moderation")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Session not completed or already reviewed"),
     *     @OA\Response(response=403, description="Not your booking"),
     *     @OA\Response(response=404, description="Booking not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, int $bookingId)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $booking = TrainerBooking::find($bookingId);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found'], 404);
        }

        if ($booking->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Not your booking'], 403);
        }

        if (!$booking->canBeReviewed()) {
            return response()->json(['success' => false, 'message' => 'Session is not completed or already reviewed'], 400);
        }

        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        TrainerReview::create([
            'booking_id'  => $booking->id,
            'trainer_id'  => $booking->trainer_id,
            'user_id'     => $user->id,
            'rating'      => $validated['rating'],
            'comment'     => $validated['comment'] ?? null,
            'is_approved' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted and pending moderation',
        ], 201);
    }
}
