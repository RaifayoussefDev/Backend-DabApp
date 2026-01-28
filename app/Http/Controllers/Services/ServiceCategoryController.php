<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Service Categories",
 *     description="API endpoints pour gérer les catégories de services (Transport, Remorquage, Instructeur, Lavage, Ateliers)"
 * )
 */
class ServiceCategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/service-categories",
     *     summary="Liste des catégories de services",
     *     description="Récupère toutes les catégories de services disponibles avec options de filtrage",
     *     operationId="getServiceCategories",
     *     tags={"Service Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filtrer par statut actif (1=actif, 0=inactif)",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1}, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="with_services_count",
     *         in="query",
     *         description="Inclure le nombre de services par catégorie",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste récupérée avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Bike Transport"),
     *                     @OA\Property(property="name_ar", type="string", example="نقل الدراجات"),
     *                     @OA\Property(property="slug", type="string", example="bike-transport"),
     *                     @OA\Property(property="description", type="string", example="Secure carrier service for moving your motorcycle safely"),
     *                     @OA\Property(property="description_ar", type="string", example="خدمة نقل آمنة لنقل دراجتك بأمان"),
     *                     @OA\Property(property="icon", type="string", example="truck"),
     *                     @OA\Property(property="color", type="string", example="#FF5722"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="order_position", type="integer", example=1),
     *                     @OA\Property(property="services_count", type="integer", example=45)
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Categories retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = ServiceCategory::query();

        // Filtrer par statut actif
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Inclure le nombre de services
        if ($request->boolean('with_services_count')) {
            $query->withCount('services');
        }

        // Ordre par position
        $categories = $query->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
            'message' => 'Categories retrieved successfully'
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/service-categories/{id}",
     *     summary="Détails d'une catégorie",
     *     description="Récupère les détails d'une catégorie spécifique avec ses services optionnels",
     *     operationId="getServiceCategory",
     *     tags={"Service Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la catégorie",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="with_services",
     *         in="query",
     *         description="Inclure les services de la catégorie",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Catégorie trouvée",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Bike Transport"),
     *                 @OA\Property(property="name_ar", type="string", example="نقل الدراجات"),
     *                 @OA\Property(property="slug", type="string", example="bike-transport"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="icon", type="string", example="truck"),
     *                 @OA\Property(property="color", type="string", example="#FF5722")
     *             ),
     *             @OA\Property(property="message", type="string", example="Category found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Catégorie non trouvée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Category not found")
     *         )
     *     )
     * )
     */
    public function show(Request $request, $id)
    {
        $query = ServiceCategory::where('id', $id);

        // Inclure les services si demandé
        if ($request->boolean('with_services')) {
            $query->with(['services' => function($q) {
                $q->where('is_available', true)
                  ->with('provider:id,business_name,business_name_ar,logo,rating_average');
            }]);
        }

        $category = $query->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category,
            'message' => 'Category found'
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/service-categories/slug/{slug}",
     *     summary="Catégorie par slug",
     *     description="Récupère une catégorie via son slug (ex: bike-transport, tow-service)",
     *     operationId="getCategoryBySlug",
     *     tags={"Service Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Slug de la catégorie",
     *         required=true,
     *         @OA\Schema(type="string", example="bike-transport")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Catégorie trouvée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Catégorie non trouvée"
     *     )
     * )
     */
    public function showBySlug($slug)
    {
        $category = ServiceCategory::where('slug', $slug)
            ->with(['services' => function($q) {
                $q->where('is_available', true)
                  ->with('provider:id,business_name,business_name_ar,logo,rating_average,city_id')
                  ->withCount('reviews')
                  ->withAvg('reviews', 'rating');
            }])
            ->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category,
            'message' => 'Category found'
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/service-categories/stats",
     *     summary="Statistiques des catégories",
     *     description="Récupère les statistiques globales (nombre de services, providers, bookings par catégorie)",
     *     operationId="getCategoryStats",
     *     tags={"Service Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistiques récupérées",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_categories", type="integer", example=5),
     *                 @OA\Property(property="active_categories", type="integer", example=5),
     *                 @OA\Property(property="total_services", type="integer", example=234),
     *                 @OA\Property(property="total_providers", type="integer", example=67)
     *             )
     *         )
     *     )
     * )
     */
    public function stats()
    {
        $stats = [
            'total_categories' => ServiceCategory::count(),
            'active_categories' => ServiceCategory::where('is_active', true)->count(),
            'total_services' => \App\Models\Service::count(),
            'total_providers' => \App\Models\ServiceProvider::count(),
            'categories_breakdown' => ServiceCategory::active()
                ->withCount(['services'])
                ->ordered()
                ->get()
                ->map(function($cat) {
                    return [
                        'id' => $cat->id,
                        'category_name' => $cat->name,
                        'category_name_ar' => $cat->name_ar,
                        'services_count' => $cat->services_count,
                        'icon' => $cat->icon,
                        'color' => $cat->color
                    ];
                })
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Statistics retrieved successfully'
        ], 200);
    }
}