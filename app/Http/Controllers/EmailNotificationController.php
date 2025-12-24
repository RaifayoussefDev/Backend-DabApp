<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\EmailNotificationService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Email Notifications",
 *     description="Envoyer des notifications par email"
 * )
 */
class EmailNotificationController extends Controller
{
    protected $notificationService;

    public function __construct(EmailNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/send",
     *     tags={"Email Notifications"},
     *     summary="Envoyer une notification",
     *     description="Envoie une notification par email à un utilisateur",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "type", "data"},
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="type", type="string", example="listing_approved"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 example={"listing_title": "Yamaha R1", "listing_id": 123}
     *             ),
     *             @OA\Property(property="action_url", type="string", example="https://dabapp.co/listings/123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification envoyée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|string',
            'data' => 'required|array',
            'action_url' => 'nullable|url',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ]);

        $user = User::findOrFail($validated['user_id']);

        $options = [];
        if (isset($validated['action_url'])) {
            $options['action_url'] = $validated['action_url'];
        }
        if (isset($validated['priority'])) {
            $options['priority'] = $validated['priority'];
        }

        $notification = $this->notificationService->send(
            $user,
            $validated['type'],
            $validated['data'],
            $options
        );

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification blocked by user preferences',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification sent successfully',
            'data' => [
                'notification_id' => $notification->id,
                'type' => $notification->type,
                'sent_to' => $user->email,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/send-custom",
     *     tags={"Email Notifications"},
     *     summary="Envoyer une notification personnalisée",
     *     description="Envoie une notification personnalisée par email (pour admins)",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "title", "message"},
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="مرحبا بك!"),
     *             @OA\Property(property="message", type="string", example="نحن سعداء بانضمامك"),
     *             @OA\Property(property="action_url", type="string", example="https://dabapp.co"),
     *             @OA\Property(property="icon", type="string", example="celebration"),
     *             @OA\Property(property="color", type="string", example="#4CAF50"),
     *             @OA\Property(property="priority", type="string", enum={"low", "normal", "high", "urgent"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification personnalisée envoyée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function sendCustom(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'action_url' => 'nullable|url',
            'icon' => 'nullable|string',
            'color' => 'nullable|string',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ]);

        $user = User::findOrFail($validated['user_id']);

        $options = [
            'action_url' => $validated['action_url'] ?? null,
            'icon' => $validated['icon'] ?? 'announcement',
            'color' => $validated['color'] ?? '#f03d24',
            'priority' => $validated['priority'] ?? 'normal',
            'admin_id' => auth()->id(),
        ];

        $notification = $this->notificationService->sendCustom(
            $user,
            $validated['title'],
            $validated['message'],
            $options
        );

        return response()->json([
            'success' => true,
            'message' => 'Custom notification sent successfully',
            'data' => [
                'notification_id' => $notification->id,
                'sent_to' => $user->email,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/send-multiple",
     *     tags={"Email Notifications"},
     *     summary="Envoyer à plusieurs utilisateurs",
     *     description="Envoie une notification à plusieurs utilisateurs",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_ids", "type", "data"},
     *             @OA\Property(
     *                 property="user_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 3, 4, 5}
     *             ),
     *             @OA\Property(property="type", type="string", example="system_updates"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 example={"update_title": "Nouvelle fonctionnalité", "update_message": "..."}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notifications envoyées",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="results", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function sendMultiple(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'type' => 'required|string',
            'data' => 'required|array',
            'action_url' => 'nullable|url',
        ]);

        $options = [];
        if (isset($validated['action_url'])) {
            $options['action_url'] = $validated['action_url'];
        }

        $results = $this->notificationService->sendToMultiple(
            $validated['user_ids'],
            $validated['type'],
            $validated['data'],
            $options
        );

        $stats = [
            'total' => count($validated['user_ids']),
            'sent' => count(array_filter($results, fn($r) => $r === 'sent')),
            'skipped' => count(array_filter($results, fn($r) => $r === 'skipped')),
            'failed' => count(array_filter($results, fn($r) => $r === 'user_not_found')),
        ];

        return response()->json([
            'success' => true,
            'message' => "Notifications sent to {$stats['sent']} users",
            'data' => [
                'stats' => $stats,
                'results' => $results,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/broadcast",
     *     tags={"Email Notifications"},
     *     summary="Broadcast à tous les utilisateurs",
     *     description="Envoie une notification à tous les utilisateurs (avec filtres optionnels)",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type", "data"},
     *             @OA\Property(property="type", type="string", example="promotional"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 example={"promo_title": "Soldes!", "promo_message": "20% de réduction"}
     *             ),
     *             @OA\Property(
     *                 property="filters",
     *                 type="object",
     *                 example={"is_verified": 1, "city": "Casablanca"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Broadcast envoyé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="sent", type="integer"),
     *                 @OA\Property(property="skipped", type="integer"),
     *                 @OA\Property(property="failed", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function broadcast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'data' => 'required|array',
            'filters' => 'nullable|array',
        ]);

        $results = $this->notificationService->broadcast(
            $validated['type'],
            $validated['data'],
            $validated['filters'] ?? []
        );

        return response()->json([
            'success' => true,
            'message' => "Broadcast sent to {$results['sent']} users",
            'data' => $results,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/test-email",
     *     tags={"Email Notifications"},
     *     summary="Tester l'envoi d'email",
     *     description="Envoie un email de test à l'utilisateur connecté",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Email de test envoyé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function testEmail(): JsonResponse
{
    $user = auth()->user();

    $notification = $this->notificationService->testEmail($user); // ← Utiliser la méthode testEmail() du service!

    return response()->json([
        'success' => true,
        'message' => 'Test email sent successfully',
        'data' => [
            'notification_id' => $notification->id,
            'sent_to' => $user->email,
            'check_your_inbox' => 'Please check your inbox (and spam folder)',
        ],
    ]);
}
}
