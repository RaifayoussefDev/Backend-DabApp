<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuideComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin - Guide Comments",
 *     description="API Endpoints pour l'administration des commentaires de guides"
 * )
 */
class GuideCommentAdminController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/guide-comments",
     *     summary="Liste tous les commentaires (Admin)",
     *     description="Récupère la liste de tous les commentaires avec filtres. Si per_page est vide, retourne tous les résultats.",
     *     tags={"Admin - Guide Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="Nombre par page (vide = tous)", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Parameter(name="guide_id", in="query", description="Filtrer par guide", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="user_id", in="query", description="Filtrer par utilisateur", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", description="Recherche dans le contenu", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort_order", in="query", required=false, @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des commentaires",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="content", type="string", example="Excellent guide!"),
     *                     @OA\Property(property="guide_id", type="integer", example=5),
     *                     @OA\Property(property="guide_title", type="string", example="Guide maintenance"),
     *                     @OA\Property(property="user_id", type="integer", example=10),
     *                     @OA\Property(property="user_name", type="string", example="John Doe"),
     *                     @OA\Property(property="user_email", type="string", example="john@example.com"),
     *                     @OA\Property(property="created_at", type="string", example="2025-02-05 14:30:00")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $query = DB::table('guide_comments')
            ->join('users', 'guide_comments.user_id', '=', 'users.id')
            ->join('guides', 'guide_comments.guide_id', '=', 'guides.id')
            ->select(
                'guide_comments.*',
                DB::raw('CONCAT(users.first_name, " ", users.last_name) as user_name'),
                'users.email as user_email',
                'users.profile_picture',
                'guides.title as guide_title',
                'guides.slug as guide_slug'
            );

        // Filtres
        if ($request->has('guide_id')) {
            $query->where('guide_comments.guide_id', $request->guide_id);
        }

        if ($request->has('user_id')) {
            $query->where('guide_comments.user_id', $request->user_id);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('guide_comments.content', 'like', "%{$search}%");
        }

        // Tri
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy('guide_comments.created_at', $sortOrder);

        $perPage = $request->get('per_page');

        if (empty($perPage)) {
            $comments = $query->get();
            return response()->json([
                'data' => $comments,
                'total' => $comments->count()
            ]);
        } else {
            $comments = $query->paginate($perPage);
            return response()->json($comments);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/guide-comments/stats",
     *     summary="Statistiques des commentaires (Admin)",
     *     tags={"Admin - Guide Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistiques",
     *         @OA\JsonContent(
     *             @OA\Property(property="total_comments", type="integer", example=890),
     *             @OA\Property(property="comments_today", type="integer", example=12),
     *             @OA\Property(property="comments_this_week", type="integer", example=78),
     *             @OA\Property(property="comments_this_month", type="integer", example=234)
     *         )
     *     )
     * )
     */
    public function stats()
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $stats = [
            'total_comments' => DB::table('guide_comments')->count(),
            'comments_today' => DB::table('guide_comments')->whereDate('created_at', today())->count(),
            'comments_this_week' => DB::table('guide_comments')
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'comments_this_month' => DB::table('guide_comments')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'avg_comments_per_guide' => round(
                DB::table('guide_comments')->count() / max(DB::table('guides')->count(), 1),
                2
            ),
            'most_commented_guides' => DB::table('guide_comments')
                ->join('guides', 'guide_comments.guide_id', '=', 'guides.id')
                ->select('guides.id', 'guides.title', 'guides.slug', DB::raw('COUNT(*) as comments_count'))
                ->groupBy('guides.id', 'guides.title', 'guides.slug')
                ->orderBy('comments_count', 'desc')
                ->limit(10)
                ->get(),
            'most_active_users' => DB::table('guide_comments')
                ->join('users', 'guide_comments.user_id', '=', 'users.id')
                ->select(
                    'users.id',
                    DB::raw('CONCAT(users.first_name, " ", users.last_name) as name'),
                    'users.email',
                    DB::raw('COUNT(*) as comments_count')
                )
                ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->orderBy('comments_count', 'desc')
                ->limit(10)
                ->get(),
            'recent_comments' => DB::table('guide_comments')
                ->join('users', 'guide_comments.user_id', '=', 'users.id')
                ->join('guides', 'guide_comments.guide_id', '=', 'guides.id')
                ->select(
                    'guide_comments.id',
                    'guide_comments.content',
                    DB::raw('CONCAT(users.first_name, " ", users.last_name) as user_name'),
                    'guides.title as guide_title',
                    'guide_comments.created_at'
                )
                ->orderBy('guide_comments.created_at', 'desc')
                ->limit(10)
                ->get()
        ];

        return response()->json($stats);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/guide-comments/{id}",
     *     summary="Détails d'un commentaire (Admin)",
     *     tags={"Admin - Guide Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Détails du commentaire")
     * )
     */
    public function show($id)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $comment = DB::table('guide_comments')
            ->join('users', 'guide_comments.user_id', '=', 'users.id')
            ->join('guides', 'guide_comments.guide_id', '=', 'guides.id')
            ->where('guide_comments.id', $id)
            ->select(
                'guide_comments.*',
                DB::raw('CONCAT(users.first_name, " ", users.last_name) as user_name'),
                'users.email as user_email',
                'users.profile_picture',
                'guides.title as guide_title',
                'guides.slug as guide_slug'
            )
            ->first();

        if (!$comment) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        return response()->json(['data' => $comment]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/guide-comments/{id}",
     *     summary="Mettre à jour un commentaire (Admin)",
     *     tags={"Admin - Guide Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="content", type="string", example="Contenu modifié")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Commentaire mis à jour")
     * )
     */
    public function update(Request $request, $id)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $comment = GuideComment::find($id);

        if (!$comment) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $comment->update($request->all());

        return response()->json([
            'message' => 'Comment updated successfully',
            'data' => $comment
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/guide-comments/{id}",
     *     summary="Supprimer un commentaire (Admin)",
     *     tags={"Admin - Guide Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Commentaire supprimé")
     * )
     */
    public function destroy($id)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $comment = GuideComment::find($id);

        if (!$comment) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/guide-comments/bulk-delete",
     *     summary="Suppression en masse de commentaires (Admin)",
     *     tags={"Admin - Guide Comments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ids"},
     *             @OA\Property(property="ids", type="array", @OA\Items(type="integer"), example={1, 2, 3})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Commentaires supprimés")
     * )
     */
    public function bulkDelete(Request $request)
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:guide_comments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        GuideComment::whereIn('id', $request->ids)->delete();

        return response()->json([
            'message' => 'Comments deleted successfully',
            'deleted_count' => count($request->ids)
        ]);
    }
}
