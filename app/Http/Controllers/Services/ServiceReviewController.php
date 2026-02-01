<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ServiceReview;
use App\Models\Service;
use App\Models\ServiceBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Service Reviews",
 *     description="API endpoints for managing service reviews and ratings"
 * )
 */
class ServiceReviewController extends Controller
{
    /**
     * Get reviews for a specific service
     *
     * @OA\Get(
     *     path="/api/services/{service_id}/reviews",
     *     summary="Get service reviews",
     *     description="Retrieve paginated list of approved reviews for a specific service with ratings and user information",
     *     operationId="getServiceReviews",
     *     tags={"Service Reviews"},
     *     @OA\Parameter(
     *         name="service_id",
     *         in="path",
     *         description="Service ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, example=10)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort reviews by",
     *         required=false,
     *         @OA\Schema(type="string", enum={"recent", "rating_high", "rating_low"}, default="recent")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="reviews",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="rating", type="integer", example=5, description="Rating from 1 to 5"),
     *                         @OA\Property(property="comment", type="string", example="Excellent service! Very professional and timely."),
     *                         @OA\Property(property="comment_ar", type="string", example="خدمة ممتازة! محترفة جدا وفي الوقت المحدد."),
     *                         @OA\Property(
     *                             property="user",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=15),
     *                             @OA\Property(property="name", type="string", example="Ahmed Ali"),
     *                             @OA\Property(property="avatar", type="string", example="https://example.com/avatars/user15.jpg")
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="datetime", example="2026-01-15 14:30:00"),
     *                         @OA\Property(property="helpful_count", type="integer", example=12, description="Number of users who found this review helpful")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="statistics",
     *                     type="object",
     *                     @OA\Property(property="average_rating", type="number", format="float", example=4.5),
     *                     @OA\Property(property="total_reviews", type="integer", example=48),
     *                     @OA\Property(
     *                         property="rating_breakdown",
     *                         type="object",
     *                         @OA\Property(property="5_stars", type="integer", example=30),
     *                         @OA\Property(property="4_stars", type="integer", example=12),
     *                         @OA\Property(property="3_stars", type="integer", example=4),
     *                         @OA\Property(property="2_stars", type="integer", example=1),
     *                         @OA\Property(property="1_star", type="integer", example=1)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="total_pages", type="integer", example=5),
     *                     @OA\Property(property="per_page", type="integer", example=10),
     *                     @OA\Property(property="total", type="integer", example=48)
     *                 )
     *             ),
     *             example={
     *                 "success": true,
     *                 "data": {
     *                     "reviews": {
     *                         {
     *                             "id": 1,
     *                             "rating": 5,
     *                             "comment": "Excellent service! Very professional.",
     *                             "comment_ar": "خدمة ممتازة! محترفة جدا.",
     *                             "user": {
     *                                 "id": 15,
     *                                 "name": "Ahmed Ali",
     *                                 "avatar": "https://example.com/avatars/user15.jpg"
     *                             },
     *                             "created_at": "2026-01-15 14:30:00",
     *                             "helpful_count": 12
     *                         }
     *                     },
     *                     "statistics": {
     *                         "average_rating": 4.5,
     *                         "total_reviews": 48,
     *                         "rating_breakdown": {
     *                             "5_stars": 30,
     *                             "4_stars": 12,
     *                             "3_stars": 4,
     *                             "2_stars": 1,
     *                             "1_star": 1
     *                         }
     *                     },
     *                     "pagination": {
     *                         "current_page": 1,
     *                         "total_pages": 5,
     *                         "per_page": 10,
     *                         "total": 48
     *                     }
     *                 }
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Service not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Service not found")
     *         )
     *     )
     * )
     */
    public function index(Request $request, $serviceId)
    {
        $service = Service::findOrFail($serviceId);

        $perPage = $request->query('per_page', 10);
        $sortBy = $request->query('sort_by', 'recent');

        $query = ServiceReview::where('service_id', $serviceId)
            ->where('is_approved', true)
            ->with(['user']);

        // Sorting
        switch ($sortBy) {
            case 'rating_high':
                $query->orderBy('rating', 'desc');
                break;
            case 'rating_low':
                $query->orderBy('rating', 'asc');
                break;
            default:
                $query->latest();
                break;
        }

        $reviews = $query->paginate($perPage);

        // Calculate statistics
        $allReviews = ServiceReview::where('service_id', $serviceId)
            ->where('is_approved', true);

        $statistics = [
            'average_rating' => round($allReviews->avg('rating'), 1),
            'total_reviews' => $allReviews->count(),
            'rating_breakdown' => [
                '5_stars' => $allReviews->where('rating', 5)->count(),
                '4_stars' => $allReviews->where('rating', 4)->count(),
                '3_stars' => $allReviews->where('rating', 3)->count(),
                '2_stars' => $allReviews->where('rating', 2)->count(),
                '1_star' => $allReviews->where('rating', 1)->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $reviews->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'comment_ar' => $review->comment_ar,
                        'user' => [
                            'id' => $review->user->id,
                            'name' => $review->user->name,
                            'avatar' => $review->user->avatar_url ?? null,
                        ],
                        'created_at' => $review->created_at->format('Y-m-d H:i:s'),
                        'helpful_count' => $review->helpful_count ?? 0,
                    ];
                }),
                'statistics' => $statistics,
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'total_pages' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
            ],
        ]);
    }

    /**
     * Create a new review
     *
     * @OA\Post(
     *     path="/api/services/{service_id}/reviews",
     *     summary="Create a service review",
     *     description="Submit a new review for a service. User must have completed a booking for this service.",
     *     operationId="createServiceReview",
     *     tags={"Service Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="service_id",
     *         in="path",
     *         description="Service ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Review data",
     *         @OA\JsonContent(
     *             required={"rating", "comment"},
     *             @OA\Property(
     *                 property="rating",
     *                 type="integer",
     *                 minimum=1,
     *                 maximum=5,
     *                 example=5,
     *                 description="Rating from 1 to 5 stars"
     *             ),
     *             @OA\Property(
     *                 property="comment",
     *                 type="string",
     *                 minLength=10,
     *                 maxLength=1000,
     *                 example="Excellent service! Very professional and completed on time.",
     *                 description="Review comment in English"
     *             ),
     *             @OA\Property(
     *                 property="comment_ar",
     *                 type="string",
     *                 nullable=true,
     *                 maxLength=1000,
     *                 example="خدمة ممتازة! محترفة جدا وتم إنجازها في الوقت المحدد.",
     *                 description="Review comment in Arabic (optional)"
     *             ),
     *             @OA\Property(
     *                 property="booking_id",
     *                 type="integer",
     *                 example=42,
     *                 description="Booking ID (optional, for verification)"
     *             ),
     *             example={
     *                 "rating": 5,
     *                 "comment": "Excellent service! Very professional.",
     *                 "comment_ar": "خدمة ممتازة! محترفة جدا.",
     *                 "booking_id": 42
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Review created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Review submitted successfully. It will be visible after approval."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="review_id", type="integer", example=123),
     *                 @OA\Property(property="status", type="string", example="pending_approval")
     *             ),
     *             example={
     *                 "success": true,
     *                 "message": "Review submitted successfully. It will be visible after approval.",
     *                 "data": {
     *                     "review_id": 123,
     *                     "status": "pending_approval"
     *                 }
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - Already reviewed or no completed booking",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You have already reviewed this service")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Service not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="rating",
     *                     type="array",
     *                     @OA\Items(type="string", example="The rating must be between 1 and 5.")
     *                 ),
     *                 @OA\Property(
     *                     property="comment",
     *                     type="array",
     *                     @OA\Items(type="string", example="The comment must be at least 10 characters.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request, $serviceId)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:1000',
            'comment_ar' => 'nullable|string|max:1000',
            'booking_id' => 'nullable|exists:service_bookings,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $service = Service::findOrFail($serviceId);
        $user = Auth::user();

        // Check if user already reviewed this service
        $existingReview = ServiceReview::where('service_id', $serviceId)
            ->where('user_id', $user->id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this service',
            ], 400);
        }

        // Optional: Verify user has completed booking
        if ($request->booking_id) {
            $booking = ServiceBooking::where('id', $request->booking_id)
                ->where('user_id', $user->id)
                ->where('service_id', $serviceId)
                ->where('status', 'completed')
                ->first();

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must complete a booking before reviewing this service',
                ], 400);
            }
        }

        // Create review
        $review = ServiceReview::create([
            'service_id' => $serviceId,
            'user_id' => $user->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'comment_ar' => $request->comment_ar,
            'is_approved' => false, // Requires admin approval
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully. It will be visible after approval.',
            'data' => [
                'review_id' => $review->id,
                'status' => 'pending_approval',
            ],
        ], 201);
    }

    /**
     * Get user's own reviews
     *
     * @OA\Get(
     *     path="/api/services/my-reviews",
     *     summary="Get authenticated user's reviews",
     *     description="Retrieve all reviews submitted by the authenticated user across all services",
     *     operationId="getMyServiceReviews",
     *     tags={"Service Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="rating", type="integer", example=5),
     *                     @OA\Property(property="comment", type="string", example="Great service!"),
     *                     @OA\Property(
     *                         property="service",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=5),
     *                         @OA\Property(property="name", type="string", example="Bike Washing Service"),
     *                         @OA\Property(property="name_ar", type="string", example="خدمة غسيل الدراجة")
     *                     ),
     *                     @OA\Property(property="is_approved", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="datetime", example="2026-01-15 10:30:00")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function myReviews(Request $request)
    {
        $user = Auth::user();

        $reviews = ServiceReview::where('user_id', $user->id)
            ->with(['service'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $reviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'comment_ar' => $review->comment_ar,
                    'service' => [
                        'id' => $review->service->id,
                        'name' => $review->service->name,
                        'name_ar' => $review->service->name_ar,
                    ],
                    'is_approved' => $review->is_approved,
                    'created_at' => $review->created_at->format('Y-m-d H:i:s'),
                ];
            }),
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'total_pages' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Update a review
     *
     * @OA\Put(
     *     path="/api/reviews/{id}",
     *     summary="Update user's review",
     *     description="Update an existing review. Only the review author can update their review.",
     *     operationId="updateReview",
     *     tags={"Service Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Review ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=4),
     *             @OA\Property(property="comment", type="string", minLength=10, maxLength=1000, example="Updated comment"),
     *             @OA\Property(property="comment_ar", type="string", nullable=true, maxLength=1000, example="تعليق محدث"),
     *             example={
     *                 "rating": 4,
     *                 "comment": "Good service, but could be faster",
     *                 "comment_ar": "خدمة جيدة، لكن يمكن أن تكون أسرع"
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Review updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Not the review author",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You can only update your own reviews")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Review not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'sometimes|required|string|min:10|max:1000',
            'comment_ar' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $review = ServiceReview::findOrFail($id);
        $user = Auth::user();

        if ($review->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only update your own reviews',
            ], 403);
        }

        $review->update($request->only(['rating', 'comment', 'comment_ar']));
        $review->is_approved = false; // Reset approval status

        $review->save();

        return response()->json([
            'success' => true,
            'message' => 'Review updated successfully. It will be re-approved by admin.',
        ]);
    }

    /**
     * Delete a review
     *
     * @OA\Delete(
     *     path="/api/reviews/{id}",
     *     summary="Delete user's review",
     *     description="Delete an existing review. Only the review author can delete their review.",
     *     operationId="deleteReview",
     *     tags={"Service Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Review ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Review deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Not the review author"
     *     ),
     *     @OA\Response(response=404, description="Review not found")
     * )
     */
    public function destroy($id)
    {
        $review = ServiceReview::findOrFail($id);
        $user = Auth::user();

        if ($review->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete your own reviews',
            ], 403);
        }

        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully',
        ]);
    }
}