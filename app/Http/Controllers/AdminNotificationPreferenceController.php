<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Admin Notification Preferences",
 *     description="Admin management of user notification preferences"
 * )
 */
class AdminNotificationPreferenceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/notification-preferences",
     *     tags={"Admin Notification Preferences"},
     *     summary="Get all notification preferences",
     *     description="Retrieve list of notification preferences with pagination",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page (leave empty for all)", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="user_id", in="query", description="Filter by User ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="List of preferences",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(type="array", @OA\Items(ref="#/components/schemas/NotificationPreference")),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/NotificationPreference")),
     *                     @OA\Property(property="meta", type="object"),
     *                     @OA\Property(property="links", type="object")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = NotificationPreference::with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('per_page')) {
            $preferences = $query->paginate((int) $request->per_page);
        } else {
            $preferences = $query->get();
        }

        return response()->json($preferences);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/notification-preferences/{id}",
     *     tags={"Admin Notification Preferences"},
     *     summary="Get specific preference by ID",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Preference details")
     * )
     */
    public function show($id): JsonResponse
    {
        $preference = NotificationPreference::with('user')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $preference]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/notification-preferences/{id}",
     *     tags={"Admin Notification Preferences"},
     *     summary="Update specific preference",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             example={
     *                 "listing_approved": true,
     *                 "listing_rejected": true,
     *                 "bid_placed": false,
     *                 "soom_new_negotiation": true,
     *                 "push_enabled": true,
     *                 "email_enabled": false,
     *                 "quiet_hours_enabled": true,
     *                 "quiet_hours_start": "22:00:00",
     *                 "quiet_hours_end": "08:00:00"
     *             },
     *             @OA\Property(property="listing_approved", type="boolean"),
     *             @OA\Property(property="listing_rejected", type="boolean"),
     *             @OA\Property(property="soom_new_negotiation", type="boolean"),
     *             @OA\Property(property="bid_placed", type="boolean"),
     *             @OA\Property(property="push_enabled", type="boolean"),
     *             @OA\Property(property="email_enabled", type="boolean"),
     *             @OA\Property(property="quiet_hours_enabled", type="boolean"),
     *             @OA\Property(property="quiet_hours_start", type="string", format="time", example="22:00:00"),
     *             @OA\Property(property="quiet_hours_end", type="string", format="time", example="08:00:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Preferences updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/NotificationPreference")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id): JsonResponse
    {
        $preference = NotificationPreference::findOrFail($id);

        $validated = $request->validate([
            'listing_approved' => 'sometimes|boolean',
            'listing_rejected' => 'sometimes|boolean',
            'listing_expired' => 'sometimes|boolean',
            'listing_sold' => 'sometimes|boolean',
            'bid_placed' => 'sometimes|boolean',
            'bid_accepted' => 'sometimes|boolean',
            'bid_rejected' => 'sometimes|boolean',
            'bid_outbid' => 'sometimes|boolean',
            'auction_ending_soon' => 'sometimes|boolean',
            'payment_success' => 'sometimes|boolean',
            'payment_failed' => 'sometimes|boolean',
            'payment_pending' => 'sometimes|boolean',
            'wishlist_price_drop' => 'sometimes|boolean',
            'wishlist_item_sold' => 'sometimes|boolean',
            'new_message' => 'sometimes|boolean',
            'new_guide_published' => 'sometimes|boolean',
            'guide_comment' => 'sometimes|boolean',
            'guide_like' => 'sometimes|boolean',
            'event_reminder' => 'sometimes|boolean',
            'event_updated' => 'sometimes|boolean',
            'event_cancelled' => 'sometimes|boolean',
            'poi_review' => 'sometimes|boolean',
            'new_poi_nearby' => 'sometimes|boolean',
            'route_comment' => 'sometimes|boolean',
            'route_warning' => 'sometimes|boolean',
            'system_updates' => 'sometimes|boolean',
            'promotional' => 'sometimes|boolean',
            'newsletter' => 'sometimes|boolean',
            'admin_custom' => 'sometimes|boolean',
            'push_enabled' => 'sometimes|boolean',
            'in_app_enabled' => 'sometimes|boolean',
            'email_enabled' => 'sometimes|boolean',
            'sms_enabled' => 'sometimes|boolean',
            'quiet_hours_enabled' => 'sometimes|boolean',
            'quiet_hours_start' => 'sometimes|date_format:H:i:s|nullable',
            'quiet_hours_end' => 'sometimes|date_format:H:i:s|nullable',
            'push_vibration' => 'sometimes|boolean',
            'push_sound' => 'sometimes|boolean',
            'push_badge' => 'sometimes|boolean',
            'push_priority' => 'sometimes|in:default,high',
            'soom_new_negotiation' => 'sometimes|boolean',
        ]);

        $preference->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'data' => $preference->fresh(),
        ]);
    }
}
