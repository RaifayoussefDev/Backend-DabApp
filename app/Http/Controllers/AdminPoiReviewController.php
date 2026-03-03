<?php

namespace App\Http\Controllers;

use App\Models\PoiReview;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\PointOfInterest;

/**
 * @OA\Tag(
 *     name="Admin POI Reviews",
 *     description="Admin API Endpoints for managing all POI reviews"
 * )
 */
class AdminPoiReviewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/poi-reviews",
     *     summary="Get all POI reviews (Admin)",
     *     tags={"Admin POI Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by comment",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="is_approved",
     *         in="query",
     *         description="Filter by approval status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="poi_id",
     *         in="query",
     *         description="Filter by POI",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page. Leave empty to get all results.",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = PoiReview::with(['user', 'pointOfInterest']);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('comment', 'LIKE', "%{$search}%");
        }

        if ($request->has('is_approved')) {
            $query->where('is_approved', $request->boolean('is_approved'));
        }

        if ($request->has('poi_id')) {
            $query->where('poi_id', $request->poi_id);
        }

        $perPage = $request->input('per_page');

        if ($perPage === null || $perPage === '') {
            $reviews = $query->latest()->get();
        } else {
            $reviews = $query->latest()->paginate((int) $perPage);
        }

        return response()->json([
            'success' => true,
            'data' => $reviews,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/poi-reviews/{id}",
     *     summary="Get a specific POI review (Admin)",
     *     tags={"Admin POI Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Review not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $review = PoiReview::with(['user', 'pointOfInterest'])->find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $review,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/poi-reviews/{id}",
     *     summary="Update a POI review (Admin)",
     *     tags={"Admin POI Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             example={
     *                 "rating": 5,
     *                 "comment": "Excellent place, loved the food!",
     *                 "is_approved": true
     *             },
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5),
     *             @OA\Property(property="comment", type="string"),
     *             @OA\Property(property="is_approved", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Review updated successfully"),
     *     @OA\Response(response=404, description="Review not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $review = PoiReview::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'is_approved' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $validated = $validator->validated();
            $review->update($validated);

            // Update POI rating average if rating or approval status changed
            if (isset($validated['rating']) || isset($validated['is_approved'])) {
                $this->updatePoiRating($review->pointOfInterest);
            }

            DB::commit();

            $review->load(['user', 'pointOfInterest']);

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully',
                'data' => $review,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review',
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/poi-reviews/{id}",
     *     summary="Delete a POI review (Admin)",
     *     tags={"Admin POI Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Review deleted successfully"),
     *     @OA\Response(response=404, description="Review not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $review = PoiReview::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
            ], 404);
        }

        DB::beginTransaction();
        try {
            $poi = $review->pointOfInterest;
            $review->delete();

            // Update POI rating average
            $this->updatePoiRating($poi);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/poi-reviews/stats/overview",
     *     summary="Get POI review statistics (Admin)",
     *     tags={"Admin POI Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_reviews' => PoiReview::count(),
            'approved_reviews' => PoiReview::where('is_approved', true)->count(),
            'pending_reviews' => PoiReview::where('is_approved', false)->count(),
            'average_rating' => PoiReview::approved()->avg('rating') ?: 0,
            'reviews_this_month' => PoiReview::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Update POI rating average and count
     */
    private function updatePoiRating(PointOfInterest $poi): void
    {
        $reviews = PoiReview::where('poi_id', $poi->id)
            ->approved()
            ->get();

        $poi->update([
            'rating_average' => $reviews->avg('rating') ?? 0,
            'reviews_count' => $reviews->count(),
        ]);
    }
}
