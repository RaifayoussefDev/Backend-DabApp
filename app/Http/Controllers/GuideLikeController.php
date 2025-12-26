<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;

/**
 * @OA\Tag(
 *     name="Guide Likes",
 *     description="API Endpoints pour la gestion des likes de guides"
 * )
 */
class GuideLikeController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    /**
     * @OA\Post(
     *     path="/api/guides/{id}/like",
     *     summary="Liker un guide",
     *     tags={"Guide Likes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du guide",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Guide liké avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Guide liké avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="guide_id", type="integer", example=1),
     *                 @OA\Property(property="liked", type="boolean", example=true),
     *                 @OA\Property(property="likes_count", type="integer", example=15)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guide non trouvé"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié"
     *     )
     * )
     */
    public function like($id)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide not found'
            ], 404);
        }

        $user = Auth::user();

        // Vérifier si déjà liké
        $existingLike = DB::table('guide_likes')
            ->where('guide_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingLike) {
            return response()->json([
                'message' => 'You have already liked this guide',
                'data' => [
                    'guide_id' => (int) $id,
                    'liked' => true,
                    'likes_count' => $guide->likes()->count()
                ]
            ], 200);
        }

        // Créer le like
        DB::table('guide_likes')->insert([
            'guide_id' => $id,
            'user_id' => $user->id,
            'created_at' => now()
        ]);

        // Notify Author
        try {
            if ($guide->author_id !== $user->id) {
                $this->notificationService->sendToUser($guide->author, 'guide_new_like', [
                    'user_name' => $user->first_name . ' ' . $user->last_name,
                    'guide_title' => $guide->title
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send guide like notification: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Guide liked successfully',
            'data' => [
                'guide_id' => (int) $id,
                'liked' => true,
                'likes_count' => $guide->likes()->count()
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/guides/{id}/unlike",
     *     summary="Unliker un guide",
     *     tags={"Guide Likes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du guide",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Like retiré avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Like retiré avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="guide_id", type="integer", example=1),
     *                 @OA\Property(property="liked", type="boolean", example=false),
     *                 @OA\Property(property="likes_count", type="integer", example=14)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guide non trouvé ou like inexistant"
     *     )
     * )
     */
    public function unlike($id)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide not found'
            ], 404);
        }

        $user = Auth::user();

        $deleted = DB::table('guide_likes')
            ->where('guide_id', $id)
            ->where('user_id', $user->id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'You have not liked this guide',
                'data' => [
                    'guide_id' => (int) $id,
                    'liked' => false,
                    'likes_count' => $guide->likes()->count()
                ]
            ], 404);
        }

        return response()->json([
            'message' => 'Like removed successfully',
            'data' => [
                'guide_id' => (int) $id,
                'liked' => false,
                'likes_count' => $guide->likes()->count()
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/guides/{id}/toggle-like",
     *     summary="Toggle like d'un guide (like/unlike automatique)",
     *     tags={"Guide Likes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du guide",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Toggle effectué avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Guide liké avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="guide_id", type="integer", example=1),
     *                 @OA\Property(property="liked", type="boolean", example=true),
     *                 @OA\Property(property="likes_count", type="integer", example=15)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guide non trouvé"
     *     )
     * )
     */
    public function toggleLike($id)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide not found'
            ], 404);
        }

        $user = Auth::user();

        $existingLike = DB::table('guide_likes')
            ->where('guide_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingLike) {
            // Unlike
            DB::table('guide_likes')
                ->where('guide_id', $id)
                ->where('user_id', $user->id)
                ->delete();

            return response()->json([
                'message' => 'Like removed successfully',
                'data' => [
                    'guide_id' => (int) $id,
                    'liked' => false,
                    'likes_count' => $guide->likes()->count()
                ]
            ]);
        } else {
            // Like
            DB::table('guide_likes')->insert([
                'guide_id' => $id,
                'user_id' => $user->id,
                'created_at' => now()
            ]);

            return response()->json([
                'message' => 'Guide liked successfully',
                'data' => [
                    'guide_id' => (int) $id,
                    'liked' => true,
                    'likes_count' => $guide->likes()->count()
                ]
            ]);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/guides/my/liked",
     *     summary="Liste des guides likés par l'utilisateur connecté",
     *     tags={"Guide Likes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre d'éléments par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des guides likés",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Guide complet de maintenance"),
     *                 @OA\Property(property="slug", type="string", example="guide-complet-maintenance"),
     *                 @OA\Property(property="excerpt", type="string"),
     *                 @OA\Property(property="featured_image", type="string"),
     *                 @OA\Property(property="liked_at", type="string", example="2025-10-17 10:30:00")
     *             )
     *         )
     *     )
     * )
     */
    public function myLikedGuides(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->get('per_page', 10);

        $likedGuides = DB::table('guide_likes')
            ->join('guides', 'guide_likes.guide_id', '=', 'guides.id')
            ->join('users', 'guides.author_id', '=', 'users.id')
            ->leftJoin('guide_categories', 'guides.category_id', '=', 'guide_categories.id')
            ->where('guide_likes.user_id', $user->id)
            ->where('guides.status', 'published')
            ->select(
                'guides.id',
                'guides.title',
                'guides.slug',
                'guides.excerpt',
                'guides.featured_image',
                'guides.views_count',
                'guide_likes.created_at as liked_at',
                DB::raw('CONCAT(users.first_name, " ", users.last_name) as author_name'),
                'guide_categories.name as category_name',
                'guide_categories.slug as category_slug'
            )
            ->orderBy('guide_likes.created_at', 'desc')
            ->paginate($perPage);

        return response()->json($likedGuides);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/{id}/likes",
     *     summary="Liste des utilisateurs qui ont liké un guide",
     *     tags={"Guide Likes"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du guide",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des likes",
     *         @OA\JsonContent(
     *             @OA\Property(property="guide_id", type="integer", example=1),
     *             @OA\Property(property="likes_count", type="integer", example=15),
     *             @OA\Property(
     *                 property="likes",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="user_id", type="integer", example=5),
     *                     @OA\Property(property="user_name", type="string", example="John Doe"),
     *                     @OA\Property(property="profile_picture", type="string"),
     *                     @OA\Property(property="liked_at", type="string", example="2025-10-17 10:30:00")
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
    public function getGuideLikes($id)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide not found'
            ], 404);
        }

        $likes = DB::table('guide_likes')
            ->join('users', 'guide_likes.user_id', '=', 'users.id')
            ->where('guide_likes.guide_id', $id)
            ->select(
                'users.id as user_id',
                DB::raw('CONCAT(users.first_name, " ", users.last_name) as user_name'),
                'users.profile_picture',
                'guide_likes.created_at as liked_at'
            )
            ->orderBy('guide_likes.created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'guide_id' => (int) $id,
            'likes_count' => $likes->count(),
            'likes' => $likes
        ]);
    }
}
