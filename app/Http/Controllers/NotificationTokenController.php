<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\NotificationToken;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Notification Tokens",
 *     description="Gestion des tokens FCM pour les notifications push"
 * )
 */
class NotificationTokenController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    /**
     * @OA\Post(
     *     path="/api/notification-tokens",
     *     tags={"Notification Tokens"},
     *     summary="Enregistrer un token FCM",
     *     description="Enregistre ou met à jour le token FCM pour recevoir les notifications push",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"fcm_token", "device_type"},
     *             @OA\Property(property="fcm_token", type="string", example="fX7Yk...token..."),
     *             @OA\Property(property="device_type", type="string", enum={"ios", "android", "web", "huawei"}),
     *             @OA\Property(property="device_name", type="string", example="iPhone 14 Pro"),
     *             @OA\Property(property="device_id", type="string", example="ABC123"),
     *             @OA\Property(property="device_model", type="string", example="iPhone14,3"),
     *             @OA\Property(property="os_version", type="string", example="16.4"),
     *             @OA\Property(property="app_version", type="string", example="1.0.0")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token enregistré avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="device_type", type="string"),
     *                 @OA\Property(property="is_active", type="boolean")
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string',
            'device_type' => 'required|in:ios,android,web,huawei',
            'device_name' => 'nullable|string|max:255',
            'device_id' => 'nullable|string|max:255',
            'device_model' => 'nullable|string|max:255',
            'os_version' => 'nullable|string|max:50',
            'app_version' => 'nullable|string|max:50',
        ]);

        $user = auth()->user();

        // Vérifier si le token existe déjà
        $token = NotificationToken::where('user_id', $user->id)
            ->where('fcm_token', $validated['fcm_token'])
            ->first();

        if ($token) {
            // Mettre à jour le token existant
            $token->update([
                'device_type' => $validated['device_type'],
                'device_name' => $validated['device_name'] ?? $token->device_name,
                'device_id' => $validated['device_id'] ?? $token->device_id,
                'device_model' => $validated['device_model'] ?? $token->device_model,
                'os_version' => $validated['os_version'] ?? $token->os_version,
                'app_version' => $validated['app_version'] ?? $token->app_version,
                'is_active' => true,
                'last_used_at' => now(),
                'failed_attempts' => 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token updated successfully',
                'data' => [
                    'id' => $token->id,
                    'device_type' => $token->device_type,
                    'is_active' => $token->is_active,
                ],
            ]);
        }

        // Créer un nouveau token
        $token = NotificationToken::create([
            'user_id' => $user->id,
            'fcm_token' => $validated['fcm_token'],
            'device_type' => $validated['device_type'],
            'device_name' => $validated['device_name'] ?? null,
            'device_id' => $validated['device_id'] ?? null,
            'device_model' => $validated['device_model'] ?? null,
            'os_version' => $validated['os_version'] ?? null,
            'app_version' => $validated['app_version'] ?? null,
            'is_active' => true,
            'last_used_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token registered successfully',
            'data' => [
                'id' => $token->id,
                'device_type' => $token->device_type,
                'is_active' => $token->is_active,
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/notification-tokens",
     *     tags={"Notification Tokens"},
     *     summary="Lister mes tokens",
     *     description="Récupère tous les tokens FCM de l'utilisateur connecté",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des tokens",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="device_type", type="string"),
     *                     @OA\Property(property="device_name", type="string"),
     *                     @OA\Property(property="is_active", type="boolean"),
     *                     @OA\Property(property="last_used_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $tokens = auth()->user()->notificationTokens()
            ->select(['id', 'device_type', 'device_name', 'device_model', 'is_active', 'last_used_at', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tokens,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/notification-tokens/{id}",
     *     tags={"Notification Tokens"},
     *     summary="Supprimer un token",
     *     description="Désactive un token FCM (déconnexion de l'appareil)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token supprimé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $token = NotificationToken::where('user_id', auth()->id())
            ->where('id', $id)
            ->firstOrFail();

        $token->deactivate();

        return response()->json([
            'success' => true,
            'message' => 'Token deactivated successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/notification-tokens/test",
     *     tags={"Notification Tokens"},
     *     summary="Tester une notification push",
     *     description="Envoie une notification de test à l'utilisateur connecté",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Notification de test envoyée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="tokens_count", type="integer"),
     *                 @OA\Property(property="sent_count", type="integer"),
     *                 @OA\Property(property="failed_count", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function testPush(): JsonResponse
    {
        $user = auth()->user();

        $tokens = NotificationToken::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        if ($tokens->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No active tokens found',
            ], 400);
        }

        $sentCount = 0;
        $failedCount = 0;

        foreach ($tokens as $token) {
            // Log Payload just before sending (User request)
            Log::info("FCM TEST SENDING [{$token->device_type}]", [
                'user_id' => $user->id,
                'token' => substr($token->fcm_token, 0, 10) . '...',
                'notification' => ['title' => '🔔 Test Notification', 'body' => 'This is a test push notification from DabApp!'],
                'data' => [
                    'type' => 'test',
                    'timestamp' => now()->toIso8601String(),
                ]
            ]);

            $result = $this->firebase->sendToToken(
                $token->fcm_token,
                '🔔 Test Notification',
                'This is a test push notification from DabApp!',
                [
                    'type' => 'test',
                    'timestamp' => now()->toIso8601String(),
                ],
                [
                    'priority' => 'high',
                    'sound' => 'default',
                    'color' => '#FF6B6B',
                ]
            );

            if ($result['success']) {
                $sentCount++;
                $token->updateLastUsed();
            } else {
                $failedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Test notification sent',
            'data' => [
                'tokens_count' => $tokens->count(),
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
            ],
        ]);
    }
}
