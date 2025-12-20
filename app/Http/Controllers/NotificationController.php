<?php
// app/Http/Controllers/Api/NotificationController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Notifications",
 *     description="Gestion des notifications utilisateur"
 * )
 */
class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * @OA\Get(
     *     path="/api/notifications",
     *     tags={"Notifications"},
     *     summary="Lister mes notifications",
     *     description="Récupère toutes les notifications de l'utilisateur connecté",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filtrer par type de notification",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="unread_only",
     *         in="query",
     *         description="Afficher uniquement les non lues",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre par page",
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des notifications",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="notifications",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="type", type="string"),
     *                         @OA\Property(property="title", type="string"),
     *                         @OA\Property(property="message", type="string"),
     *                         @OA\Property(property="is_read", type="boolean"),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="time_ago", type="string")
     *                     )
     *                 ),
     *                 @OA\Property(property="unread_count", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = auth()->user()->notifications()->notDeleted();

        // Filtres
        if ($request->filled('type')) {
            $query->byType($request->type);
        }

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        $perPage = $request->input('per_page', 20);
        $notifications = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $unreadCount = auth()->user()->notifications()->unread()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications->items(),
                'unread_count' => $unreadCount,
                'total' => $notifications->total(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/notifications/unread-count",
     *     tags={"Notifications"},
     *     summary="Compter les non lues",
     *     description="Récupère le nombre de notifications non lues",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Nombre de notifications non lues",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="unread_count", type="integer", example=5)
     *             )
     *         )
     *     )
     * )
     */
    public function unreadCount(): JsonResponse
    {
        $count = auth()->user()->notifications()->unread()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/notifications/{id}",
     *     tags={"Notifications"},
     *     summary="Détails d'une notification",
     *     description="Récupère les détails d'une notification et la marque comme lue",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de la notification",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="message", type="string"),
     *                 @OA\Property(property="data", type="object"),
     *                 @OA\Property(property="action_url", type="string"),
     *                 @OA\Property(property="is_read", type="boolean")
     *             )
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $notification = Notification::where('user_id', auth()->id())
            ->where('id', $id)
            ->notDeleted()
            ->with('actions')
            ->firstOrFail();

        // Marquer comme lue automatiquement
        if (!$notification->is_read) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'data' => $notification,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/{id}/read",
     *     tags={"Notifications"},
     *     summary="Marquer comme lue",
     *     description="Marque une notification comme lue",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification marquée comme lue",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function markAsRead(int $id): JsonResponse
    {
        $notification = Notification::where('user_id', auth()->id())
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/read-all",
     *     tags={"Notifications"},
     *     summary="Marquer toutes comme lues",
     *     description="Marque toutes les notifications comme lues",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Toutes les notifications marquées comme lues",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="updated_count", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function markAllAsRead(): JsonResponse
    {
        $count = auth()->user()
            ->notifications()
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'data' => [
                'updated_count' => $count,
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/notifications/{id}",
     *     tags={"Notifications"},
     *     summary="Supprimer une notification",
     *     description="Marque une notification comme supprimée",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification supprimée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $notification = Notification::where('user_id', auth()->id())
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsDeleted();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/notifications",
     *     tags={"Notifications"},
     *     summary="Supprimer toutes les notifications",
     *     description="Marque toutes les notifications comme supprimées",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Toutes les notifications supprimées",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="deleted_count", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function destroyAll(): JsonResponse
    {
        $count = auth()->user()
            ->notifications()
            ->notDeleted()
            ->update([
                'is_deleted' => true,
                'deleted_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications deleted',
            'data' => [
                'deleted_count' => $count,
            ],
        ]);
    }
}
