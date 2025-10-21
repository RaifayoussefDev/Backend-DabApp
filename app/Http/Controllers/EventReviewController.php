<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventReview;
use App\Models\EventParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventReviewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/reviews",
     *     summary="Get event reviews",
     *     tags={"Event Reviews"},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="rating", in="query", description="Filter by rating", @OA\Schema(type="integer", minimum=1, maximum=5)),
     *     @OA\Response(
     *         response=200,
     *         description="List of reviews with statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="reviews", type="object"),
     *             @OA\Property(property="statistics", type="object",
     *                 @OA\Property(property="average_rating", type="number"),
     *                 @OA\Property(property="total_reviews", type="integer"),
     *                 @OA\Property(property="rating_distribution", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request, $eventId)
    {
        $event = Event::findOrFail($eventId);

        $query = EventReview::where('event_id', $eventId)
            ->approved()
            ->with('user');

        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        $perPage = $request->get('per_page', 20);
        $reviews = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Calculate statistics
        $allReviews = EventReview::where('event_id', $eventId)->approved();
        $averageRating = round($allReviews->avg('rating'), 2);
        $totalReviews = $allReviews->count();

        // Rating distribution
        $ratingDistribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $ratingDistribution[$i] = EventReview::where('event_id', $eventId)
                ->approved()
                ->where('rating', $i)
                ->count();
        }

        return response()->json([
            'message' => 'Reviews retrieved successfully',
            'reviews' => $reviews,
            'statistics' => [
                'average_rating' => $averageRating,
                'total_reviews' => $totalReviews,
                'rating_distribution' => $ratingDistribution
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/reviews/{reviewId}",
     *     summary="Get single review details",
     *     tags={"Event Reviews"},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="reviewId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Review details"),
     *     @OA\Response(response=404, description="Review not found")
     * )
     */
    public function show($eventId, $reviewId)
    {
        $review = EventReview::where('event_id', $eventId)
            ->where('id', $reviewId)
            ->with('user')
            ->firstOrFail();

        return response()->json([
            'message' => 'Review retrieved successfully',
            'data' => $review
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/events/{eventId}/reviews",
     *     summary="Add a review for an event (auth required, must have participated)",
     *     tags={"Event Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rating"},
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=5),
     *             @OA\Property(property="comment", type="string", maxLength=2000, example="Amazing event! Very well organized.")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Review added successfully"),
     *     @OA\Response(response=400, description="Already reviewed"),
     *     @OA\Response(response=403, description="Not participated"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(Request $request, $eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        // Check if user participated in the event
        $participated = EventParticipant::where('event_id', $eventId)
            ->where('user_id', $user->id)
            ->whereIn('status', ['confirmed', 'attended'])
            ->exists();

        if (!$participated) {
            return response()->json([
                'message' => 'You must have participated in the event to leave a review'
            ], 403);
        }

        // Check if user already reviewed
        $existingReview = EventReview::where('event_id', $eventId)
            ->where('user_id', $user->id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'message' => 'You have already left a review for this event. Use the update endpoint to modify it.'
            ], 400);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $review = EventReview::create([
            'event_id' => $eventId,
            'user_id' => $user->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'is_approved' => 1, // Auto-approve by default
        ]);

        return response()->json([
            'message' => 'Review added successfully',
            'data' => $review->load('user')
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/events/{eventId}/reviews/{reviewId}",
     *     summary="Update a review (auth required, owner only)",
     *     tags={"Event Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="reviewId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5),
     *             @OA\Property(property="comment", type="string", maxLength=2000)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Review updated successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Review not found")
     * )
     */
    public function update(Request $request, $eventId, $reviewId)
    {
        $user = Auth::user();

        $review = EventReview::where('event_id', $eventId)
            ->where('id', $reviewId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $review->update($validated);

        return response()->json([
            'message' => 'Review updated successfully',
            'data' => $review->fresh()->load('user')
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/events/{eventId}/reviews/{reviewId}",
     *     summary="Delete a review (auth required, owner only)",
     *     tags={"Event Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="reviewId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Review deleted successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Review not found")
     * )
     */
    public function destroy($eventId, $reviewId)
    {
        $user = Auth::user();

        $review = EventReview::where('event_id', $eventId)
            ->where('id', $reviewId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/my-review",
     *     summary="Get my review for event (auth required)",
     *     tags={"Event Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="My review or null",
     *         @OA\JsonContent(
     *             @OA\Property(property="has_reviewed", type="boolean"),
     *             @OA\Property(property="review", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function myReview($eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        $review = EventReview::where('event_id', $eventId)
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'has_reviewed' => $review !== null,
            'review' => $review
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/reviews/can-review",
     *     summary="Check if user can review event (auth required)",
     *     tags={"Event Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Review eligibility status",
     *         @OA\JsonContent(
     *             @OA\Property(property="can_review", type="boolean"),
     *             @OA\Property(property="reason", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function canReview($eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        // Check if already reviewed
        $existingReview = EventReview::where('event_id', $eventId)
            ->where('user_id', $user->id)
            ->exists();

        if ($existingReview) {
            return response()->json([
                'can_review' => false,
                'reason' => 'You have already reviewed this event'
            ]);
        }

        // Check if participated
        $participated = EventParticipant::where('event_id', $eventId)
            ->where('user_id', $user->id)
            ->whereIn('status', ['confirmed', 'attended'])
            ->exists();

        if (!$participated) {
            return response()->json([
                'can_review' => false,
                'reason' => 'You must participate in the event to leave a review'
            ]);
        }

        return response()->json([
            'can_review' => true,
            'reason' => null
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/my-reviews",
     *     summary="Get all my reviews (auth required)",
     *     tags={"Event Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="List of my reviews"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function myReviews(Request $request)
    {
        $user = Auth::user();

        $reviews = EventReview::where('user_id', $user->id)
            ->with('event')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'message' => 'Your reviews retrieved successfully',
            'data' => $reviews
        ]);
    }
}
