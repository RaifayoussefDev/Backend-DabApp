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
     * @OA\Post(
     *     path="/api/admin/notification-preferences",
     *     tags={"Admin Notification Preferences"},
     *     summary="Create default preferences for a user",
     *     description="Useful if a user hasn't logged in yet and has no preferences record",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id"},
     *             @OA\Property(property="user_id", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Preferences created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/NotificationPreference")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id|unique:notification_preferences,user_id',
        ]);

        $preference = NotificationPreference::create([
            'user_id' => $request->user_id,
        ]);

        // Apply defaults if needed, but model/db should handle default values (usually true/false)
        // Check migration defaults? Assuming they are set. If not, we might want to enableAll/init.
        // For safety, let's enable all by default or rely on DB defaults.
        // $preference->enableAll(); // Optional: force enable all on create?

        return response()->json([
            'success' => true,
            'message' => 'Preferences created successfully',
            'data' => $preference,
        ], 201);
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
            'listing_updated' => 'sometimes|boolean',
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
            'guide_published' => 'sometimes|boolean',
            'guide_comment' => 'sometimes|boolean',
            'guide_like' => 'sometimes|boolean',
            'event_created' => 'sometimes|boolean',
            'event_published' => 'sometimes|boolean',
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
            'dealer_approved' => 'sometimes|boolean',
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
            'soom_counter_offer' => 'sometimes|boolean',
            'soom_accepted' => 'sometimes|boolean',
            'soom_rejected' => 'sometimes|boolean',
        ]);

        $preference->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'data' => $preference->fresh(),
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/notification-preferences/{id}/enable-all",
     *     tags={"Admin Notification Preferences"},
     *     summary="Enable all notifications for a user",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="All notifications enabled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="All notifications enabled"),
     *             @OA\Property(property="data", ref="#/components/schemas/NotificationPreference")
     *         )
     *     )
     * )
     */
    public function enableAll($id): JsonResponse
    {
        $preference = NotificationPreference::findOrFail($id);
        $preference->enableAll();
        return response()->json([
            'success' => true,
            'message' => 'All notifications enabled',
            'data' => $preference->fresh(),
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/notification-preferences/{id}/disable-all",
     *     tags={"Admin Notification Preferences"},
     *     summary="Disable all notifications for a user",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="All notifications disabled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="All notifications disabled"),
     *             @OA\Property(property="data", ref="#/components/schemas/NotificationPreference")
     *         )
     *     )
     * )
     */
    /**
     * @OA\Post(
     *     path="/api/admin/notification-preferences/mass-update",
     *     tags={"Admin Notification Preferences"},
     *     summary="Update preferences for ALL users",
     *     description="Apply specific preference settings to ALL users in the system. Use with caution.",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             example={"soom_new_negotiation": true, "push_enabled": true},
     *             @OA\Property(property="listing_approved", type="boolean"),
     *             @OA\Property(property="soom_new_negotiation", type="boolean"),
     *             @OA\Property(property="push_enabled", type="boolean"),
     *             @OA\Property(property="enable_all", type="boolean", description="If true, enables ALL notifications for everyone")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mass update successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Updated 1500 users choices")
     *         )
     *     )
     * )
     */
    public function massUpdate(Request $request): JsonResponse
    {
        // If "enable_all" is present and true, we enable everything
        if ($request->input('enable_all')) {
            // This might be heavy, so we can use a direct DB update for speed
            // or just iterate (but large userbase = slow).
            // DB Update is safer for "Create Default" logic if we assume rows exist.
            // If rows don't exist, we can't update them here easily without creating them first.
            // Assuming most users have prefs or we update those who have.

            // For now, let's update existing records.
            NotificationPreference::query()->update([
                'listing_approved' => true,
                'listing_rejected' => true,
                'listing_expired' => true,
                'listing_sold' => true,
                'listing_updated' => true,
                'bid_placed' => true,
                'bid_accepted' => true,
                'bid_rejected' => true,
                'bid_outbid' => true,
                'auction_ending_soon' => true,
                'soom_new_negotiation' => true,
                'soom_counter_offer' => true,
                'soom_accepted' => true,
                'soom_rejected' => true,
                'dealer_approved' => true,
                'payment_success' => true,
                'payment_failed' => true,
                'payment_pending' => true,
                'wishlist_price_drop' => true,
                'wishlist_item_sold' => true,
                'new_message' => true,
                'new_guide_published' => true,
                'guide_published' => true,
                'guide_comment' => true,
                'guide_like' => true,
                'event_created' => true,
                'event_published' => true,
                'event_reminder' => true,
                'event_updated' => true,
                'event_cancelled' => true,
                'poi_review' => true,
                'new_poi_nearby' => true,
                'route_comment' => true,
                'route_warning' => true,
                'system_updates' => true,
                'promotional' => true,
                'newsletter' => true,
                'admin_custom' => true,
                'push_enabled' => true,
                'in_app_enabled' => true,
                'email_enabled' => true,
                'sms_enabled' => true,
                'quiet_hours_enabled' => false, // Usually disable quiet hours if enabling all?
            ]);

            return response()->json(['success' => true, 'message' => 'All notifications enabled for all users (who had preference records)']);
        }

        // Partial Update
        $validated = $request->validate([
            'listing_approved' => 'sometimes|boolean',
            'push_enabled' => 'sometimes|boolean',
            'soom_new_negotiation' => 'sometimes|boolean',
            // Add other fields as needed, or allow all generic
        ]);

        // Filter out nulls/non-present keys
        $dataToUpdate = $request->except(['enable_all']);

        // Security: Ensure only valid columns are updated by checking against schema or model fillable?
        // Using $fillable is safer, but $request->except might include garbage.
        // Let's rely on strict validation or intersection with fillable.

        $preferenceModel = new NotificationPreference();
        $fillable = $preferenceModel->getFillable();
        $validData = array_intersect_key($dataToUpdate, array_flip($fillable));

        if (empty($validData)) {
            return response()->json(['success' => false, 'message' => 'No valid fields provided'], 400);
        }

        NotificationPreference::query()->update($validData);

        return response()->json([
            'success' => true,
            'message' => 'Updated preferences for all users',
            'fields_updated' => array_keys($validData)
        ]);
    }
}
