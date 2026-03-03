<?php

namespace App\Http\Controllers;

use App\Models\RouteReview;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Admin - Route Reviews",
 *     description="API Endpoints for Managing Route Reviews by Admins"
 * )
 */
class AdminRouteReviewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/route-reviews",
     *     summary="Get all route reviews (Admin)",
     *     tags={"Admin - Route Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page. Leave empty to get all items.",
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
     *     @OA\Parameter(
     *         name="route_id",
     *         in="query",
     *         description="Filter by route ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (pending, approved, rejected)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = RouteReview::query()->with(['user', 'route']);

        if ($request->has('route_id') && !empty($request->route_id)) {
            $query->where('route_id', $request->route_id);
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $query->latest();

        if ($request->has('per_page') && $request->per_page != '') {
            $perPage = $request->input('per_page', 15);
            $reviews = $query->paginate($perPage);
        } else {
            $reviews = $query->get();
        }

        return response()->json([
            'success' => true,
            'data' => $reviews,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/route-reviews/{id}",
     *     summary="Get a specific route review (Admin)",
     *     tags={"Admin - Route Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Review not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $review = RouteReview::with(['user', 'route'])->find($id);

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
     *     path="/api/admin/route-reviews/{id}/status",
     *     summary="Update review status (Admin)",
     *     tags={"Admin - Route Reviews"},
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
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"pending", "approved", "rejected"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Status updated successfully"),
     *     @OA\Response(response=404, description="Review not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $review = RouteReview::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
            ], 404);
        }

        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $review->update(['status' => $request->status]);

        // Update route rating
        if ($route = $review->route) {
            $routeReviews = RouteReview::where('route_id', $route->id)->approved()->get();
            $route->update([
                'rating_average' => $routeReviews->avg('rating') ?? 0,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Review status updated successfully',
            'data' => $review->fresh(),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/route-reviews/{id}",
     *     summary="Delete a route review (Admin)",
     *     tags={"Admin - Route Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Review deleted successfully"),
     *     @OA\Response(response=404, description="Review not found")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $review = RouteReview::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
            ], 404);
        }

        $route = $review->route;
        $review->delete();

        // Update route rating
        if ($route) {
            $routeReviews = RouteReview::where('route_id', $route->id)->approved()->get();
            $route->update([
                'rating_average' => $routeReviews->avg('rating') ?? 0,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Route review deleted successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/route-reviews/stats/overview",
     *     summary="Get route reviews statistics (Admin)",
     *     tags={"Admin - Route Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
    public function stats(): JsonResponse
    {
        $totalReviews = RouteReview::count();
        $approvedReviews = RouteReview::where('status', 'approved')->count();
        $pendingReviews = RouteReview::where('status', 'pending')->count();
        $rejectedReviews = RouteReview::where('status', 'rejected')->count();
        $averageRating = RouteReview::where('status', 'approved')->avg('rating');

        return response()->json([
            'success' => true,
            'data' => [
                'total_reviews' => $totalReviews,
                'approved_reviews' => $approvedReviews,
                'pending_reviews' => $pendingReviews,
                'rejected_reviews' => $rejectedReviews,
                'average_rating_overall' => round($averageRating ?? 0, 2),
            ]
        ]);
    }
}
