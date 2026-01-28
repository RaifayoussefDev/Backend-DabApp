<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Service Providers",
 *     description="API endpoints pour gérer les fournisseurs de services (ateliers, transporteurs, instructeurs)"
 * )
 */
class ServiceProviderController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/service-providers",
     *     summary="Liste des fournisseurs de services",
     *     description="Récupère tous les fournisseurs de services avec filtres et pagination",
     *     operationId="getServiceProviders",
     *     tags={"Service Providers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="is_verified",
     *         in="query",
     *         description="Filtrer par statut vérifié (1=vérifié, 0=non vérifié)",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1})
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filtrer par statut actif",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1})
     *     ),
     *     @OA\Parameter(
     *         name="city_id",
     *         in="query",
     *         description="Filtrer par ville",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filtrer par catégorie de service",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Rechercher par nom d'entreprise",
     *         required=false,
     *         @OA\Schema(type="string", example="Moto Service")
     *     ),
     *     @OA\Parameter(
     *         name="min_rating",
     *         in="query",
     *         description="Note minimale (1-5)",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=4.0)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre de résultats par page",
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
     *                         @OA\Property(property="business_name", type="string", example="Riyadh Moto Service"),
     *                         @OA\Property(property="business_name_ar", type="string", example="خدمة الدراجات الرياض"),
     *                         @OA\Property(property="logo", type="string"),
     *                         @OA\Property(property="rating_average", type="number", format="float", example=4.5),
     *                         @OA\Property(property="reviews_count", type="integer", example=123),
     *                         @OA\Property(property="is_verified", type="boolean", example=true),
     *                         @OA\Property(property="city", type="object")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = ServiceProvider::with(['city', 'country']);

        // Filtre: Vérifié
        if ($request->has('is_verified')) {
            $query->where('is_verified', $request->is_verified);
        }

        // Filtre: Actif
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Filtre: Ville
        if ($request->has('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        // Filtre: Catégorie de service
        if ($request->has('category_id')) {
            $query->whereHas('services', function($q) use ($request) {
                $q->where('category_id', $request->category_id);
            });
        }

        // Recherche par nom
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('business_name', 'LIKE', "%{$search}%")
                  ->orWhere('business_name_ar', 'LIKE', "%{$search}%");
            });
        }

        // Filtre: Note minimale
        if ($request->has('min_rating')) {
            $query->where('rating_average', '>=', $request->min_rating);
        }

        // Tri par note par défaut
        $query->orderByDesc('rating_average');

        $perPage = $request->get('per_page', 20);
        $providers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $providers,
            'message' => 'Providers retrieved successfully'
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/service-providers/{id}",
     *     summary="Détails d'un fournisseur",
     *     description="Récupère les détails complets d'un fournisseur avec ses services, avis et horaires",
     *     operationId="getServiceProvider",
     *     tags={"Service Providers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du fournisseur",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Fournisseur trouvé",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Fournisseur non trouvé")
     * )
     */
    public function show($id)
    {
        $provider = ServiceProvider::with([
            'user:id,full_name,email,phone',
            'city',
            'country',
            'services' => function($q) {
                $q->where('is_available', true);
            },
            'workingHours',
            'images',
            'reviews' => function($q) {
                $q->where('is_approved', true)
                  ->latest()
                  ->limit(10)
                  ->with('user:id,full_name,avatar');
            }
        ])->find($id);

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $provider,
            'message' => 'Provider found'
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/service-providers/nearby",
     *     summary="Fournisseurs à proximité",
     *     description="Recherche les fournisseurs dans un rayon donné autour d'une position GPS",
     *     operationId="getNearbyProviders",
     *     tags={"Service Providers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="latitude",
     *         in="query",
     *         description="Latitude de la position",
     *         required=true,
     *         @OA\Schema(type="number", format="float", example=24.7136)
     *     ),
     *     @OA\Parameter(
     *         name="longitude",
     *         in="query",
     *         description="Longitude de la position",
     *         required=true,
     *         @OA\Schema(type="number", format="float", example=46.6753)
     *     ),
     *     @OA\Parameter(
     *         name="radius",
     *         in="query",
     *         description="Rayon de recherche en kilomètres",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Fournisseurs à proximité trouvés"
     *     ),
     *     @OA\Response(response=400, description="Paramètres invalides")
     * )
     */
    public function nearby(Request $request)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|integer|min:1|max:100'
        ]);

        $radius = $validated['radius'] ?? 10;

        $providers = ServiceProvider::nearby(
            $validated['latitude'],
            $validated['longitude'],
            $radius
        )
        ->active()
        ->verified()
        ->with(['city', 'services'])
        ->get();

        return response()->json([
            'success' => true,
            'data' => $providers,
            'count' => $providers->count(),
            'search_radius_km' => $radius,
            'message' => 'Nearby providers found'
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/become-provider",
     *     summary="Devenir fournisseur de services",
     *     description="Permet à un utilisateur de s'inscrire comme fournisseur de services",
     *     operationId="becomeProvider",
     *     tags={"Service Providers"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"business_name", "phone", "city_id", "address"},
     *             @OA\Property(property="business_name", type="string", example="Riyadh Moto Service"),
     *             @OA\Property(property="business_name_ar", type="string", example="خدمة الدراجات الرياض"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="description_ar", type="string"),
     *             @OA\Property(property="phone", type="string", example="+966501234567"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="address_ar", type="string"),
     *             @OA\Property(property="city_id", type="integer", example=1),
     *             @OA\Property(property="latitude", type="number", format="float"),
     *             @OA\Property(property="longitude", type="number", format="float"),
     *             @OA\Property(property="logo", type="string", format="binary"),
     *             @OA\Property(property="cover_image", type="string", format="binary")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Compte fournisseur créé avec succès"
     *     ),
     *     @OA\Response(response=400, description="L'utilisateur est déjà fournisseur")
     * )
     */
    public function becomeProvider(Request $request)
    {
        $user = auth()->user();

        // Vérifier si déjà fournisseur
        if ($user->serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'You are already a service provider'
            ], 400);
        }

        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'business_name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'description_ar' => 'nullable|string|max:1000',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'required|string|max:500',
            'address_ar' => 'nullable|string|max:500',
            'city_id' => 'required|exists:cities,id',
            'country_id' => 'nullable|exists:countries,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'logo' => 'nullable|image|max:2048',
            'cover_image' => 'nullable|image|max:2048'
        ]);

        DB::beginTransaction();
        try {
            // Upload logo si fourni
            if ($request->hasFile('logo')) {
                $validated['logo'] = $request->file('logo')->store('providers/logos', 'public');
            }

            // Upload cover image si fourni
            if ($request->hasFile('cover_image')) {
                $validated['cover_image'] = $request->file('cover_image')->store('providers/covers', 'public');
            }

            $provider = ServiceProvider::create([
                ...$validated,
                'user_id' => $user->id,
                'is_verified' => false,
                'is_active' => true,
                'rating_average' => 0,
                'reviews_count' => 0,
                'services_count' => 0,
                'completed_orders' => 0
            ]);

            DB::commit();

            // TODO: Envoyer notification aux admins pour vérification

            return response()->json([
                'success' => true,
                'data' => $provider->load(['city', 'country']),
                'message' => 'Provider account created successfully. Pending admin verification.'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create provider account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/my-provider-profile",
     *     summary="Mettre à jour profil fournisseur",
     *     description="Permet au fournisseur de mettre à jour son profil",
     *     operationId="updateProviderProfile",
     *     tags={"Service Providers"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="business_name", type="string"),
     *             @OA\Property(property="business_name_ar", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="address", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Profil mis à jour"),
     *     @OA\Response(response=403, description="Vous n'êtes pas fournisseur")
     * )
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        if (!$user->serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a service provider'
            ], 403);
        }

        $validated = $request->validate([
            'business_name' => 'nullable|string|max:255',
            'business_name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'description_ar' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'address_ar' => 'nullable|string|max:500',
            'city_id' => 'nullable|exists:cities,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'logo' => 'nullable|image|max:2048',
            'cover_image' => 'nullable|image|max:2048'
        ]);

        try {
            $provider = $user->serviceProvider;

            // Upload nouveau logo
            if ($request->hasFile('logo')) {
                // Supprimer ancien logo
                if ($provider->logo) {
                    Storage::disk('public')->delete($provider->logo);
                }
                $validated['logo'] = $request->file('logo')->store('providers/logos', 'public');
            }

            // Upload nouvelle cover image
            if ($request->hasFile('cover_image')) {
                if ($provider->cover_image) {
                    Storage::disk('public')->delete($provider->cover_image);
                }
                $validated['cover_image'] = $request->file('cover_image')->store('providers/covers', 'public');
            }

            $provider->update($validated);

            return response()->json([
                'success' => true,
                'data' => $provider->fresh(['city', 'country']),
                'message' => 'Profile updated successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/my-provider-profile",
     *     summary="Mon profil fournisseur",
     *     description="Récupère le profil fournisseur de l'utilisateur connecté",
     *     operationId="getMyProviderProfile",
     *     tags={"Service Providers"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Profil récupéré"),
     *     @OA\Response(response=404, description="Vous n'êtes pas fournisseur")
     * )
     */
    public function myProfile()
    {
        $user = auth()->user();

        if (!$user->serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a service provider'
            ], 404);
        }

        $provider = $user->serviceProvider->load([
            'city',
            'country',
            'services',
            'workingHours',
            'images'
        ]);

        return response()->json([
            'success' => true,
            'data' => $provider,
            'message' => 'Profile retrieved successfully'
        ], 200);
    }
}