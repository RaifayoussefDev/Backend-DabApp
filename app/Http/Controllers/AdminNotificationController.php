<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\MassNotificationJob;
use App\Models\NotificationBatch;
use App\Models\User;
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
            'filters.is_verified' => 'nullable|boolean',
            'filters.gender' => 'nullable|string|in:male,female,other',
            'filters.country_id' => 'nullable|exists:countries,id',
            'filters.role_id' => 'nullable|exists:roles,id',
            'filters.category_id' => 'nullable|exists:categories,id',
            'filters.brand_in_garage' => 'nullable|exists:motorcycle_brands,id',
            'filters.has_points_of_interest' => 'nullable|boolean',
            'filters.last_login_from' => 'nullable|date',
            'filters.date_from' => 'nullable|date',
            'filters.date_to' => 'nullable|date',
            'channels' => 'nullable|array',
            'channels.*' => 'in:push,email',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $filters = $request->input('filters', []);
        $content = $request->input('content');
        $channels = $request->input('channels', ['push']); // Default to push if not specified
        $scheduledAt = $request->input('scheduled_at');

        // Cheap COUNT query regardless of how many users match — safe even at 100k+ users.
        // (Recomputed at fire time for scheduled broadcasts, since the audience can shift by then.)
        $totalTargeted = User::query()->applyFilters($filters)->count();

        $batch = NotificationBatch::create([
            'title_en'       => $content['title_en'],
            'title_ar'       => $content['title_ar'] ?? null,
            'body_en'        => $content['body_en'],
            'body_ar'        => $content['body_ar'] ?? null,
            'type'           => $content['type'] ?? 'info',
            'channels'       => $channels,
            'filters'        => $filters,
            'scheduled_at'   => $scheduledAt,
            'total_targeted' => $totalTargeted,
            'status'         => $scheduledAt ? 'scheduled' : 'pending',
            'created_by'     => $request->user()?->id ?? auth()->id(),
        ]);

        if ($scheduledAt) {
            // Held until notifications:dispatch-scheduled (runs every minute) picks it up —
            // nothing dispatched to the queue yet, so it can still be cancelled beforehand.
            return response()->json([
                'message' => 'Broadcast scheduled',
                'batch'   => $batch,
            ], 202);
        }

        // Dispatched to the queue — the actual sending (push/email per user) happens on a
        // queue worker, never blocking this HTTP request. This is what makes broadcasting
        // to any number of users (10, 10 000, or more) return instantly instead of timing out.
        MassNotificationJob::dispatch($batch->id, $filters, $content, $channels, $batch->created_by);

        return response()->json([
            'message' => 'Broadcast queued',
            'batch'   => $batch,
        ], 202);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/notifications/batches/{id}/cancel",
     *     summary="Cancel a scheduled broadcast before it fires",
     *     tags={"Admin Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Cancelled"),
     *     @OA\Response(response=400, description="Only scheduled broadcasts can be cancelled"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function cancelBatch(int $id)
    {
        $batch = NotificationBatch::find($id);

        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Batch not found'], 404);
        }

        if ($batch->status !== 'scheduled') {
            return response()->json(['success' => false, 'message' => 'Only a scheduled (not yet fired) broadcast can be cancelled'], 400);
        }

        $batch->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => 'Scheduled broadcast cancelled', 'data' => $batch->fresh()]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/notifications/batches",
     *     summary="List broadcast batches",
     *     description="Aggregate view of every mass-send broadcast — total targeted, sent, failed and read counts. Never lists individual recipients.",
     *     tags={"Admin Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Response(response=200, description="Batches retrieved")
     * )
     */
    public function batches(Request $request)
    {
        $batches = NotificationBatch::with('creator:id,first_name,last_name')
            ->withCount(['notifications as read_count' => fn ($q) => $q->where('is_read', true)])
            ->latest()
            ->paginate($request->get('per_page', 20));

        // User::$appends (is_dealer, points_of_interest, ...) survive column selection on with() —
        // hide them here so listing broadcasts doesn't drag in each admin's full POI records.
        // (makeHidden keeps this a real Model so it still serializes — a plain array via only()
        // silently disappears from the response since Eloquent's relation serializer only
        // handles Arrayable|null values.)
        $batches->getCollection()->each(function ($batch) {
            $batch->creator?->makeHidden(['is_dealer', 'dealer_title', 'dealer_address', 'dealer_phone', 'points_of_interest']);
        });

        return response()->json([
            'success' => true,
            'data' => $batches,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/notifications/batches/{id}",
     *     summary="Broadcast batch detail",
     *     tags={"Admin Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Batch retrieved"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function batchShow(int $id)
    {
        $batch = NotificationBatch::with('creator:id,first_name,last_name')->find($id);

        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Batch not found'], 404);
        }

        $batch->creator?->makeHidden(['is_dealer', 'dealer_title', 'dealer_address', 'dealer_phone', 'points_of_interest']);

        return response()->json([
            'success' => true,
            'data' => array_merge($batch->toArray(), ['read_count' => $batch->readCount()]),
        ]);
    }
}
