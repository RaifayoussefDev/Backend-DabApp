<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Admin Chat Sessions",
 *     description="API endpoints for monitoring chat sessions (Admin)"
 * )
 */
class AdminChatSessionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/chat-sessions",
     *     summary="List chat sessions (Admin)",
     *     operationId="adminGetChatSessions",
     *     tags={"Admin Chat Sessions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", description="Filter by status", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Chat sessions retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=789),
     *                         @OA\Property(property="session_status", type="string", example="active"),
     *                         @OA\Property(property="started_at", type="string", format="date-time", example="2026-01-30T10:00:00Z"),
     *                         @OA\Property(property="user", type="object", @OA\Property(property="name", type="string", example="Customer 1")),
     *                         @OA\Property(property="provider", type="object", @OA\Property(property="name", type="string", example="Provider 1"))
     *                     )
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=10)
     *             ),
     *             @OA\Property(property="message", type="string", example="Chat sessions retrieved successfully")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = ChatSession::with(['user', 'provider.user']);

        if ($request->has('status')) {
            $query->where('session_status', $request->status);
        }

        $perPage = $request->input('per_page');

        if ($perPage) {
            $sessions = $query->orderBy('started_at', 'desc')->paginate($perPage);
        } else {
            $sessions = $query->orderBy('started_at', 'desc')->get();
        }

        return response()->json([
            'success' => true,
            'data' => $sessions,
            'message' => 'Chat sessions retrieved successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/chat-sessions/{id}",
     *     summary="Get chat session details with messages (Admin)",
     *     operationId="adminGetChatSession",
     *     tags={"Admin Chat Sessions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Chat session details retrieved")
     * )
     */
    public function show($id)
    {
        $session = ChatSession::with(['user', 'provider.user', 'messages'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $session,
            'message' => 'Chat session retrieved successfully'
        ]);
    }
}
