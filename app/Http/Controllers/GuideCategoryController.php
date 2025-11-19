<?php

namespace App\Http\Controllers;

use App\Models\GuideCategory;
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
     *     description="Récupère la liste complète des catégories avec le nombre de guides associés",
     *     tags={"Guide Categories"},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des catégories récupérée avec succès",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Entretien"),
     *                 @OA\Property(property="slug", type="string", example="entretien"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Guides d'entretien pour motos"),
     *                 @OA\Property(property="icon", type="string", nullable=true, example="wrench"),
     *                 @OA\Property(property="color", type="string", nullable=true, example="#FF5722"),
     *                 @OA\Property(property="guides_count", type="integer", example=15),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-15 10:30:00"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15 10:30:00")
     *             )
     *         )
     *     )
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
     *     description="Crée une nouvelle catégorie de guide. Le slug est généré automatiquement à partir du nom. Nécessite une authentification.",
     *     tags={"Guide Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 maxLength=255,
     *                 example="Sécurité Routière",
     *                 description="Nom de la catégorie (le slug sera généré automatiquement)"
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 nullable=true,
     *                 example="Conseils et guides pour une conduite sécuritaire"
     *             ),
     *             @OA\Property(
     *                 property="icon",
     *                 type="string",
     *                 maxLength=255,
     *                 nullable=true,
     *                 example="shield"
     *             ),
     *             @OA\Property(
     *                 property="color",
     *                 type="string",
     *                 maxLength=50,
     *                 nullable=true,
     *                 example="#4CAF50"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Catégorie créée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Catégorie créée avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=5),
     *                 @OA\Property(property="name", type="string", example="Sécurité Routière"),
     *                 @OA\Property(property="slug", type="string", example="securite-routiere", description="Généré automatiquement"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Conseils et guides pour une conduite sécuritaire"),
     *                 @OA\Property(property="icon", type="string", nullable=true, example="shield"),
     *                 @OA\Property(property="color", type="string", nullable=true, example="#4CAF50"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-15 10:30:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="name",
     *                     type="array",
     *                     @OA\Items(type="string", example="Le champ name est requis.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
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
     *     description="Récupère les informations détaillées d'une catégorie spécifique avec le nombre de guides associés",
     *     tags={"Guide Categories"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la catégorie",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de la catégorie",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Entretien"),
     *             @OA\Property(property="slug", type="string", example="entretien"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Guides d'entretien pour motos"),
     *             @OA\Property(property="icon", type="string", nullable=true, example="wrench"),
     *             @OA\Property(property="color", type="string", nullable=true, example="#FF5722"),
     *             @OA\Property(property="guides_count", type="integer", example=15),
     *             @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-15 10:30:00"),
     *             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15 10:30:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Catégorie non trouvée",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Catégorie non trouvée")
     *         )
     *     )
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
     *     description="Met à jour les informations d'une catégorie existante. Si le nom est modifié, le slug sera automatiquement régénéré. Nécessite une authentification.",
     *     tags={"Guide Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la catégorie à mettre à jour",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 maxLength=255,
     *                 example="Entretien Moto",
     *                 description="Nom de la catégorie (le slug sera régénéré automatiquement)"
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 nullable=true,
     *                 example="Guides complets d'entretien pour motos"
     *             ),
     *             @OA\Property(
     *                 property="icon",
     *                 type="string",
     *                 maxLength=255,
     *                 nullable=true,
     *                 example="tool"
     *             ),
     *             @OA\Property(
     *                 property="color",
     *                 type="string",
     *                 maxLength=50,
     *                 nullable=true,
     *                 example="#2196F3"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Catégorie mise à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Catégorie mise à jour avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Entretien Moto"),
     *                 @OA\Property(property="slug", type="string", example="entretien-moto", description="Régénéré automatiquement si le nom change"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Guides complets d'entretien pour motos"),
     *                 @OA\Property(property="icon", type="string", nullable=true, example="tool"),
     *                 @OA\Property(property="color", type="string", nullable=true, example="#2196F3"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15 10:30:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Catégorie non trouvée",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Catégorie non trouvée")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="name",
     *                     type="array",
     *                     @OA\Items(type="string", example="Ce nom de catégorie existe déjà.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
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
     *     description="Supprime une catégorie de guide. Nécessite une authentification.",
     *     tags={"Guide Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la catégorie à supprimer",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Catégorie supprimée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Catégorie supprimée avec succès")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Catégorie non trouvée",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Catégorie non trouvée")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
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
     *     description="Récupère la liste de tous les guides publiés appartenant à une catégorie spécifique, triés par date de publication décroissante",
     *     tags={"Guide Categories"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la catégorie",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des guides de la catégorie",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=10),
     *                 @OA\Property(property="title", type="string", example="Comment changer l'huile de votre moto"),
     *                 @OA\Property(property="slug", type="string", example="comment-changer-huile-moto"),
     *                 @OA\Property(property="excerpt", type="string", nullable=true, example="Guide complet pour la vidange d'huile moteur"),
     *                 @OA\Property(property="featured_image", type="string", nullable=true, example="https://example.com/images/guide.jpg"),
     *                 @OA\Property(property="views_count", type="integer", example=1250),
     *                 @OA\Property(property="likes_count", type="integer", example=85),
     *                 @OA\Property(property="comments_count", type="integer", example=23),
     *                 @OA\Property(property="published_at", type="string", format="date-time", example="2025-01-10 14:30:00"),
     *                 @OA\Property(
     *                     property="author",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="name", type="string", example="Ahmed Benali"),
     *                     @OA\Property(property="profile_picture", type="string", nullable=true, example="https://example.com/profiles/ahmed.jpg")
     *                 ),
     *                 @OA\Property(
     *                     property="tags",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="Maintenance"),
     *                         @OA\Property(property="slug", type="string", example="maintenance")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Catégorie non trouvée",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Catégorie non trouvée")
     *         )
     *     )
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
