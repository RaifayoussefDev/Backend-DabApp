<?php

namespace App\Http\Controllers;

use App\Models\GuestNotificationToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Guest Notification Tokens",
 *     description="FCM token registration for guest (not-logged-in) devices"
 * )
 */
class GuestNotificationTokenController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/guest/notification-tokens",
     *     tags={"Guest Notification Tokens"},
     *     summary="Register / update a guest device push token",
     *     description="Public endpoint. Upserts on device_id so calling it on every launch is safe.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"device_id", "fcm_token", "device_type"},
     *             @OA\Property(property="device_id", type="string", example="a1b2c3-stable-install-id"),
     *             @OA\Property(property="fcm_token", type="string"),
     *             @OA\Property(property="device_type", type="string", enum={"ios", "android", "web", "huawei"}),
     *             @OA\Property(property="device_name", type="string"),
     *             @OA\Property(property="device_model", type="string"),
     *             @OA\Property(property="os_version", type="string"),
     *             @OA\Property(property="app_version", type="string"),
     *             @OA\Property(property="locale", type="string", example="ar"),
     *             @OA\Property(property="country_id", type="integer"),
     *             @OA\Property(property="city_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Token updated"),
     *     @OA\Response(response=201, description="Token registered")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:255',
            'fcm_token' => 'required|string',
            'device_type' => 'required|in:ios,android,web,huawei',
            'device_name' => 'nullable|string|max:255',
            'device_model' => 'nullable|string|max:255',
            'os_version' => 'nullable|string|max:50',
            'app_version' => 'nullable|string|max:50',
            'locale' => 'nullable|string|max:10',
            'country_id' => 'nullable|exists:countries,id',
            'city_id' => 'nullable|exists:cities,id',
        ]);

        $existing = GuestNotificationToken::where('device_id', $validated['device_id'])->first();

        $attributes = array_merge($validated, [
            'is_active' => true,
            'last_active_at' => now(),
            'failed_attempts' => 0,
            'converted_user_id' => null,
        ]);

        if ($existing) {
            $existing->update($attributes);

            return response()->json([
                'success' => true,
                'message' => 'Guest token updated',
                'data' => ['id' => $existing->id, 'device_type' => $existing->device_type, 'is_active' => true],
            ]);
        }

        $token = GuestNotificationToken::create($attributes);

        return response()->json([
            'success' => true,
            'message' => 'Guest token registered',
            'data' => ['id' => $token->id, 'device_type' => $token->device_type, 'is_active' => true],
        ], 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/guest/notification-tokens/{deviceId}",
     *     tags={"Guest Notification Tokens"},
     *     summary="Deactivate a guest device push token (opt-out / uninstall)",
     *     @OA\Parameter(name="deviceId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Token deactivated")
     * )
     */
    public function destroy(string $deviceId): JsonResponse
    {
        $token = GuestNotificationToken::where('device_id', $deviceId)->first();

        if ($token) {
            $token->deactivate();
        }

        return response()->json(['success' => true, 'message' => 'Guest token deactivated']);
    }
}
