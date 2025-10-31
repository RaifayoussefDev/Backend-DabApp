<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PoiReview;
use App\Models\PointOfInterest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="POI Reviews",
 *     description="API Endpoints for POI Reviews"
 * )
 */
class PoiReviewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pois/{poi_id}/reviews",
     *     summary="Get all reviews for a POI",
     *     tags={"POI Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="poi_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="POI not found")
     * )
     */
    public function index(int $poiId): JsonResponse
    {
        $poi = PointOfInterest::find($poiId);

        if (!$poi) {
            return response()->json([
                'success' => false,
                'message' => 'Point of interest not found',
            ], 404);
        }

        $reviews = PoiReview::where('poi_id', $poiId)
            ->approved()
            ->with('user')
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $reviews,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/pois/{poi_id}/reviews",
     *     summary="Create a review for a POI",
     *     tags={"POI Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="poi_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rating"},
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5),
     *             @OA\Property(property="comment", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Review created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, int $poiId): JsonResponse
    {
        $poi = PointOfInterest::find($poiId);

        if (!$poi) {
            return response()->json([
                'success' => false,
                'message' => 'Point of interest not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if user already reviewed this POI
        $existingReview = PoiReview::where('poi_id', $poiId)
            ->where('user_id', auth()->id())
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this POI',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $review = PoiReview::create([
                'poi_id' => $poiId,
                'user_id' => auth()->id(),
                'rating' => $request->rating,
                'comment' => $request->comment,
            ]);

            // Update POI rating average
            $this->updatePoiRating($poi);

            DB::commit();

            $review->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Review created successfully',
                'data' => $review,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create review',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/pois/{poi_id}/reviews/{id}",
     *     summary="Update a review",
     *     tags={"POI Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="poi_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5),
     *             @OA\Property(property="comment", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Review updated successfully"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Review not found")
     * )
     */
    public function update(Request $request, int $poiId, int $id): JsonResponse
    {
        $review = PoiReview::where('poi_id', $poiId)->find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
            ], 404);
        }

        if ($review->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this review',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $review->update($validator->validated());

            // Update POI rating average
            $this->updatePoiRating($review->pointOfInterest);

            DB::commit();

            $review->load('user');

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
     *     path="/api/pois/{poi_id}/reviews/{id}",
     *     summary="Delete a review",
     *     tags={"POI Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="poi_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Review deleted successfully"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Review not found")
     * )
     */
    public function destroy(int $poiId, int $id): JsonResponse
    {
        $review = PoiReview::where('poi_id', $poiId)->find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
            ], 404);
        }

        if ($review->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this review',
            ], 403);
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
