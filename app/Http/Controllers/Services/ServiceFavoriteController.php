<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ServiceFavorite;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Service Favorites",
 *     description="API endpoints pour gérer les services favoris de l'utilisateur"
 * )
 */
class ServiceFavoriteController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/my-favorite-services",
     *     summary="Mes services favoris",
     *     description="Récupère la liste de tous les services ajoutés aux favoris par l'utilisateur",
     *     operationId="getMyFavorites",
     *     tags={"Service Favorites"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filtrer par catégorie",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="city_id",
     *         in="query",
     *         description="Filtrer par ville",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="is_available",
     *         in="query",
     *         description="Filtrer par disponibilité",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1})
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre par page",
     *         required=false,
     *         @OA\Schema(type="integer", example=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Favoris récupérés avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="service_id", type="integer"),
     *                         @OA\Property(property="favorited_at", type="string", format="date-time"),
     *                         @OA\Property(
     *                             property="service",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="name", type="string"),
     *                             @OA\Property(property="name_ar", type="string"),
     *                             @OA\Property(property="price", type="number"),
     *                             @OA\Property(property="is_available", type="boolean"),
     *                             @OA\Property(property="image", type="string"),
     *                             @OA\Property(
     *                                 property="provider",
     *                                 type="object",
     *                                 @OA\Property(property="business_name", type="string"),
     *                                 @OA\Property(property="rating_average", type="number")
     *                             ),
     *                             @OA\Property(
     *                                 property="category",
     *                                 type="object",
     *                                 @OA\Property(property="name", type="string")
     *                             )
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="total_favorites", type="integer", example=15)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $query = ServiceFavorite::where('user_id', $user->id)
            ->with([
                'service.category',
                'service.provider.city',
                'service.provider' => function($q) {
                    $q->select('id', 'business_name', 'business_name_ar', 'logo', 'rating_average', 'city_id');
                }
            ]);

        // Filtrer par catégorie
        if ($request->has('category_id')) {
            $query->whereHas('service', function($q) use ($request) {
                $q->where('category_id', $request->category_id);
            });
        }

        // Filtrer par ville
        if ($request->has('city_id')) {
            $query->whereHas('service.provider', function($q) use ($request) {
                $q->where('city_id', $request->city_id);
            });
        }

        // Filtrer par disponibilité
        if ($request->has('is_available')) {
            $query->whereHas('service', function($q) use ($request) {
                $q->where('is_available', $request->is_available);
            });
        }

        $perPage = $request->get('per_page', 20);
        $favorites = $query->latest('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $favorites,
            'total_favorites' => ServiceFavorite::where('user_id', $user->id)->count(),
            'message' => 'Favorites retrieved successfully'
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/services/{id}/favorite",
     *     summary="Ajouter/Retirer des favoris (Toggle)",
     *     description="Ajoute ou retire un service des favoris de l'utilisateur",
     *     operationId="toggleFavorite",
     *     tags={"Service Favorites"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du service",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Action effectuée avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="is_favorited", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Service added to favorites")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Service non trouvé")
     * )
     */
    public function toggle($serviceId)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $service = Service::find($serviceId);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found'
            ], 404);
        }

        try {
            $favorite = ServiceFavorite::where('user_id', $user->id)
                ->where('service_id', $serviceId)
                ->first();

            if ($favorite) {
                // Retirer des favoris
                $favorite->delete();
                $isFavorited = false;
                $message = 'Service removed from favorites';
            } else {
                // Ajouter aux favoris
                ServiceFavorite::create([
                    'user_id' => $user->id,
                    'service_id' => $serviceId
                ]);
                $isFavorited = true;
                $message = 'Service added to favorites';
            }

            return response()->json([
                'success' => true,
                'is_favorited' => $isFavorited,
                'message' => $message
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle favorite',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/services/{id}/favorite/add",
     *     summary="Ajouter aux favoris",
     *     description="Ajoute explicitement un service aux favoris",
     *     operationId="addFavorite",
     *     tags={"Service Favorites"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du service",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Service ajouté aux favoris"
     *     ),
     *     @OA\Response(response=400, description="Service déjà dans les favoris"),
     *     @OA\Response(response=404, description="Service non trouvé")
     * )
     */
    public function add($serviceId)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $service = Service::find($serviceId);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found'
            ], 404);
        }

        $exists = ServiceFavorite::where('user_id', $user->id)
            ->where('service_id', $serviceId)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Service already in favorites'
            ], 400);
        }

        try {
            $favorite = ServiceFavorite::create([
                'user_id' => $user->id,
                'service_id' => $serviceId
            ]);

            return response()->json([
                'success' => true,
                'data' => $favorite->load('service'),
                'message' => 'Service added to favorites'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add favorite',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/services/{id}/favorite/remove",
     *     summary="Retirer des favoris",
     *     description="Retire explicitement un service des favoris",
     *     operationId="removeFavorite",
     *     tags={"Service Favorites"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du service",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Service retiré des favoris"),
     *     @OA\Response(response=404, description="Favori non trouvé")
     * )
     */
    public function remove($serviceId)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $favorite = ServiceFavorite::where('user_id', $user->id)
            ->where('service_id', $serviceId)
            ->first();

        if (!$favorite) {
            return response()->json([
                'success' => false,
                'message' => 'Favorite not found'
            ], 404);
        }

        try {
            $favorite->delete();

            return response()->json([
                'success' => true,
                'message' => 'Service removed from favorites'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove favorite',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/services/{id}/is-favorited",
     *     summary="Vérifier si favori",
     *     description="Vérifie si un service est dans les favoris de l'utilisateur",
     *     operationId="isFavorited",
     *     tags={"Service Favorites"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du service",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statut vérifié",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="is_favorited", type="boolean", example=true)
     *         )
     *     )
     * )
     */
    public function isFavorited($serviceId)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $isFavorited = ServiceFavorite::where('user_id', $user->id)
            ->where('service_id', $serviceId)
            ->exists();

        return response()->json([
            'success' => true,
            'is_favorited' => $isFavorited
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/my-favorite-services/clear",
     *     summary="Vider tous les favoris",
     *     description="Supprime tous les services favoris de l'utilisateur",
     *     operationId="clearAllFavorites",
     *     tags={"Service Favorites"},
     *     security={{"bearer":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Favoris vidés",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="deleted_count", type="integer", example=15),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function clearAll()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $deletedCount = ServiceFavorite::where('user_id', $user->id)->delete();

            return response()->json([
                'success' => true,
                'deleted_count' => $deletedCount,
                'message' => 'All favorites cleared successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear favorites',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/my-favorite-services/stats",
     *     summary="Statistiques des favoris",
     *     description="Récupère les statistiques des services favoris de l'utilisateur",
     *     operationId="getFavoritesStats",
     *     tags={"Service Favorites"},
     *     security={{"bearer":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistiques récupérées",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_favorites", type="integer", example=15),
     *                 @OA\Property(property="available_services", type="integer", example=12),
     *                 @OA\Property(property="unavailable_services", type="integer", example=3),
     *                 @OA\Property(
     *                     property="by_category",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="category_name", type="string"),
     *                         @OA\Property(property="count", type="integer")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function stats()
    {
        $user = JWTAuth::parseToken()->authenticate();

        $totalFavorites = ServiceFavorite::where('user_id', $user->id)->count();

        $availableCount = ServiceFavorite::where('user_id', $user->id)
            ->whereHas('service', function($q) {
                $q->where('is_available', true);
            })
            ->count();

        $byCategory = DB::table('service_favorites')
            ->join('services', 'service_favorites.service_id', '=', 'services.id')
            ->join('service_categories', 'services.category_id', '=', 'service_categories.id')
            ->where('service_favorites.user_id', $user->id)
            ->select('service_categories.name as category_name', 'service_categories.name_ar as category_name_ar', DB::raw('count(*) as count'))
            ->groupBy('service_categories.id', 'service_categories.name', 'service_categories.name_ar')
            ->get();

        $stats = [
            'total_favorites' => $totalFavorites,
            'available_services' => $availableCount,
            'unavailable_services' => $totalFavorites - $availableCount,
            'by_category' => $byCategory
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Statistics retrieved successfully'
        ], 200);
    }
}