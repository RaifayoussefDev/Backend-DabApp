<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EventReview;
use Illuminate\Http\Request;

class AdminEventReviewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/event-reviews",
     *     summary="Admin: Get all reviews",
     *     tags={"Admin Event Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="event_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_approved", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="List of reviews",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EventReview"))
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = EventReview::with(['user', 'event']);

        if ($request->has('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        if ($request->has('is_approved')) {
            $query->where('is_approved', $request->boolean('is_approved'));
        }

        $reviews = $query->latest()->paginate(20);

        return response()->json($reviews);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/event-reviews/{id}/approve",
     *     summary="Admin: Approve a review",
     *     tags={"Admin Event Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Review approved")
     * )
     */
    public function approve($id)
    {
        $review = EventReview::findOrFail($id);
        $review->is_approved = true;
        $review->save();

        return response()->json(['message' => 'Review approved', 'data' => $review]);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/event-reviews/{id}/reject",
     *     summary="Admin: Reject (unapprove) a review",
     *     tags={"Admin Event Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Review rejected")
     * )
     */
    public function reject($id)
    {
        $review = EventReview::findOrFail($id);
        $review->is_approved = false;
        $review->save();

        return response()->json(['message' => 'Review rejected/hidden', 'data' => $review]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/event-reviews/{id}",
     *     summary="Admin: Delete a review",
     *     tags={"Admin Event Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Review deleted")
     * )
     */
    public function destroy($id)
    {
        $review = EventReview::findOrFail($id);
        $review->delete();

        return response()->json(['message' => 'Review deleted successfully']);
    }
}
