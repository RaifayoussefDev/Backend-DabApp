<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;

/**
 * @OA\Tag(
 *     name="Guide Bookmarks",
 *     description="API Endpoints pour la gestion des favoris/bookmarks de guides"
 * )
 */
class GuideBookmarkController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    /**
     * @OA\Post(
     *     path="/api/guides/{id}/bookmark",
     *     summary="Ajouter un guide aux favoris",
     *     tags={"Guide Bookmarks"},
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
     *         description="Guide ajouté aux favoris avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Guide ajouté aux favoris avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="guide_id", type="integer", example=1),
     *                 @OA\Property(property="bookmarked", type="boolean", example=true),
     *                 @OA\Property(property="bookmarked_at", type="string", example="2025-10-17 10:30:00")
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
    public function bookmark($id)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide not found'
            ], 404);
        }

        $user = Auth::user();

        // Vérifier si déjà bookmarké
        $existingBookmark = DB::table('guide_bookmarks')
            ->where('guide_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingBookmark) {
            return response()->json([
                'message' => 'This guide is already in your bookmarks',
                'data' => [
                    'guide_id' => (int) $id,
                    'bookmarked' => true,
                    'bookmarked_at' => $existingBookmark->created_at
                ]
            ], 200);
        }

        // Créer le bookmark
        $createdAt = now();
        DB::table('guide_bookmarks')->insert([
            'guide_id' => $id,
            'user_id' => $user->id,
            'created_at' => $createdAt
        ]);

        // Notify Author (Optional for bookmark, but requested in plan)
        try {
            if ($guide->author_id !== $user->id) {
                // Assuming we want a notification for bookmarks too based on previous context
                $this->notificationService->sendToUser($guide->author, 'guide_new_bookmark', [
                    'user_name' => $user->first_name . ' ' . $user->last_name,
                    'guide_title' => $guide->title
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send guide bookmark notification: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Guide added to bookmarks successfully',
            'data' => [
                'guide_id' => (int) $id,
                'bookmarked' => true,
                'bookmarked_at' => $createdAt->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/guides/{id}/unbookmark",
     *     summary="Retirer un guide des favoris",
     *     tags={"Guide Bookmarks"},
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
     *         description="Guide retiré des favoris avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Guide retiré des favoris avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="guide_id", type="integer", example=1),
     *                 @OA\Property(property="bookmarked", type="boolean", example=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guide non trouvé ou bookmark inexistant"
     *     )
     * )
     */
    public function unbookmark($id)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide not found'
            ], 404);
        }

        $user = Auth::user();

        $deleted = DB::table('guide_bookmarks')
            ->where('guide_id', $id)
            ->where('user_id', $user->id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'This guide is not in your bookmarks',
                'data' => [
                    'guide_id' => (int) $id,
                    'bookmarked' => false
                ]
            ], 404);
        }

        return response()->json([
            'message' => 'Guide removed from bookmarks successfully',
            'data' => [
                'guide_id' => (int) $id,
                'bookmarked' => false
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/guides/{id}/toggle-bookmark",
     *     summary="Toggle bookmark d'un guide (bookmark/unbookmark automatique)",
     *     tags={"Guide Bookmarks"},
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
     *             @OA\Property(property="message", type="string", example="Guide ajouté aux favoris avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="guide_id", type="integer", example=1),
     *                 @OA\Property(property="bookmarked", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guide non trouvé"
     *     )
     * )
     */
    public function toggleBookmark($id)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide not found'
            ], 404);
        }

        $user = Auth::user();

        $existingBookmark = DB::table('guide_bookmarks')
            ->where('guide_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingBookmark) {
            // Unbookmark
            DB::table('guide_bookmarks')
                ->where('guide_id', $id)
                ->where('user_id', $user->id)
                ->delete();

            return response()->json([
                'message' => 'Guide removed from bookmarks successfully',
                'data' => [
                    'guide_id' => (int) $id,
                    'bookmarked' => false
                ]
            ]);
        } else {
            // Bookmark
            $createdAt = now();
            DB::table('guide_bookmarks')->insert([
                'guide_id' => $id,
                'user_id' => $user->id,
                'created_at' => $createdAt
            ]);

            return response()->json([
                'message' => 'Guide added to bookmarks successfully',
                'data' => [
                    'guide_id' => (int) $id,
                    'bookmarked' => true,
                    'bookmarked_at' => $createdAt->format('Y-m-d H:i:s')
                ]
            ]);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/guides/my/bookmarks",
     *     summary="Liste des guides bookmarkés par l'utilisateur connecté",
     *     tags={"Guide Bookmarks"},
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
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Tri: latest (bookmarkés récemment), oldest, title",
     *         required=false,
     *         @OA\Schema(type="string", enum={"latest", "oldest", "title"}, default="latest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des guides bookmarkés",
     *         @OA\JsonContent(
     *             @OA\Property(property="total", type="integer", example=25),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="per_page", type="integer", example=10),
     *             @OA\Property(
     *                 property="bookmarks",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Guide complet de maintenance"),
     *                     @OA\Property(property="slug", type="string", example="guide-complet-maintenance"),
     *                     @OA\Property(property="excerpt", type="string"),
     *                     @OA\Property(property="featured_image", type="string"),
     *                     @OA\Property(property="views_count", type="integer"),
     *                     @OA\Property(property="bookmarked_at", type="string", example="2025-10-17 10:30:00"),
     *                     @OA\Property(
     *                         property="author",
     *                         type="object",
     *                         @OA\Property(property="name", type="string")
     *                     ),
     *                     @OA\Property(
     *                         property="category",
     *                         type="object",
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="slug", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function myBookmarks(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->get('per_page', 10);
        $sort = $request->get('sort', 'latest');

        $query = DB::table('guide_bookmarks')
            ->join('guides', 'guide_bookmarks.guide_id', '=', 'guides.id')
            ->join('users', 'guides.author_id', '=', 'users.id')
            ->leftJoin('guide_categories', 'guides.category_id', '=', 'guide_categories.id')
            ->where('guide_bookmarks.user_id', $user->id)
            ->where('guides.status', 'published')
            ->select(
                'guides.id',
                'guides.title',
                'guides.slug',
                'guides.excerpt',
                'guides.featured_image',
                'guides.views_count',
                'guide_bookmarks.created_at as bookmarked_at',
                DB::raw('CONCAT(users.first_name, " ", users.last_name) as author_name'),
                'users.profile_picture as author_profile_picture',
                'guide_categories.name as category_name',
                'guide_categories.slug as category_slug'
            );

        // Tri
        switch ($sort) {
            case 'oldest':
                $query->orderBy('guide_bookmarks.created_at', 'asc');
                break;
            case 'title':
                $query->orderBy('guides.title', 'asc');
                break;
            case 'latest':
            default:
                $query->orderBy('guide_bookmarks.created_at', 'desc');
                break;
        }

        $bookmarks = $query->paginate($perPage);

        return response()->json($bookmarks);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/my/bookmarks/count",
     *     summary="Nombre de guides bookmarkés",
     *     tags={"Guide Bookmarks"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Nombre de bookmarks",
     *         @OA\JsonContent(
     *             @OA\Property(property="count", type="integer", example=25)
     *         )
     *     )
     * )
     */
    public function countMyBookmarks()
    {
        $user = Auth::user();

        $count = DB::table('guide_bookmarks')
            ->join('guides', 'guide_bookmarks.guide_id', '=', 'guides.id')
            ->where('guide_bookmarks.user_id', $user->id)
            ->where('guides.status', 'published')
            ->count();

        return response()->json([
            'count' => $count
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/guides/my/bookmarks/clear",
     *     summary="Supprimer tous les bookmarks de l'utilisateur",
     *     tags={"Guide Bookmarks"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Tous les favoris ont été supprimés",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Tous les favoris ont été supprimés avec succès"),
     *             @OA\Property(property="deleted_count", type="integer", example=12)
     *         )
     *     )
     * )
     */
    public function clearAllBookmarks()
    {
        $user = Auth::user();

        $deletedCount = DB::table('guide_bookmarks')
            ->where('user_id', $user->id)
            ->delete();

        return response()->json([
            'message' => 'All bookmarks have been cleared successfully',
            'deleted_count' => $deletedCount
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/{id}/bookmark-status",
     *     summary="Vérifier si un guide est bookmarké par l'utilisateur",
     *     tags={"Guide Bookmarks"},
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
     *         description="Statut du bookmark",
     *         @OA\JsonContent(
     *             @OA\Property(property="guide_id", type="integer", example=1),
     *             @OA\Property(property="bookmarked", type="boolean", example=true),
     *             @OA\Property(property="bookmarked_at", type="string", example="2025-10-17 10:30:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Guide non trouvé"
     *     )
     * )
     */
    public function checkBookmarkStatus($id)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide not found'
            ], 404);
        }

        $user = Auth::user();

        $bookmark = DB::table('guide_bookmarks')
            ->where('guide_id', $id)
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'guide_id' => (int) $id,
            'bookmarked' => $bookmark !== null,
            'bookmarked_at' => $bookmark ? $bookmark->created_at : null
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/guides/bookmarks/batch",
     *     summary="Vérifier le statut bookmark de plusieurs guides",
     *     tags={"Guide Bookmarks"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"guide_ids"},
     *             @OA\Property(
     *                 property="guide_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 3, 4, 5}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statuts des bookmarks",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="guide_id", type="integer"),
     *                 @OA\Property(property="bookmarked", type="boolean")
     *             )
     *         )
     *     )
     * )
     */
    public function batchCheckBookmarks(Request $request)
    {
        $request->validate([
            'guide_ids' => 'required|array',
            'guide_ids.*' => 'integer|exists:guides,id'
        ]);

        $user = Auth::user();
        $guideIds = $request->guide_ids;

        $bookmarkedGuides = DB::table('guide_bookmarks')
            ->where('user_id', $user->id)
            ->whereIn('guide_id', $guideIds)
            ->pluck('guide_id')
            ->toArray();

        $result = array_map(function($guideId) use ($bookmarkedGuides) {
            return [
                'guide_id' => $guideId,
                'bookmarked' => in_array($guideId, $bookmarkedGuides)
            ];
        }, $guideIds);

        return response()->json($result);
    }
}
