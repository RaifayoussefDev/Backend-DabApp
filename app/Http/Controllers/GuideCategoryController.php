<?php

namespace App\Http\Controllers;

use App\Models\GuideCategory; // Ensure this path matches the actual location of the GuideCategory model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Guide Categories",
 *     description="API Endpoints pour la gestion des catégories de guides"
 * )
 */
class GuideCategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/guides/categories",
     *     summary="Liste toutes les catégories de guides",
     *     tags={"Guide Categories"},
     *     @OA\Response(response=200, description="Liste des catégories")
     * )
     */
    public function index()
    {
        $categories = GuideCategory::withCount('guides')->get()->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'icon' => $category->icon,
                'color' => $category->color,
                'guides_count' => $category->guides_count,
                'created_at' => $category->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $category->updated_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json($categories);
    }

    /**
     * @OA\Post(
     *     path="/api/guides/categories",
     *     summary="Créer une nouvelle catégorie",
     *     tags={"Guide Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=201, description="Catégorie créée")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:guide_categories,name',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $category = GuideCategory::create($request->all());

        return response()->json([
            'message' => 'Catégorie créée avec succès',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'icon' => $category->icon,
                'color' => $category->color,
                'created_at' => $category->created_at->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/categories/{id}",
     *     summary="Afficher les détails d'une catégorie",
     *     tags={"Guide Categories"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Détails de la catégorie")
     * )
     */
    public function show($id)
    {
        $category = GuideCategory::withCount('guides')->find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        return response()->json([
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'icon' => $category->icon,
            'color' => $category->color,
            'guides_count' => $category->guides_count,
            'created_at' => $category->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $category->updated_at->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/guides/categories/{id}",
     *     summary="Mettre à jour une catégorie",
     *     tags={"Guide Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Catégorie mise à jour")
     * )
     */
    public function update(Request $request, $id)
    {
        $category = GuideCategory::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:guide_categories,name,' . $id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $category->update($request->all());

        return response()->json([
            'message' => 'Catégorie mise à jour avec succès',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'icon' => $category->icon,
                'color' => $category->color,
                'updated_at' => $category->updated_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/guides/categories/{id}",
     *     summary="Supprimer une catégorie",
     *     tags={"Guide Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Catégorie supprimée")
     * )
     */
    public function destroy($id)
    {
        $category = GuideCategory::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        $category->delete();

        return response()->json([
            'message' => 'Catégorie supprimée avec succès'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/categories/{id}/guides",
     *     summary="Obtenir tous les guides d'une catégorie",
     *     tags={"Guide Categories"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Liste des guides")
     * )
     */
    public function getGuidesByCategory($id)
    {
        $category = GuideCategory::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        $guides = $category->guides()
            ->with(['author', 'tags'])
            ->where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->get()
            ->map(function ($guide) {
                return [
                    'id' => $guide->id,
                    'title' => $guide->title,
                    'slug' => $guide->slug,
                    'excerpt' => $guide->excerpt,
                    'featured_image' => $guide->featured_image,
                    'views_count' => $guide->views_count,
                    'likes_count' => $guide->likes()->count(),
                    'comments_count' => $guide->allComments()->count(),
                    'published_at' => $guide->published_at->format('Y-m-d H:i:s'),
                    'author' => [
                        'id' => $guide->author->id,
                        'name' => $guide->author->first_name . ' ' . $guide->author->last_name,
                        'profile_picture' => $guide->author->profile_picture,
                    ],
                    'tags' => $guide->tags->map(function ($tag) {
                        return [
                            'id' => $tag->id,
                            'name' => $tag->name,
                            'slug' => $tag->slug,
                        ];
                    }),
                ];
            });

        return response()->json($guides);
    }
}
