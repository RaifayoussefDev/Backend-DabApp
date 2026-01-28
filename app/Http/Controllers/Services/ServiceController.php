<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Services",
 *     description="API endpoints pour gérer les services offerts par les fournisseurs"
 * )
 */
class ServiceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/services",
     *     summary="Liste des services",
     *     description="Récupère tous les services disponibles avec filtres avancés",
     *     operationId="getServices",
     *     tags={"Services"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filtrer par catégorie",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="provider_id",
     *         in="query",
     *         description="Filtrer par fournisseur",
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
     *         name="price_type",
     *         in="query",
     *         description="Type de tarification",
     *         required=false,
     *         @OA\Schema(type="string", enum={"fixed", "per_hour", "per_km", "custom"})
     *     ),
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="Prix minimum",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=50)
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="Prix maximum",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=500)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Rechercher par nom",
     *         required=false,
     *         @OA\Schema(type="string", example="Transport")
     *     ),
     *     @OA\Parameter(
     *         name="is_available",
     *         in="query",
     *         description="Disponibilité (1=disponible)",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1})
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Trier par (price, rating, created_at)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"price", "rating", "created_at", "name"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Ordre de tri",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="asc")
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
     *         description="Liste récupérée avec succès",
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
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Motorcycle Transport to Jeddah"),
     *                         @OA\Property(property="name_ar", type="string", example="نقل الدراجة إلى جدة"),
     *                         @OA\Property(property="price", type="number", format="float", example=250.00),
     *                         @OA\Property(property="price_type", type="string", example="fixed"),
     *                         @OA\Property(property="currency", type="string", example="SAR"),
     *                         @OA\Property(property="is_available", type="boolean", example=true),
     *                         @OA\Property(
     *                             property="provider",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="business_name", type="string"),
     *                             @OA\Property(property="rating_average", type="number")
     *                         ),
     *                         @OA\Property(
     *                             property="category",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="name", type="string")
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Service::with(['provider.city', 'category']);

        // Filtre: Catégorie
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filtre: Fournisseur
        if ($request->has('provider_id')) {
            $query->where('provider_id', $request->provider_id);
        }

        // Filtre: Ville (via provider)
        if ($request->has('city_id')) {
            $query->whereHas('provider', function($q) use ($request) {
                $q->where('city_id', $request->city_id);
            });
        }

        // Filtre: Type de prix
        if ($request->has('price_type')) {
            $query->where('price_type', $request->price_type);
        }

        // Filtre: Prix minimum
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        // Filtre: Prix maximum
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('name_ar', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Filtre: Disponibilité
        if ($request->has('is_available')) {
            $query->where('is_available', $request->is_available);
        }

        // Tri
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if ($sortBy === 'rating') {
            $query->leftJoin('service_reviews', 'services.id', '=', 'service_reviews.service_id')
                  ->select('services.*', DB::raw('AVG(service_reviews.rating) as avg_rating'))
                  ->groupBy('services.id')
                  ->orderBy('avg_rating', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = $request->get('per_page', 20);
        $services = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $services,
            'message' => 'Services retrieved successfully'
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/services/{id}",
     *     summary="Détails d'un service",
     *     description="Récupère les détails complets d'un service avec avis, horaires et documents requis",
     *     operationId="getService",
     *     tags={"Services"},
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
     *         description="Service trouvé",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="price", type="number"),
     *                 @OA\Property(property="is_favorited", type="boolean", example=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Service non trouvé")
     * )
     */
    public function show($id)
    {
        $service = Service::with([
            'provider.city',
            'provider.workingHours',
            'category',
            'schedules',
            'requiredDocuments',
            'reviews' => function($q) {
                $q->where('is_approved', true)
                  ->latest()
                  ->limit(10)
                  ->with('user:id,full_name,avatar');
            }
        ])
        ->withCount('reviews')
        ->withAvg('reviews', 'rating')
        ->find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found'
            ], 404);
        }

        // Vérifier si l'utilisateur a ajouté aux favoris
        $user = JWTAuth::parseToken()->authenticate();
        $isFavorited = $service->isFavoritedBy($user);

        $serviceData = $service->toArray();
        $serviceData['is_favorited'] = $isFavorited;

        return response()->json([
            'success' => true,
            'data' => $serviceData,
            'message' => 'Service found'
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/my-services",
     *     summary="Créer un nouveau service (Provider)",
     *     description="Permet au fournisseur d'ajouter un nouveau service à son catalogue",
     *     operationId="createService",
     *     tags={"Services"},
     *     security={{"bearer":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"category_id", "name", "price", "price_type"},
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Transport Riyadh to Jeddah"),
     *             @OA\Property(property="name_ar", type="string", example="نقل من الرياض إلى جدة"),
     *             @OA\Property(property="description", type="string", example="Safe motorcycle transport service"),
     *             @OA\Property(property="description_ar", type="string"),
     *             @OA\Property(property="price", type="number", format="float", example=250.00),
     *             @OA\Property(property="price_type", type="string", enum={"fixed", "per_hour", "per_km", "custom"}, example="fixed"),
     *             @OA\Property(property="currency", type="string", example="SAR"),
     *             @OA\Property(property="duration_minutes", type="integer", example=60),
     *             @OA\Property(property="is_available", type="boolean", example=true),
     *             @OA\Property(property="requires_booking", type="boolean", example=true),
     *             @OA\Property(property="max_capacity", type="integer", example=5),
     *             @OA\Property(property="image", type="string", format="binary")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Service créé avec succès"
     *     ),
     *     @OA\Response(response=403, description="Vous n'êtes pas fournisseur")
     * )
     */
    public function store(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user->serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a service provider'
            ], 403);
        }

        $validated = $request->validate([
            'category_id' => 'required|exists:service_categories,id',
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'description_ar' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0',
            'price_type' => 'required|in:fixed,per_hour,per_km,custom',
            'currency' => 'nullable|string|max:3',
            'duration_minutes' => 'nullable|integer|min:0',
            'is_available' => 'nullable|boolean',
            'requires_booking' => 'nullable|boolean',
            'max_capacity' => 'nullable|integer|min:1',
            'image' => 'nullable|image|max:2048'
        ]);

        DB::beginTransaction();
        try {
            // Upload image si fourni
            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('services', 'public');
            }

            $service = Service::create([
                ...$validated,
                'provider_id' => $user->serviceProvider->id,
                'currency' => $validated['currency'] ?? 'SAR',
                'is_available' => $validated['is_available'] ?? true,
                'requires_booking' => $validated['requires_booking'] ?? true
            ]);

            // Incrémenter le compteur de services du provider
            $user->serviceProvider->increment('services_count');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $service->load(['category', 'provider']),
                'message' => 'Service created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/my-services/{id}",
     *     summary="Mettre à jour un service (Provider)",
     *     description="Permet au fournisseur de modifier un de ses services",
     *     operationId="updateService",
     *     tags={"Services"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du service",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="price", type="number"),
     *             @OA\Property(property="is_available", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Service mis à jour"),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=404, description="Service non trouvé")
     * )
     */
    public function update(Request $request, $id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user->serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a service provider'
            ], 403);
        }

        $service = Service::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found'
            ], 404);
        }

        // Vérifier que c'est bien le service du provider
        if ($service->provider_id !== $user->serviceProvider->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this service'
            ], 403);
        }

        $validated = $request->validate([
            'category_id' => 'nullable|exists:service_categories,id',
            'name' => 'nullable|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'description_ar' => 'nullable|string|max:2000',
            'price' => 'nullable|numeric|min:0',
            'price_type' => 'nullable|in:fixed,per_hour,per_km,custom',
            'duration_minutes' => 'nullable|integer|min:0',
            'is_available' => 'nullable|boolean',
            'requires_booking' => 'nullable|boolean',
            'max_capacity' => 'nullable|integer|min:1',
            'image' => 'nullable|image|max:2048'
        ]);

        try {
            // Upload nouvelle image si fournie
            if ($request->hasFile('image')) {
                // Supprimer ancienne image
                if ($service->image) {
                    Storage::disk('public')->delete($service->image);
                }
                $validated['image'] = $request->file('image')->store('services', 'public');
            }

            $service->update($validated);

            return response()->json([
                'success' => true,
                'data' => $service->fresh(['category', 'provider']),
                'message' => 'Service updated successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/my-services/{id}",
     *     summary="Supprimer un service (Provider)",
     *     description="Permet au fournisseur de supprimer un de ses services",
     *     operationId="deleteService",
     *     tags={"Services"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du service",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Service supprimé"),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=404, description="Service non trouvé")
     * )
     */
    public function destroy($id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user->serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a service provider'
            ], 403);
        }

        $service = Service::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found'
            ], 404);
        }

        if ($service->provider_id !== $user->serviceProvider->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this service'
            ], 403);
        }

        // Vérifier s'il y a des réservations en cours
        $hasActiveBookings = $service->bookings()
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->exists();

        if ($hasActiveBookings) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete service with active bookings'
            ], 400);
        }

        try {
            // Supprimer l'image
            if ($service->image) {
                Storage::disk('public')->delete($service->image);
            }

            $service->delete();

            // Décrémenter le compteur
            $user->serviceProvider->decrement('services_count');

            return response()->json([
                'success' => true,
                'message' => 'Service deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/my-services",
     *     summary="Mes services (Provider)",
     *     description="Liste tous les services du fournisseur connecté",
     *     operationId="getMyServices",
     *     tags={"Services"},
     *     security={{"bearer":{}}},
     *     @OA\Response(response=200, description="Liste récupérée"),
     *     @OA\Response(response=403, description="Vous n'êtes pas fournisseur")
     * )
     */
    public function myServices()
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user->serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a service provider'
            ], 403);
        }

        $services = Service::where('provider_id', $user->serviceProvider->id)
            ->with(['category'])
            ->withCount(['bookings', 'reviews'])
            ->withAvg('reviews', 'rating')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $services,
            'count' => $services->count(),
            'message' => 'Services retrieved successfully'
        ], 200);
    }
}