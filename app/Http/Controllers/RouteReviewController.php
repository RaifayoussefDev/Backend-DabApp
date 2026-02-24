<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\RouteReview;
use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Route Reviews",
 *     description="API Endpoints for Route Reviews"
 * )
 */
class RouteReviewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/routes/{route_id}/reviews",
     *     summary="Get all reviews for a route",
     *     tags={"Route Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function index(int $routeId): JsonResponse
    {
        $route = Route::find($routeId);

        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Route not found',
            ], 404);
        }

        $reviews = RouteReview::where('route_id', $routeId)
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
     *     path="/api/routes/{route_id}/reviews",
     *     summary="Create a review for a route",
     *     tags={"Route Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=201, description="Review created successfully")
     * )
     */
    public function store(Request $request, int $routeId): JsonResponse
    {
        $route = Route::find($routeId);

        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Route not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'completed_date' => 'nullable|date',
            'weather_condition' => 'nullable|in:sunny,cloudy,rainy',
            'traffic_level' => 'nullable|in:light,moderate,heavy',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $review = RouteReview::create([
                'route_id' => $routeId,
                'user_id' => auth()->id(),
                ...$validator->validated()
            ]);

            // Update route rating
            $this->updateRouteRating($route);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Review created successfully',
                'data' => $review->load('user'),
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
     * @OA\Delete(
     *     path="/api/routes/{route_id}/reviews/{id}",
     *     summary="Delete a review",
     *     tags={"Route Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Review deleted successfully")
     * )
     */
    public function destroy(int $routeId, int $id): JsonResponse
    {
        $review = RouteReview::where('route_id', $routeId)->find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
            ], 404);
        }

        if ($review->user_id !== auth()->id() && !auth()->user()->hasPermission('manage_reviews')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        DB::beginTransaction();
        try {
            $route = $review->route;
            $review->delete();
            $this->updateRouteRating($route);
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

    private function updateRouteRating(Route $route): void
    {
        $reviews = RouteReview::where('route_id', $route->id)->approved()->get();

        $route->update([
            'rating_average' => $reviews->avg('rating') ?? 0,
        ]);
    }
}
