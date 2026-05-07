<?php

namespace App\Http\Controllers\Assist;

use App\Models\Assist\AssistNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Assist - Notifications",
 *     description="In-app notifications for the Assist module (seekers and helpers)"
 * )
 */
class AssistNotificationController extends AssistBaseController
{
    /**
     * @OA\Get(
     *     path="/api/assist/notifications",
     *     summary="List the authenticated user's Assist notifications",
     *     description="Returns a paginated list of all Assist notifications for the current user (seeker or helper), sorted newest first. Use `?unread=1` to filter only unread ones.",
     *     tags={"Assist - Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="unread",
     *         in="query",
     *         required=false,
     *         description="Pass 1 to return only unread notifications",
     *         @OA\Schema(type="integer", enum={0,1}, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of notifications",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_page",   type="integer", example=1),
     *                 @OA\Property(property="per_page",       type="integer", example=20),
     *                 @OA\Property(property="total",          type="integer", example=5),
     *                 @OA\Property(property="unread_count",   type="integer", example=3,
     *                     description="Total number of unread notifications (always returned regardless of filter)"),
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",         type="integer", example=122),
     *                         @OA\Property(property="type",       type="string",  example="accepted",
     *                             description="Raw type: new_request | accepted | en_route | arrived | completed | cancelled | helper_cancelled | rated | seeker_finished"),
     *                         @OA\Property(property="title",      type="string",  example="Helper is on the way!"),
     *                         @OA\Property(property="body",       type="string",  example="Your request has been accepted. Help is coming."),
     *                         @OA\Property(property="is_read",    type="boolean", example=false),
     *                         @OA\Property(property="request_id", type="integer", nullable=true, example=28),
     *                         @OA\Property(property="created_at", type="string",  format="date-time", example="2026-05-07T12:47:07.000000Z")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(): JsonResponse
    {
        $query = AssistNotification::where('user_id', Auth::id())
            ->orderByDesc('created_at');

        if (request()->boolean('unread')) {
            $query->where('is_read', false);
        }

        $unreadCount = AssistNotification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->count();

        $paginated = $query->paginate(20);

        $data            = $paginated->toArray();
        $data['unread_count'] = $unreadCount;

        return $this->success($data);
    }

    /**
     * @OA\Patch(
     *     path="/api/assist/notifications/{id}/read",
     *     summary="Mark a single notification as read",
     *     tags={"Assist - Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Notification ID",
     *         @OA\Schema(type="integer", example=122)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification marked as read",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Notification marked as read.")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Notification not found")
     * )
     */
    public function markRead(string $id): JsonResponse
    {
        $notification = AssistNotification::where('user_id', Auth::id())
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return $this->error('Notification not found.', 404);
        }

        $notification->update(['is_read' => true]);

        return $this->success(null, 'Notification marked as read.');
    }

    /**
     * @OA\Patch(
     *     path="/api/assist/notifications/read-all",
     *     summary="Mark all notifications as read",
     *     tags={"Assist - Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="All notifications marked as read",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="All notifications marked as read."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="updated", type="integer", example=3,
     *                     description="Number of notifications that were marked as read")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function markAllRead(): JsonResponse
    {
        $updated = AssistNotification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return $this->success(['updated' => $updated], 'All notifications marked as read.');
    }

    /**
     * @OA\Get(
     *     path="/api/assist/notifications/unread-count",
     *     summary="Get the unread notifications count",
     *     description="Lightweight endpoint — call this on app resume to update the badge count without loading the full list.",
     *     tags={"Assist - Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Unread count",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="unread_count", type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function unreadCount(): JsonResponse
    {
        $count = AssistNotification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->count();

        return $this->success(['unread_count' => $count]);
    }
}
