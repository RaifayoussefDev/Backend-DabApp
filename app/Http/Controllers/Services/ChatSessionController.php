<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Models\ServiceBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Chat Sessions",
 *     description="API endpoints pour le chat technique entre utilisateurs et providers"
 * )
 */
class ChatSessionController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/bookings/{booking_id}/start-chat",
     *     summary="Démarrer une session de chat",
     *     description="Démarre une session de chat technique entre le client et le fournisseur pour une réservation",
     *     operationId="startChatSession",
     *     tags={"Chat Sessions"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="booking_id",
     *         in="path",
     *         description="ID de la réservation",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Session créée avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="booking_id", type="integer", example=1),
     *                 @OA\Property(property="session_status", type="string", example="active"),
     *                 @OA\Property(property="session_price", type="number", format="float", example=50.00),
     *                 @OA\Property(property="started_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Session déjà existante ou réservation invalide"),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=404, description="Réservation non trouvée")
     * )
     */
    public function startSession($bookingId)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $booking = ServiceBooking::with(['service', 'provider'])->find($bookingId);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        }

        // Vérifier autorisation (user ou provider)
        if ($booking->user_id !== $user->id && 
            $booking->provider->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to start chat for this booking'
            ], 403);
        }

        // Vérifier que la réservation est confirmée ou en cours
        if (!in_array($booking->status, ['confirmed', 'in_progress'])) {
            return response()->json([
                'success' => false,
                'message' => 'Chat is only available for confirmed or in-progress bookings'
            ], 400);
        }

        // Vérifier si une session existe déjà
        if ($booking->chatSession) {
            return response()->json([
                'success' => true,
                'data' => $booking->chatSession,
                'message' => 'Chat session already exists'
            ], 200);
        }

        DB::beginTransaction();
        try {
            // Prix de la session (optionnel, peut être gratuit)
            $sessionPrice = 0; // Ou récupérer depuis config

            $session = ChatSession::create([
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'provider_id' => $booking->provider_id,
                'session_price' => $sessionPrice,
                'session_status' => 'active',
                'started_at' => now()
            ]);

            DB::commit();

            // TODO: Créer une notification temps réel

            return response()->json([
                'success' => true,
                'data' => $session->load(['booking.service', 'user:id,full_name', 'provider']),
                'message' => 'Chat session started successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to start chat session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/chat-sessions/{session_id}/send-message",
     *     summary="Envoyer un message",
     *     description="Envoie un message texte, image ou fichier dans une session de chat",
     *     operationId="sendChatMessage",
     *     tags={"Chat Sessions"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="session_id",
     *         in="path",
     *         description="ID de la session de chat",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The engine is making a strange noise", description="Texte du message (requis si pas d'attachment)"),
     *             @OA\Property(property="message_type", type="string", enum={"text", "image", "file"}, example="text"),
     *             @OA\Property(property="attachment", type="string", format="binary", description="Fichier joint (image ou document)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Message envoyé",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="message", type="string"),
     *                 @OA\Property(property="message_type", type="string"),
     *                 @OA\Property(property="sender_type", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=404, description="Session non trouvée")
     * )
     */
    public function sendMessage(Request $request, $sessionId)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $session = ChatSession::find($sessionId);

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Chat session not found'
            ], 404);
        }

        // Vérifier autorisation
        $isUser = $session->user_id === $user->id;
        $isProvider = $session->provider->user_id === $user->id;

        if (!$isUser && !$isProvider) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to send messages in this session'
            ], 403);
        }

        // Vérifier que la session est active
        if ($session->session_status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Chat session is not active'
            ], 400);
        }

        $validated = $request->validate([
            'message' => 'required_without:attachment|string|max:2000',
            'message_type' => 'nullable|in:text,image,file',
            'attachment' => 'nullable|file|max:10240' // 10MB max
        ]);

        DB::beginTransaction();
        try {
            $messageType = $validated['message_type'] ?? 'text';
            $attachmentUrl = null;

            // Upload du fichier joint
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                
                // Déterminer le type
                if ($file->isValid()) {
                    $mimeType = $file->getMimeType();
                    
                    if (str_starts_with($mimeType, 'image/')) {
                        $messageType = 'image';
                        $attachmentUrl = $file->store('chat/images', 'public');
                    } else {
                        $messageType = 'file';
                        $attachmentUrl = $file->store('chat/files', 'public');
                    }
                }
            }

            $message = ChatMessage::create([
                'session_id' => $session->id,
                'sender_id' => $user->id,
                'sender_type' => $isUser ? 'user' : 'provider',
                'message' => $validated['message'] ?? null,
                'message_type' => $messageType,
                'attachment_url' => $attachmentUrl,
                'is_read' => false
            ]);

            DB::commit();

            // TODO: Envoyer notification push au destinataire
            // TODO: Broadcast via WebSocket pour chat temps réel

            return response()->json([
                'success' => true,
                'data' => $message,
                'message' => 'Message sent successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/chat-sessions/{session_id}/messages",
     *     summary="Messages d'une session",
     *     description="Récupère tous les messages d'une session de chat avec pagination",
     *     operationId="getChatMessages",
     *     tags={"Chat Sessions"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="session_id",
     *         in="path",
     *         description="ID de la session",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Messages par page",
     *         required=false,
     *         @OA\Schema(type="integer", example=50)
     *     ),
     *     @OA\Parameter(
     *         name="before_id",
     *         in="query",
     *         description="Charger les messages avant cet ID (pagination infinie)",
     *         required=false,
     *         @OA\Schema(type="integer", example=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Messages récupérés",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="messages",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="message", type="string"),
     *                         @OA\Property(property="message_type", type="string"),
     *                         @OA\Property(property="sender_type", type="string"),
     *                         @OA\Property(property="is_read", type="boolean"),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
     *                     )
     *                 ),
     *                 @OA\Property(property="session", type="object"),
     *                 @OA\Property(property="unread_count", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function messages(Request $request, $sessionId)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $session = ChatSession::with(['booking.service', 'user:id,full_name,avatar', 'provider'])->find($sessionId);

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Chat session not found'
            ], 404);
        }

        // Vérifier autorisation
        if ($session->user_id !== $user->id && 
            $session->provider->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view messages'
            ], 403);
        }

        $query = ChatMessage::where('session_id', $session->id);

        // Pagination infinie (charger messages avant un certain ID)
        if ($request->has('before_id')) {
            $query->where('id', '<', $request->before_id);
        }

        $perPage = $request->get('per_page', 50);
        $messages = $query->orderByDesc('id')->paginate($perPage);

        // Compter messages non lus pour l'utilisateur actuel
        $isUser = $session->user_id === $user->id;
        $unreadCount = ChatMessage::where('session_id', $session->id)
            ->where('sender_type', $isUser ? 'provider' : 'user')
            ->where('is_read', false)
            ->count();

        // Marquer comme lus les messages de l'autre partie
        ChatMessage::where('session_id', $session->id)
            ->where('sender_type', $isUser ? 'provider' : 'user')
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => [
                'messages' => $messages,
                'session' => $session,
                'unread_count' => $unreadCount
            ],
            'message' => 'Messages retrieved successfully'
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/chat-sessions/{session_id}/end",
     *     summary="Terminer une session",
     *     description="Termine une session de chat active",
     *     operationId="endChatSession",
     *     tags={"Chat Sessions"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="session_id",
     *         in="path",
     *         description="ID de la session",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Session terminée",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function endSession($sessionId)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $session = ChatSession::find($sessionId);

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Chat session not found'
            ], 404);
        }

        // Vérifier autorisation
        if ($session->user_id !== $user->id && 
            $session->provider->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to end this session'
            ], 403);
        }

        if ($session->session_status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Session is already ended'
            ], 400);
        }

        try {
            // Calculer la durée
            $startedAt = \Carbon\Carbon::parse($session->started_at);
            $durationMinutes = $startedAt->diffInMinutes(now());

            $session->update([
                'session_status' => 'completed',
                'ended_at' => now(),
                'duration_minutes' => $durationMinutes
            ]);

            return response()->json([
                'success' => true,
                'data' => $session,
                'message' => 'Chat session ended successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to end session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/my-chat-sessions",
     *     summary="Mes sessions de chat",
     *     description="Récupère toutes les sessions de chat de l'utilisateur",
     *     operationId="getMyChatSessions",
     *     tags={"Chat Sessions"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filtrer par statut",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "active", "completed", "expired"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sessions récupérées"
     *     )
     * )
     */
    public function mySessions(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $query = ChatSession::with([
            'booking.service',
            'user:id,full_name,avatar',
            'provider'
        ])
        ->where(function($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereHas('provider', function($pq) use ($user) {
                  $pq->where('user_id', $user->id);
              });
        });

        // Filtre par statut
        if ($request->has('status')) {
            $query->where('session_status', $request->status);
        }

        // Ajouter le dernier message et le nombre de non lus
        $sessions = $query->orderByDesc('started_at')->paginate(20);

        $sessions->getCollection()->transform(function($session) use ($user) {
            $lastMessage = ChatMessage::where('session_id', $session->id)
                ->latest()
                ->first();

            $isUser = $session->user_id === $user->id;
            $unreadCount = ChatMessage::where('session_id', $session->id)
                ->where('sender_type', $isUser ? 'provider' : 'user')
                ->where('is_read', false)
                ->count();

            $session->last_message = $lastMessage;
            $session->unread_count = $unreadCount;

            return $session;
        });

        return response()->json([
            'success' => true,
            'data' => $sessions,
            'message' => 'Chat sessions retrieved successfully'
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/chat-sessions/{session_id}/mark-read",
     *     summary="Marquer comme lu",
     *     description="Marque tous les messages d'une session comme lus",
     *     operationId="markSessionAsRead",
     *     tags={"Chat Sessions"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="session_id",
     *         in="path",
     *         description="ID de la session",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Messages marqués comme lus")
     * )
     */
    public function markAsRead($sessionId)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $session = ChatSession::find($sessionId);

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Chat session not found'
            ], 404);
        }

        // Vérifier autorisation
        if ($session->user_id !== $user->id && 
            $session->provider->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            $isUser = $session->user_id === $user->id;
            
            $updated = ChatMessage::where('session_id', $session->id)
                ->where('sender_type', $isUser ? 'provider' : 'user')
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'messages_marked' => $updated,
                'message' => 'Messages marked as read'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark messages as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}