<?php
// app/Http/Controllers/Api/NotificationPreferenceController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Notification Preferences",
 *     description="Gestion des préférences de notifications"
 * )
 */
class NotificationPreferenceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/notification-preferences",
     *     tags={"Notification Preferences"},
     *     summary="Récupérer mes préférences",
     *     description="Récupère les préférences de notifications de l'utilisateur",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Préférences récupérées",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="listing_approved", type="boolean"),
     *                 @OA\Property(property="bid_placed", type="boolean"),
     *                 @OA\Property(property="push_enabled", type="boolean"),
     *                 @OA\Property(property="quiet_hours_enabled", type="boolean")
     *             )
     *         )
     *     )
     * )
     */
    public function show(): JsonResponse
    {
        $preferences = auth()->user()->notificationPreference;

        if (!$preferences) {
            // Créer les préférences par défaut
            $preferences = NotificationPreference::create([
                'user_id' => auth()->id(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $preferences,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/notification-preferences",
     *     tags={"Notification Preferences"},
     *     summary="Mettre à jour mes préférences",
     *     description="Met à jour les préférences de notifications",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="listing_approved", type="boolean", example=true),
     *             @OA\Property(property="listing_rejected", type="boolean", example=true),
     *             @OA\Property(property="bid_placed", type="boolean", example=true),
     *             @OA\Property(property="new_message", type="boolean", example=true),
     *             @OA\Property(property="push_enabled", type="boolean", example=true),
     *             @OA\Property(property="quiet_hours_enabled", type="boolean", example=false),
     *             @OA\Property(property="quiet_hours_start", type="string", example="22:00:00"),
     *             @OA\Property(property="quiet_hours_end", type="string", example="08:00:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Préférences mises à jour",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Listings
            'listing_approved' => 'sometimes|boolean',
            'listing_rejected' => 'sometimes|boolean',
            'listing_expired' => 'sometimes|boolean',
            'listing_sold' => 'sometimes|boolean',
            // Auctions
            'bid_placed' => 'sometimes|boolean',
            'bid_accepted' => 'sometimes|boolean',
            'bid_rejected' => 'sometimes|boolean',
            'bid_outbid' => 'sometimes|boolean',
            'auction_ending_soon' => 'sometimes|boolean',
            // Payments
            'payment_success' => 'sometimes|boolean',
            'payment_failed' => 'sometimes|boolean',
            'payment_pending' => 'sometimes|boolean',
            // Wishlist
            'wishlist_price_drop' => 'sometimes|boolean',
            'wishlist_item_sold' => 'sometimes|boolean',
            // Messages
            'new_message' => 'sometimes|boolean',
            // Guides
            'new_guide_published' => 'sometimes|boolean',
            'guide_comment' => 'sometimes|boolean',
            'guide_like' => 'sometimes|boolean',
            // Events
            'event_reminder' => 'sometimes|boolean',
            'event_updated' => 'sometimes|boolean',
            'event_cancelled' => 'sometimes|boolean',
            // POI
            'poi_review' => 'sometimes|boolean',
            'new_poi_nearby' => 'sometimes|boolean',
            // Routes
            'route_comment' => 'sometimes|boolean',
            'route_warning' => 'sometimes|boolean',
            // System
            'system_updates' => 'sometimes|boolean',
            'promotional' => 'sometimes|boolean',
            'newsletter' => 'sometimes|boolean',
            'admin_custom' => 'sometimes|boolean',
            // Channels
            'push_enabled' => 'sometimes|boolean',
            'in_app_enabled' => 'sometimes|boolean',
            'email_enabled' => 'sometimes|boolean',
            'sms_enabled' => 'sometimes|boolean',
            // Quiet hours
            'quiet_hours_enabled' => 'sometimes|boolean',
            'quiet_hours_start' => 'sometimes|date_format:H:i:s|nullable',
            'quiet_hours_end' => 'sometimes|date_format:H:i:s|nullable',
            // Push settings
            'push_vibration' => 'sometimes|boolean',
            'push_sound' => 'sometimes|boolean',
            'push_badge' => 'sometimes|boolean',
            'push_priority' => 'sometimes|in:default,high',
        ]);

        $preferences = auth()->user()->notificationPreference;

        if (!$preferences) {
            $preferences = NotificationPreference::create([
                'user_id' => auth()->id(),
            ]);
        }

        $preferences->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'data' => $preferences->fresh(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/notification-preferences/enable-all",
     *     tags={"Notification Preferences"},
     *     summary="Activer toutes les notifications",
     *     description="Active toutes les préférences de notifications",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Toutes les notifications activées",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function enableAll(): JsonResponse
    {
        $preferences = auth()->user()->notificationPreference;

        if (!$preferences) {
            $preferences = NotificationPreference::create([
                'user_id' => auth()->id(),
            ]);
        }

        $preferences->enableAll();

        return response()->json([
            'success' => true,
            'message' => 'All notifications enabled',
            'data' => $preferences->fresh(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/notification-preferences/disable-all",
     *     tags={"Notification Preferences"},
     *     summary="Désactiver toutes les notifications",
     *     description="Désactive toutes les préférences de notifications",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Toutes les notifications désactivées",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function disableAll(): JsonResponse
    {
        $preferences = auth()->user()->notificationPreference;

        if (!$preferences) {
            $preferences = NotificationPreference::create([
                'user_id' => auth()->id(),
            ]);
        }

        $preferences->disableAll();

        return response()->json([
            'success' => true,
            'message' => 'All notifications disabled',
            'data' => $preferences->fresh(),
        ]);
    }
}
