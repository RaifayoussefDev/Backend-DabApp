<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use App\Models\GuideImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Guides",
 *     description="API Endpoints pour la gestion des guides"
 * )
 */
class GuideController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/guides",
     *     summary="Liste tous les guides avec filtres",
     *     tags={"Guides"},
     *     @OA\Response(response=200, description="Liste des guides")
     * )
     */

    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Guide::with(['author', 'category', 'tags', 'images'])
            ->where('status', 'published')
            ->whereNotNull('published_at'); // ✅ FIX 1: Vérifier que published_at n'est pas null

        // Filtres
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('tag')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('slug', $request->tag);
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_featured') && $request->is_featured == '1') {
            $query->where('is_featured', true);
        }

        switch ($request->get('sort', 'latest')) {
            case 'popular':
            case 'views':
                $query->orderBy('views_count', 'desc');
                break;
            default:
                $query->orderBy('published_at', 'desc');
        }

        $guides = $query->get()->map(function ($guide) use ($user) {
            $isLiked = false;
            $isBookmarked = false;

            if ($user) {
                $isLiked = DB::table('guide_likes')
                    ->where('user_id', $user->id)
                    ->where('guide_id', $guide->id)
                    ->exists();

                $isBookmarked = DB::table('guide_bookmarks')
                    ->where('user_id', $user->id)
                    ->where('guide_id', $guide->id)
                    ->exists();
            }

            return [
                'id' => $guide->id,
                'title' => $guide->title,
                'slug' => $guide->slug,
                'excerpt' => $guide->excerpt,
                'featured_image' => $guide->featured_image,
                'views_count' => $guide->views_count,
                'likes_count' => $guide->likes()->count(),
                'comments_count' => $guide->allComments()->count(),
                'is_featured' => $guide->is_featured,
                'published_at' => $guide->published_at ? $guide->published_at->format('Y-m-d H:i:s') : null,
                'author' => [
                    'id' => $guide->author->id,
                    'name' => $guide->author->first_name . ' ' . $guide->author->last_name,
                    'profile_picture' => $guide->author->profile_picture,
                ],
                'category' => $guide->category ? [
                    'id' => $guide->category->id,
                    'name' => $guide->category->name,
                    'slug' => $guide->category->slug,
                ] : null,
                'tags' => $guide->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ];
                }),
                'images' => $guide->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'image_url' => $image->image_url,
                        'caption' => $image->caption,
                        'order_position' => $image->order_position,
                    ];
                }),
                'liked' => $isLiked,
                'bookmarked' => $isBookmarked,
            ];
        });

        return response()->json($guides);
    }

    /**
     * @OA\Post(
     *     path="/api/guides",
     *     summary="Créer un nouveau guide",
     *     tags={"Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "content"},
     *             @OA\Property(property="title", type="string", example="Complete Motorcycle Maintenance Guide"),
     *             @OA\Property(property="content", type="string", example="This comprehensive guide covers all aspects of motorcycle maintenance..."),
     *             @OA\Property(property="excerpt", type="string", example="Learn how to maintain your motorcycle properly"),
     *             @OA\Property(property="featured_image", type="string", example="https://example.com/maintenance-cover.jpg"),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(
     *                 property="tags",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 3}
     *             ),
     *             @OA\Property(property="is_featured", type="boolean", example=false),
     *             @OA\Property(
     *                 property="images",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="image_url", type="string", example="https://example.com/image1.jpg"),
     *                     @OA\Property(property="caption", type="string", example="Oil change procedure"),
     *                     @OA\Property(property="order_position", type="integer", example=0)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Guide créé avec succès"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'featured_image' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:guide_categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:guide_tags,id',
            'is_featured' => 'nullable|boolean',
            // Validation pour les images
            'images' => 'nullable|array',
            'images.*.image_url' => 'required|string|max:255',
            'images.*.caption' => 'nullable|string',
            'images.*.order_position' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $guide = Guide::create([
            'title' => $request->title,
            'content' => $request->content,
            'excerpt' => $request->excerpt,
            'featured_image' => $request->featured_image,
            'category_id' => $request->category_id,
            'author_id' => Auth::id(),
            'status' => 'draft',
            'is_featured' => $request->is_featured ?? false,
        ]);

        // Ajouter les tags
        if ($request->has('tags')) {
            $guide->tags()->sync($request->tags);
        }

        // Ajouter les images
        if ($request->has('images') && is_array($request->images)) {
            foreach ($request->images as $index => $imageData) {
                GuideImage::create([
                    'guide_id' => $guide->id,
                    'image_url' => $imageData['image_url'],
                    'caption' => $imageData['caption'] ?? null,
                    'order_position' => $imageData['order_position'] ?? $index,
                ]);
            }
        }

        // Recharger le guide avec toutes ses relations
        $guide->load(['author', 'category', 'tags', 'images']);

        return response()->json([
            'message' => 'Guide créé avec succès',
            'data' => [
                'id' => $guide->id,
                'title' => $guide->title,
                'slug' => $guide->slug,
                'status' => $guide->status,
                'category' => $guide->category ? [
                    'id' => $guide->category->id,
                    'name' => $guide->category->name,
                    'slug' => $guide->category->slug,
                ] : null,
                'tags' => $guide->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ];
                }),
                'images' => $guide->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'image_url' => $image->image_url,
                        'caption' => $image->caption,
                        'order_position' => $image->order_position,
                    ];
                }),
                'created_at' => $guide->created_at->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/{slug}",
     *     summary="Afficher les détails d'un guide",
     *     tags={"Guides"},
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Détails du guide")
     * )
     */
    public function show($slug)
    {
        $user = Auth::user();

        $guide = Guide::with(['author', 'category', 'tags', 'images'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();

        if (!$guide) {
            return response()->json([
                'message' => 'Guide non trouvé'
            ], 404);
        }

        // Incrémenter les vues
        $guide->increment('views_count');

        $isLiked = false;
        $isBookmarked = false;

        if ($user) {
            $isLiked = DB::table('guide_likes')
                ->where('user_id', $user->id)
                ->where('guide_id', $guide->id)
                ->exists();

            $isBookmarked = DB::table('guide_bookmarks')
                ->where('user_id', $user->id)
                ->where('guide_id', $guide->id)
                ->exists();
        }

        return response()->json([
            'id' => $guide->id,
            'title' => $guide->title,
            'slug' => $guide->slug,
            'content' => $guide->content,
            'excerpt' => $guide->excerpt,
            'featured_image' => $guide->featured_image,
            'views_count' => $guide->views_count,
            'likes_count' => $guide->likes()->count(),
            'comments_count' => $guide->allComments()->count(),
            'is_featured' => $guide->is_featured,
            'published_at' => $guide->published_at->format('Y-m-d H:i:s'),
            'author' => [
                'id' => $guide->author->id,
                'name' => $guide->author->first_name . ' ' . $guide->author->last_name,
                'email' => $guide->author->email,
                'profile_picture' => $guide->author->profile_picture,
            ],
            'category' => $guide->category ? [
                'id' => $guide->category->id,
                'name' => $guide->category->name,
                'slug' => $guide->category->slug,
                'color' => $guide->category->color,
            ] : null,
            'tags' => $guide->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ];
            }),
            'images' => $guide->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'image_url' => $image->image_url,
                    'caption' => $image->caption,
                    'order_position' => $image->order_position,
                ];
            }),
            'liked' => $isLiked,
            'bookmarked' => $isBookmarked,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/guides/{id}",
     *     summary="Mettre à jour un guide",
     *     tags={"Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Guide mis à jour")
     * )
     */
    public function update(Request $request, $id)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide non trouvé'
            ], 404);
        }

        // Vérifier autorisation (auteur ou admin role_id = 1)
        $user = Auth::user();
        if ($guide->author_id !== $user->id && $user->role_id != 1) {
            return response()->json([
                'message' => 'Non autorisé'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'excerpt' => 'nullable|string',
            'featured_image' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:guide_categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:guide_tags,id',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $guide->update($request->only([
            'title',
            'content',
            'excerpt',
            'featured_image',
            'category_id',
            'is_featured'
        ]));

        if ($request->has('tags')) {
            $guide->tags()->sync($request->tags);
        }

        return response()->json([
            'message' => 'Guide mis à jour avec succès',
            'data' => [
                'id' => $guide->id,
                'title' => $guide->title,
                'slug' => $guide->slug,
                'updated_at' => $guide->updated_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/guides/{id}",
     *     summary="Supprimer un guide",
     *     tags={"Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Guide supprimé")
     * )
     */
    public function destroy($id)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide non trouvé'
            ], 404);
        }

        // Vérifier autorisation (auteur ou admin role_id = 1)
        $user = Auth::user();
        if ($guide->author_id !== $user->id && $user->role_id != 1) {
            return response()->json([
                'message' => 'Non autorisé'
            ], 403);
        }

        $guide->delete();

        return response()->json([
            'message' => 'Guide supprimé avec succès'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/featured",
     *     summary="Guides mis en avant",
     *     tags={"Guides"},
     *     @OA\Response(response=200, description="Guides featured")
     * )
     */
    public function featured()
    {
        $guides = Guide::with(['author', 'category', 'tags'])
            ->where('status', 'published')
            ->where('is_featured', true)
            ->orderBy('published_at', 'desc')
            ->limit(6)
            ->get()
            ->map(function ($guide) {
                return [
                    'id' => $guide->id,
                    'title' => $guide->title,
                    'slug' => $guide->slug,
                    'excerpt' => $guide->excerpt,
                    'featured_image' => $guide->featured_image,
                    'views_count' => $guide->views_count,
                    'published_at' => $guide->published_at ? $guide->published_at->format('Y-m-d H:i:s') : null,
                    'author' => [
                        'name' => $guide->author->first_name . ' ' . $guide->author->last_name,
                    ],
                ];
            });

        return response()->json($guides);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/popular",
     *     summary="Guides populaires",
     *     tags={"Guides"},
     *     @OA\Response(response=200, description="Guides populaires")
     * )
     */
    public function popular()
    {
        $guides = Guide::with(['author', 'category'])
            ->where('status', 'published')
            ->orderBy('views_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($guide) {
                return [
                    'id' => $guide->id,
                    'title' => $guide->title,
                    'slug' => $guide->slug,
                    'excerpt' => $guide->excerpt,
                    'featured_image' => $guide->featured_image,
                    'views_count' => $guide->views_count,
                    'published_at' => $guide->published_at ? $guide->published_at->format('Y-m-d H:i:s') : null,
                ];
            });

        return response()->json($guides);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/latest",
     *     summary="Derniers guides",
     *     tags={"Guides"},
     *     @OA\Response(response=200, description="Derniers guides")
     * )
     */
    public function latest()
    {
        $guides = Guide::with(['author', 'category'])
            ->where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($guide) {
                return [
                    'id' => $guide->id,
                    'title' => $guide->title,
                    'slug' => $guide->slug,
                    'excerpt' => $guide->excerpt,
                    'featured_image' => $guide->featured_image,
                    'published_at' => $guide->published_at ? $guide->published_at->format('Y-m-d H:i:s') : null,
                ];
            });

        return response()->json($guides);
    }

    /**
     * @OA\Post(
     *     path="/api/guides/{id}/publish",
     *     summary="Publier un guide",
     *     tags={"Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Guide publié")
     * )
     */
    public function publish($id)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide non trouvé'
            ], 404);
        }

        // Vérifier autorisation (auteur ou admin role_id = 1)
        $user = Auth::user();
        if ($guide->author_id !== $user->id && $user->role_id != 1) {
            return response()->json([
                'message' => 'Non autorisé'
            ], 403);
        }

        $guide->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return response()->json([
            'message' => 'Guide publié avec succès',
            'data' => [
                'id' => $guide->id,
                'status' => $guide->status,
                'published_at' => $guide->published_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/guides/{id}/archive",
     *     summary="Archiver un guide",
     *     tags={"Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Guide archivé")
     * )
     */
    public function archive($id)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'message' => 'Guide non trouvé'
            ], 404);
        }

        // Vérifier autorisation (auteur ou admin role_id = 1)
        $user = Auth::user();
        if ($guide->author_id !== $user->id && $user->role_id != 1) {
            return response()->json([
                'message' => 'Non autorisé'
            ], 403);
        }

        $guide->update(['status' => 'archived']);

        return response()->json([
            'message' => 'Guide archivé avec succès'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/my/guides",
     *     summary="Mes guides",
     *     tags={"Guides"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Mes guides")
     * )
     */
    public function myGuides()
    {
        $guides = Guide::with(['category', 'tags'])
            ->where('author_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($guide) {
                return [
                    'id' => $guide->id,
                    'title' => $guide->title,
                    'slug' => $guide->slug,
                    'status' => $guide->status,
                    'views_count' => $guide->views_count,
                    'likes_count' => $guide->likes()->count(),
                    'comments_count' => $guide->allComments()->count(),
                    'created_at' => $guide->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json($guides);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/id/{id}",
     *     summary="Afficher les détails d'un guide par ID",
     *     tags={"Guides"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Détails du guide"),
     *     @OA\Response(response=404, description="Guide non trouvé")
     * )
     */
    public function showById($id)
    {
        $user = Auth::user();

        $guide = Guide::with(['author', 'category', 'tags', 'images'])
            ->where('id', $id)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->first();

        if (!$guide) {
            return response()->json([
                'message' => 'Guide non trouvé'
            ], 404);
        }

        // Incrémenter les vues
        $guide->increment('views_count');

        $isLiked = false;
        $isBookmarked = false;

        if ($user) {
            $isLiked = DB::table('guide_likes')
                ->where('user_id', $user->id)
                ->where('guide_id', $guide->id)
                ->exists();

            $isBookmarked = DB::table('guide_bookmarks')
                ->where('user_id', $user->id)
                ->where('guide_id', $guide->id)
                ->exists();
        }

        return response()->json([
            'id' => $guide->id,
            'title' => $guide->title,
            'slug' => $guide->slug,
            'content' => $guide->content,
            'excerpt' => $guide->excerpt,
            'featured_image' => $guide->featured_image,
            'views_count' => $guide->views_count,
            'likes_count' => $guide->likes()->count(),
            'comments_count' => $guide->allComments()->count(),
            'is_featured' => $guide->is_featured,
            'published_at' => $guide->published_at->format('Y-m-d H:i:s'),
            'author' => [
                'id' => $guide->author->id,
                'name' => $guide->author->first_name . ' ' . $guide->author->last_name,
                'email' => $guide->author->email,
                'profile_picture' => $guide->author->profile_picture,
            ],
            'category' => $guide->category ? [
                'id' => $guide->category->id,
                'name' => $guide->category->name,
                'slug' => $guide->category->slug,
            ] : null,
            'tags' => $guide->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ];
            }),
            'images' => $guide->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'image_url' => $image->image_url,
                    'caption' => $image->caption,
                    'order_position' => $image->order_position,
                ];
            }),
            'liked' => $isLiked,
            'bookmarked' => $isBookmarked,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/category/{category_id}",
     *     summary="Liste des guides par catégorie",
     *     tags={"Guides"},
     *     @OA\Parameter(
     *         name="category_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Tri: latest, popular",
     *         @OA\Schema(type="string", enum={"latest", "popular"}, default="latest")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Nombre de résultats",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(response=200, description="Liste des guides de la catégorie")
     * )
     */
    public function getByCategory($category_id, Request $request)
    {
        $user = Auth::user();

        $query = Guide::with(['author', 'category', 'tags', 'images'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('category_id', $category_id);

        // Tri
        $sort = $request->get('sort', 'latest');
        if ($sort === 'popular') {
            $query->orderBy('views_count', 'desc');
        } else {
            $query->orderBy('published_at', 'desc');
        }

        // Limite
        $limit = $request->get('limit', 10);
        $query->limit($limit);

        $guides = $query->get()->map(function ($guide) use ($user) {
            $isLiked = false;
            $isBookmarked = false;

            if ($user) {
                $isLiked = DB::table('guide_likes')
                    ->where('user_id', $user->id)
                    ->where('guide_id', $guide->id)
                    ->exists();

                $isBookmarked = DB::table('guide_bookmarks')
                    ->where('user_id', $user->id)
                    ->where('guide_id', $guide->id)
                    ->exists();
            }

            return [
                'id' => $guide->id,
                'title' => $guide->title,
                'slug' => $guide->slug,
                'excerpt' => $guide->excerpt,
                'featured_image' => $guide->featured_image,
                'views_count' => $guide->views_count,
                'likes_count' => $guide->likes()->count(),
                'comments_count' => $guide->allComments()->count(),
                'is_featured' => $guide->is_featured,
                'published_at' => $guide->published_at ? $guide->published_at->format('Y-m-d H:i:s') : null,
                'author' => [
                    'id' => $guide->author->id,
                    'name' => $guide->author->first_name . ' ' . $guide->author->last_name,
                    'profile_picture' => $guide->author->profile_picture,
                ],
                'category' => $guide->category ? [
                    'id' => $guide->category->id,
                    'name' => $guide->category->name,
                    'slug' => $guide->category->slug,
                ] : null,
                'tags' => $guide->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ];
                }),
                'images' => $guide->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'image_url' => $image->image_url,
                        'caption' => $image->caption,
                        'order_position' => $image->order_position,
                    ];
                }),
                'liked' => $isLiked,
                'bookmarked' => $isBookmarked,
            ];
        });

        return response()->json([
            'category_id' => (int) $category_id,
            'total' => $guides->count(),
            'guides' => $guides
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/tag/{tag_slug}",
     *     summary="Liste des guides par tag (slug)",
     *     tags={"Guides"},
     *     @OA\Parameter(
     *         name="tag_slug",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Tri: latest, popular",
     *         @OA\Schema(type="string", enum={"latest", "popular"}, default="latest")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Nombre de résultats",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(response=200, description="Liste des guides du tag")
     * )
     */
    public function getByTag($tag_slug, Request $request)
    {
        $user = Auth::user();

        $query = Guide::with(['author', 'category', 'tags', 'images'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereHas('tags', function ($q) use ($tag_slug) {
                $q->where('slug', $tag_slug);
            });

        // Tri
        $sort = $request->get('sort', 'latest');
        if ($sort === 'popular') {
            $query->orderBy('views_count', 'desc');
        } else {
            $query->orderBy('published_at', 'desc');
        }

        // Limite
        $limit = $request->get('limit', 10);
        $query->limit($limit);

        $guides = $query->get()->map(function ($guide) use ($user) {
            $isLiked = false;
            $isBookmarked = false;

            if ($user) {
                $isLiked = DB::table('guide_likes')
                    ->where('user_id', $user->id)
                    ->where('guide_id', $guide->id)
                    ->exists();

                $isBookmarked = DB::table('guide_bookmarks')
                    ->where('user_id', $user->id)
                    ->where('guide_id', $guide->id)
                    ->exists();
            }

            return [
                'id' => $guide->id,
                'title' => $guide->title,
                'slug' => $guide->slug,
                'excerpt' => $guide->excerpt,
                'featured_image' => $guide->featured_image,
                'views_count' => $guide->views_count,
                'likes_count' => $guide->likes()->count(),
                'comments_count' => $guide->allComments()->count(),
                'is_featured' => $guide->is_featured,
                'published_at' => $guide->published_at ? $guide->published_at->format('Y-m-d H:i:s') : null,
                'author' => [
                    'id' => $guide->author->id,
                    'name' => $guide->author->first_name . ' ' . $guide->author->last_name,
                    'profile_picture' => $guide->author->profile_picture,
                ],
                'category' => $guide->category ? [
                    'id' => $guide->category->id,
                    'name' => $guide->category->name,
                    'slug' => $guide->category->slug,
                ] : null,
                'tags' => $guide->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ];
                }),
                'images' => $guide->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'image_url' => $image->image_url,
                        'caption' => $image->caption,
                        'order_position' => $image->order_position,
                    ];
                }),
                'liked' => $isLiked,
                'bookmarked' => $isBookmarked,
            ];
        });

        return response()->json([
            'tag_slug' => $tag_slug,
            'total' => $guides->count(),
            'guides' => $guides
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/tag/id/{tag_id}",
     *     summary="Liste des guides par tag ID",
     *     tags={"Guides"},
     *     @OA\Parameter(
     *         name="tag_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Tri: latest, popular",
     *         @OA\Schema(type="string", enum={"latest", "popular"}, default="latest")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Nombre de résultats",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(response=200, description="Liste des guides du tag")
     * )
     */
    public function getByTagId($tag_id, Request $request)
    {
        $user = Auth::user();

        $query = Guide::with(['author', 'category', 'tags', 'images'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereHas('tags', function ($q) use ($tag_id) {
                $q->where('guide_tags.id', $tag_id);
            });

        // Tri
        $sort = $request->get('sort', 'latest');
        if ($sort === 'popular') {
            $query->orderBy('views_count', 'desc');
        } else {
            $query->orderBy('published_at', 'desc');
        }

        // Limite
        $limit = $request->get('limit', 10);
        $query->limit($limit);

        $guides = $query->get()->map(function ($guide) use ($user) {
            $isLiked = false;
            $isBookmarked = false;

            if ($user) {
                $isLiked = DB::table('guide_likes')
                    ->where('user_id', $user->id)
                    ->where('guide_id', $guide->id)
                    ->exists();

                $isBookmarked = DB::table('guide_bookmarks')
                    ->where('user_id', $user->id)
                    ->where('guide_id', $guide->id)
                    ->exists();
            }

            return [
                'id' => $guide->id,
                'title' => $guide->title,
                'slug' => $guide->slug,
                'excerpt' => $guide->excerpt,
                'featured_image' => $guide->featured_image,
                'views_count' => $guide->views_count,
                'likes_count' => $guide->likes()->count(),
                'comments_count' => $guide->allComments()->count(),
                'is_featured' => $guide->is_featured,
                'published_at' => $guide->published_at ? $guide->published_at->format('Y-m-d H:i:s') : null,
                'author' => [
                    'id' => $guide->author->id,
                    'name' => $guide->author->first_name . ' ' . $guide->author->last_name,
                    'profile_picture' => $guide->author->profile_picture,
                ],
                'category' => $guide->category ? [
                    'id' => $guide->category->id,
                    'name' => $guide->category->name,
                    'slug' => $guide->category->slug,
                ] : null,
                'tags' => $guide->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ];
                }),
                'images' => $guide->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'image_url' => $image->image_url,
                        'caption' => $image->caption,
                        'order_position' => $image->order_position,
                    ];
                }),
                'liked' => $isLiked,
                'bookmarked' => $isBookmarked,
            ];
        });

        return response()->json([
            'tag_id' => (int) $tag_id,
            'total' => $guides->count(),
            'guides' => $guides
        ]);
    }
}
