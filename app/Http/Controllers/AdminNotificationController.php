<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\MassNotificationJob;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin Notifications",
 *     description="API Endpoints for Admin Mass Notifications"
 * )
 */
class AdminNotificationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/notifications",
     *     summary="Get all notifications list",
     *     description="Retrieve a paginated list of all notifications sent to users, including read status",
     *     tags={"Admin Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by notification type",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notifications retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Notifications retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="message", type="string"),
     *                     @OA\Property(property="type", type="string"),
     *                     @OA\Property(property="is_read", type="boolean"),
     *                     @OA\Property(property="read_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="first_name", type="string"),
     *                         @OA\Property(property="last_name", type="string"),
     *                         @OA\Property(property="email", type="string")
     *                     )
     *                 )),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = \App\Models\Notification::with('user');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'message' => 'Notifications retrieved successfully',
            'data' => $notifications
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/notifications/mass-send",
     *     summary="Send mass notifications to filtered users",
     *     tags={"Admin Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"content"},
     *             @OA\Property(
     *                 property="filters",
     *                 type="object",
     * 
     *                 @OA\Property(property="user_ids", type="array", @OA\Items(type="integer"), description="List of User IDs for direct notification"),
     *                 @OA\Property(property="country_id", type="integer"),
     *                 @OA\Property(property="category_id", type="integer"),
     *                 @OA\Property(property="has_listing", type="boolean"),
     *                 @OA\Property(property="brand_in_garage", type="integer", description="Brand ID in garage"),
     *                 @OA\Property(property="date_from", type="string", format="date"),
     *                 @OA\Property(property="date_to", type="string", format="date")
     *             ),
     *             @OA\Property(
     *                 property="content",
     *                 type="object",
     *                 required={"title_en", "body_en"},
     *                 @OA\Property(property="title_en", type="string", example="New Promotion"),
     *                 @OA\Property(property="title_ar", type="string", example="عروض جديدة"),
     *                 @OA\Property(property="body_en", type="string", example="Check out our latest offers!"),
     *                 @OA\Property(property="body_ar", type="string", example="تحقق من أحدث عروضنا!"),
     *                 @OA\Property(property="type", type="string", enum={"promo", "news", "info"}, default="info")
     *             ),
     *             @OA\Property(
     *                 property="channels",
     *                 type="array",
     *                 @OA\Items(type="string", enum={"push", "email"})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification job dispatched",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="filters", type="object")
     *         )
     *     )
     * )
     */
    public function sendMassNotification(Request $request)
    {
        // 1. Validation
        $validator = Validator::make($request->all(), [
            'content.title_en' => 'required|string|max:255',
            'content.body_en' => 'required|string',
            'content.title_ar' => 'nullable|string|max:255',
            'content.body_ar' => 'nullable|string',
            'content.type' => 'nullable|string|in:promo,news,info',
            'filters.user_ids' => 'nullable|array',
            'filters.user_ids.*' => 'exists:users,id',
            'filters.country_id' => 'nullable|exists:countries,id',
            'filters.category_id' => 'nullable|exists:categories,id',
            'filters.has_listing' => 'nullable|boolean',
            'filters.brand_in_garage' => 'nullable|exists:motorcycle_brands,id',
            'filters.date_from' => 'nullable|date',
            'filters.date_to' => 'nullable|date',
            'channels' => 'nullable|array',
            'channels.*' => 'in:push,email'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $filters = $request->input('filters', []);
        $content = $request->input('content');
        $channels = $request->input('channels', ['push']); // Default to push if not specified

        // 2. Dispatch Job
        // 2. Dispatch Job
        // Using dispatchSync for debugging/immediate execution
        MassNotificationJob::dispatchSync($filters, $content, $channels);

        return response()->json([
            'message' => 'Mass notification job dispatched successfully',
            'filters' => $filters,
            'recipients_estimate' => 'Processing in background'
        ]);
    }
}
