<?php

namespace App\Http\Controllers;

use App\Models\GuideTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Guide Tags",
 *     description="API Endpoints pour la gestion des tags de guides"
 * )
 */
class GuideTagController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/guides/tags",
     *     summary="Liste tous les tags",
     *     tags={"Guide Tags"},
     *     @OA\Response(response=200, description="Liste des tags")
     * )
     */
    public function index()
    {
        $tags = GuideTag::withCount('guides')->get()->map(function ($tag) {
            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'guides_count' => $tag->guides_count,
                'created_at' => $tag->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json($tags);
    }

    /**
     * @OA\Post(
     *     path="/api/guides/tags",
     *     summary="Créer un nouveau tag",
     *     tags={"Guide Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=201, description="Tag créé")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:guide_tags,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $tag = GuideTag::create($request->all());

        return response()->json([
            'message' => 'Tag créé avec succès',
            'data' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'created_at' => $tag->created_at->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/tags/{slug}",
     *     summary="Afficher les détails d'un tag",
     *     tags={"Guide Tags"},
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Détails du tag")
     * )
     */
    public function show($slug)
    {
        $tag = GuideTag::where('slug', $slug)->withCount('guides')->first();

        if (!$tag) {
            return response()->json([
                'message' => 'Tag non trouvé'
            ], 404);
        }

        return response()->json([
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'guides_count' => $tag->guides_count,
            'created_at' => $tag->created_at->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/guides/tags/{id}",
     *     summary="Mettre à jour un tag",
     *     tags={"Guide Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Tag mis à jour")
     * )
     */
    public function update(Request $request, $id)
    {
        $tag = GuideTag::find($id);

        if (!$tag) {
            return response()->json([
                'message' => 'Tag non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:guide_tags,name,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $tag->update($request->all());

        return response()->json([
            'message' => 'Tag mis à jour avec succès',
            'data' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'updated_at' => $tag->updated_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/guides/tags/{id}",
     *     summary="Supprimer un tag",
     *     tags={"Guide Tags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Tag supprimé")
     * )
     */
    public function destroy($id)
    {
        $tag = GuideTag::find($id);

        if (!$tag) {
            return response()->json([
                'message' => 'Tag non trouvé'
            ], 404);
        }

        $tag->delete();

        return response()->json([
            'message' => 'Tag supprimé avec succès'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/tags/{slug}/guides",
     *     summary="Obtenir tous les guides avec un tag spécifique",
     *     tags={"Guide Tags"},
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Liste des guides")
     * )
     */
    public function getGuidesByTag($slug)
    {
        $tag = GuideTag::where('slug', $slug)->first();

        if (!$tag) {
            return response()->json([
                'message' => 'Tag non trouvé'
            ], 404);
        }

        $guides = $tag->guides()
            ->with(['author', 'category'])
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
                ];
            });

        return response()->json($guides);
    }

    /**
     * @OA\Get(
     *     path="/api/guides/tags/popular",
     *     summary="Obtenir les tags les plus utilisés",
     *     tags={"Guide Tags"},
     *     @OA\Response(response=200, description="Tags populaires")
     * )
     */
    public function popular()
    {
        $tags = GuideTag::withCount('guides')
            ->orderBy('guides_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'guides_count' => $tag->guides_count,
                ];
            });

        return response()->json($tags);
    }
}
