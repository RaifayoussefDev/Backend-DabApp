<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ServiceReview;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Admin Service Reviews",
 *     description="API endpoints for moderating service reviews (Admin)"
 * )
 */
class AdminServiceReviewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/service-reviews",
     *     summary="List reviews (Admin)",
     *     operationId="adminGetServiceReviews",
     *     tags={"Admin Service Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="service_id", in="query", description="Filter by service", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="provider_id", in="query", description="Filter by provider", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_approved", in="query", description="Filter by approval status", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="Reviews retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=456),
     *                         @OA\Property(property="rating", type="integer", example=5),
     *                         @OA\Property(property="comment", type="string", example="Excellent service!"),
     *                         @OA\Property(property="is_approved", type="boolean", example=false),
     *                         @OA\Property(property="user", type="object", @OA\Property(property="name", type="string", example="Alice Smith")),
     *                         @OA\Property(property="service", type="object", @OA\Property(property="name", type="string", example="Car Repair"))
     *                     )
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=32)
     *             ),
     *             @OA\Property(property="message", type="string", example="Reviews retrieved successfully")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = ServiceReview::with(['service', 'user', 'booking']);

        if ($request->has('service_id')) {
            $query->where('service_id', $request->service_id);
        }

        if ($request->has('provider_id')) {
            $query->whereHas('service', function ($q) use ($request) {
                $q->where('provider_id', $request->provider_id);
            });
        }

        if ($request->has('is_approved')) {
            $query->where('is_approved', $request->boolean('is_approved'));
        }

        $perPage = $request->input('per_page');

        if ($perPage) {
            $reviews = $query->orderBy('created_at', 'desc')->paginate($perPage);
        } else {
            $reviews = $query->orderBy('created_at', 'desc')->get();
        }

        return response()->json([
            'success' => true,
            'data' => $reviews,
            'message' => 'Reviews retrieved successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/service-reviews/{id}/approve",
     *     summary="Approve review (Admin)",
     *     operationId="adminApproveReview",
     *     tags={"Admin Service Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Review approved")
     * )
     */
    public function approve($id)
    {
        $review = ServiceReview::findOrFail($id);
        $review->update(['is_approved' => true]);

        return response()->json(['success' => true, 'message' => 'Review approved successfully']);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/service-reviews/{id}",
     *     summary="Delete review (Admin)",
     *     operationId="adminDeleteReview",
     *     tags={"Admin Service Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Review deleted")
     * )
     */
    public function destroy($id)
    {
        $review = ServiceReview::findOrFail($id);
        $review->delete();

        return response()->json(['success' => true, 'message' => 'Review deleted successfully']);
    }
}
