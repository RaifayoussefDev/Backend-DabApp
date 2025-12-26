<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use App\Models\GuideComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Services\NotificationService;

/**
 * @OA\Tag(
 *     name="Guide Comments",
 *     description="API Endpoints pour la gestion des commentaires de guides"
 * )
 */
class GuideCommentController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    /**
     * @OA\Get(
     *     path="/api/guides/{id}/comments",
     *     summary="Liste tous les commentaires d'un guide",
     *     tags={"Guide Comments"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du guide",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Tri: latest, oldest",
     *         required=false,
     *         @OA\Schema(type="string", enum={"latest", "oldest"}, default="latest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des commentaires",
     *         @OA\JsonContent(
     *             @OA\Property(property="guide_id", type="integer", example=1),
     *             @OA\Property(property="total_comments", type="integer", example=25),
     *             @OA\Property(
     *                 property="comments",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="content", type="string"),
     *                     @OA\Property(property="is_approved", type="boolean"),
     *                     @OA\Property(property="created_at", type="string"),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="profile_picture", type="string")
     *                     ),
     *                     @OA\Property(
     *                         property="replies",
     *                         type="array",
     *                         @OA\Items(ref="#")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guide non trouvé"
     *     )
     * )
     */
    public function index($id, Request $request)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide not found'
            ], 404);
        }

        $sort = $request->get('sort', 'latest');

        // Récupérer les commentaires parents uniquement (sans parent_id)
        $query = GuideComment::with(['user', 'replies.user'])
            ->where('guide_id', $id)
            ->whereNull('parent_id')
            ->where('is_approved', true);

        if ($sort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $comments = $query->get()->map(function ($comment) {
            return $this->formatComment($comment);
        });

        return response()->json([
            'guide_id' => (int) $id,
            'total_comments' => $guide->allComments()->where('is_approved', true)->count(),
            'comments' => $comments
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/guides/{id}/comments",
     *     summary="Ajouter un commentaire à un guide",
     *     tags={"Guide Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du guide",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"content"},
     *             @OA\Property(property="content", type="string", example="Excellent guide, très utile!"),
     *             @OA\Property(property="parent_id", type="integer", example=null, description="ID du commentaire parent pour les réponses")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Commentaire créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Commentaire ajouté avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="content", type="string"),
     *                 @OA\Property(property="created_at", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guide non trouvé"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation"
     *     )
     * )
     */
    public function store($id, Request $request)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|min:3|max:1000',
            'parent_id' => 'nullable|exists:guide_comments,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier que le parent_id appartient bien au même guide
        if ($request->parent_id) {
            $parentComment = GuideComment::find($request->parent_id);
            if ($parentComment->guide_id != $id) {
                return response()->json([
                    'message' => 'The parent comment does not belong to this guide'
                ], 422);
            }
        }

        $comment = GuideComment::create([
            'guide_id' => $id,
            'user_id' => Auth::id(),
            'parent_id' => $request->parent_id,
            'content' => $request->content,
            'is_approved' => true, // Auto-approuvé par défaut, peut être modifié selon besoins
        ]);

        // Notify Author
        try {
            if ($guide->author_id !== Auth::id()) {
                $this->notificationService->sendToUser($guide->author, 'guide_new_comment', [
                    'user_name' => Auth::user()->first_name . ' ' . Auth::user()->last_name,
                    'guide_title' => $guide->title
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send guide comment notification: ' . $e->getMessage());
        }

        $comment->load('user');

        return response()->json([
            'message' => 'Comment added successfully',
            'data' => $this->formatComment($comment)
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/guides/comments/{commentId}",
     *     summary="Modifier un commentaire",
     *     tags={"Guide Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="commentId",
     *         in="path",
     *         description="ID du commentaire",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"content"},
     *             @OA\Property(property="content", type="string", example="Commentaire mis à jour")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Commentaire mis à jour avec succès"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Commentaire non trouvé"
     *     )
     * )
     */
    public function update($commentId, Request $request)
    {
        $comment = GuideComment::find($commentId);

        if (!$comment) {
            return response()->json([
                'message' => 'Comment not found'
            ], 404);
        }

        $user = Auth::user();

        // Vérifier autorisation (auteur du commentaire ou admin)
        if ($comment->user_id !== $user->id && $user->role_id != 1) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|min:3|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $comment->update([
            'content' => $request->content
        ]);

        return response()->json([
            'message' => 'Comment updated successfully',
            'data' => $this->formatComment($comment)
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/guides/comments/{commentId}",
     *     summary="Supprimer un commentaire",
     *     tags={"Guide Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="commentId",
     *         in="path",
     *         description="ID du commentaire",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Commentaire supprimé avec succès"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Commentaire non trouvé"
     *     )
     * )
     */
    public function destroy($commentId)
    {
        $comment = GuideComment::find($commentId);

        if (!$comment) {
            return response()->json([
                'message' => 'Comment not found'
            ], 404);
        }

        $user = Auth::user();

        // Vérifier autorisation (auteur du commentaire ou admin)
        if ($comment->user_id !== $user->id && $user->role_id != 1) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/guides/comments/{commentId}/approve",
     *     summary="Approuver un commentaire (Admin uniquement)",
     *     tags={"Guide Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="commentId",
     *         in="path",
     *         description="ID du commentaire",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Commentaire approuvé avec succès"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé - Admin uniquement"
     *     )
     * )
     */
    public function approve($commentId)
    {
        $comment = GuideComment::find($commentId);

        if (!$comment) {
            return response()->json([
                'message' => 'Comment not found'
            ], 404);
        }

        $user = Auth::user();

        // Seulement admin
        if ($user->role_id != 1) {
            return response()->json([
                'message' => 'Unauthorized - Admin only'
            ], 403);
        }

        $comment->update(['is_approved' => true]);

        return response()->json([
            'message' => 'Comment approved successfully',
            'data' => $this->formatComment($comment)
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/guides/comments/{commentId}/reject",
     *     summary="Rejeter un commentaire (Admin uniquement)",
     *     tags={"Guide Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="commentId",
     *         in="path",
     *         description="ID du commentaire",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Commentaire rejeté avec succès"
     *     )
     * )
     */
    public function reject($commentId)
    {
        $comment = GuideComment::find($commentId);

        if (!$comment) {
            return response()->json([
                'message' => 'Comment not found'
            ], 404);
        }

        $user = Auth::user();

        if ($user->role_id != 1) {
            return response()->json([
                'message' => 'Unauthorized - Admin only'
            ], 403);
        }

        $comment->update(['is_approved' => false]);

        return response()->json([
            'message' => 'Comment rejected successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/comments/pending",
     *     summary="Liste des commentaires en attente de modération (Admin)",
     *     tags={"Guide Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des commentaires en attente"
     *     )
     * )
     */
    public function pending()
    {
        $user = Auth::user();

        if ($user->role_id != 1) {
            return response()->json([
                'message' => 'Unauthorized - Admin only'
            ], 403);
        }

        $comments = GuideComment::with(['user', 'guide'])
            ->where('is_approved', false)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'created_at' => $comment->created_at->format('Y-m-d H:i:s'),
                    'user' => [
                        'id' => $comment->user->id,
                        'name' => $comment->user->first_name . ' ' . $comment->user->last_name,
                        'email' => $comment->user->email,
                    ],
                    'guide' => [
                        'id' => $comment->guide->id,
                        'title' => $comment->guide->title,
                        'slug' => $comment->guide->slug,
                    ],
                ];
            });

        return response()->json([
            'total' => $comments->count(),
            'comments' => $comments
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/my/comments",
     *     summary="Mes commentaires",
     *     tags={"Guide Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste de mes commentaires"
     *     )
     * )
     */
    public function myComments()
    {
        $user = Auth::user();

        $comments = GuideComment::with(['guide'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'is_approved' => $comment->is_approved,
                    'created_at' => $comment->created_at->format('Y-m-d H:i:s'),
                    'guide' => [
                        'id' => $comment->guide->id,
                        'title' => $comment->guide->title,
                        'slug' => $comment->guide->slug,
                    ],
                ];
            });

        return response()->json($comments);
    }

    /**
     * Formater un commentaire avec ses réponses
     */
    private function formatComment($comment)
    {
        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'is_approved' => $comment->is_approved,
            'created_at' => $comment->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $comment->updated_at->format('Y-m-d H:i:s'),
            'user' => [
                'id' => $comment->user->id,
                'name' => $comment->user->first_name . ' ' . $comment->user->last_name,
                'profile_picture' => $comment->user->profile_picture,
            ],
            'replies' => $comment->replies ? $comment->replies->map(function ($reply) {
                return $this->formatComment($reply);
            }) : [],
            'replies_count' => $comment->replies ? $comment->replies->count() : 0,
        ];
    }
}
