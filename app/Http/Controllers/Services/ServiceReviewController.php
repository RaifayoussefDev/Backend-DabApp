<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ServiceReview;
use App\Models\ServiceBooking;
use App\Models\Service;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Service Reviews",
 *     description="API endpoints pour gérer les avis et évaluations des services"
 * )
 */
class ServiceReviewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/services/{service_id}/reviews",
     *     summary="Avis d'un service",
     *     description="Récupère tous les avis approuvés pour un service donné",
     *     operationId="getServiceReviews",
     *     tags={"Service Reviews"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="service_id",
     *         in="path",
     *         description="ID du service",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="rating",
     *         in="query",
     *         description="Filtrer par note (1-5)",
     *         required=false,
     *         @OA\Schema(type="integer", enum={1, 2, 3, 4, 5})
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Trier par (recent, rating_high, rating_low)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"recent", "rating_high", "rating_low"}, example="recent")
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
     *         description="Avis récupérés avec succès",
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
     *                         @OA\Property(property="rating", type="integer", example=5),
     *                         @OA\Property(property="comment", type="string", example="Excellent service!"),
     *                         @OA\Property(property="comment_ar", type="string", example="خدمة ممتازة!"),
     *                         @OA\Property(
     *                             property="user",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="full_name", type="string"),
     *                             @OA\Property(property="avatar", type="string")
     *                         ),
     *                         @OA\Property(property="provider_response", type="string"),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="statistics",
     *                 type="object",
     *                 @OA\Property(property="average_rating", type="number", format="float", example=4.5),
     *                 @OA\Property(property="total_reviews", type="integer", example=123),
     *                 @OA\Property(
     *                     property="rating_breakdown",
     *                     type="object",
     *                     @OA\Property(property="5_stars", type="integer", example=80),
     *                     @OA\Property(property="4_stars", type="integer", example=30),
     *                     @OA\Property(property="3_stars", type="integer", example=10),
     *                     @OA\Property(property="2_stars", type="integer", example=2),
     *                     @OA\Property(property="1_star", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Service non trouvé")
     * )
     */
    public function index(Request $request, $serviceId)
    {
        $service = Service::find($serviceId);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found'
            ], 404);
        }

        $query = ServiceReview::where('service_id', $serviceId)
            ->where('is_approved', true)
            ->with('user:id,full_name,avatar');

        // Filtre par note
        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        // Tri
        $sortBy = $request->get('sort_by', 'recent');
        switch ($sortBy) {
            case 'rating_high':
                $query->orderByDesc('rating')->orderByDesc('created_at');
                break;
            case 'rating_low':
                $query->orderBy('rating')->orderByDesc('created_at');
                break;
            default: // recent
                $query->latest();
                break;
        }

        $perPage = $request->get('per_page', 20);
        $reviews = $query->paginate($perPage);

        // Statistiques
        $statistics = [
            'average_rating' => round($service->reviews()->where('is_approved', true)->avg('rating'), 2),
            'total_reviews' => $service->reviews()->where('is_approved', true)->count(),
            'rating_breakdown' => [
                '5_stars' => $service->reviews()->where('is_approved', true)->where('rating', 5)->count(),
                '4_stars' => $service->reviews()->where('is_approved', true)->where('rating', 4)->count(),
                '3_stars' => $service->reviews()->where('is_approved', true)->where('rating', 3)->count(),
                '2_stars' => $service->reviews()->where('is_approved', true)->where('rating', 2)->count(),
                '1_star' => $service->reviews()->where('is_approved', true)->where('rating', 1)->count(),
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $reviews,
            'statistics' => $statistics,
            'message' => 'Reviews retrieved successfully'
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/bookings/{booking_id}/review",
     *     summary="Créer un avis",
     *     description="Permet à l'utilisateur de laisser un avis sur une réservation complétée",
     *     operationId="createReview",
     *     tags={"Service Reviews"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="booking_id",
     *         in="path",
     *         description="ID de la réservation",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rating"},
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=5),
     *             @OA\Property(property="comment", type="string", example="Excellent service! Very professional."),
     *             @OA\Property(property="comment_ar", type="string", example="خدمة ممتازة! محترف جداً.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Avis créé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string", example="Review submitted successfully")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Réservation non complétée ou avis déjà existant"),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=404, description="Réservation non trouvée")
     * )
     */
    public function store(Request $request, $bookingId)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $booking = ServiceBooking::with(['service', 'provider'])->find($bookingId);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        }

        // Vérifier que c'est bien l'utilisateur qui a fait la réservation
        if ($booking->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to review this booking'
            ], 403);
        }

        // Vérifier que la réservation est complétée
        if ($booking->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Can only review completed bookings'
            ], 400);
        }

        // Vérifier qu'il n'y a pas déjà un avis
        if ($booking->review) {
            return response()->json([
                'success' => false,
                'message' => 'Review already exists for this booking'
            ], 400);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'comment_ar' => 'nullable|string|max:1000'
        ]);

        DB::beginTransaction();
        try {
            $review = ServiceReview::create([
                'booking_id' => $booking->id,
                'service_id' => $booking->service_id,
                'provider_id' => $booking->provider_id,
                'user_id' => $user->id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'comment_ar' => $validated['comment_ar'] ?? null,
                'is_approved' => true // Auto-approve (ou mettre false pour modération)
            ]);

            // Mettre à jour la note moyenne du service
            $this->updateServiceRating($booking->service_id);

            // Mettre à jour la note moyenne du provider
            $this->updateProviderRating($booking->provider_id);

            DB::commit();

            // TODO: Notifier le provider du nouvel avis

            return response()->json([
                'success' => true,
                'data' => $review->load('user:id,full_name,avatar'),
                'message' => 'Review submitted successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/reviews/{id}",
     *     summary="Modifier un avis",
     *     description="Permet à l'utilisateur de modifier son propre avis",
     *     operationId="updateReview",
     *     tags={"Service Reviews"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'avis",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5),
     *             @OA\Property(property="comment", type="string"),
     *             @OA\Property(property="comment_ar", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Avis mis à jour"),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=404, description="Avis non trouvé")
     * )
     */
    public function update(Request $request, $id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $review = ServiceReview::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }

        // Vérifier que c'est bien l'auteur de l'avis
        if ($review->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this review'
            ], 403);
        }

        $validated = $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'comment_ar' => 'nullable|string|max:1000'
        ]);

        DB::beginTransaction();
        try {
            $review->update($validated);

            // Recalculer les notes si rating modifié
            if (isset($validated['rating'])) {
                $this->updateServiceRating($review->service_id);
                $this->updateProviderRating($review->provider_id);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $review->fresh('user:id,full_name,avatar'),
                'message' => 'Review updated successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/reviews/{id}",
     *     summary="Supprimer un avis",
     *     description="Permet à l'utilisateur de supprimer son propre avis",
     *     operationId="deleteReview",
     *     tags={"Service Reviews"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'avis",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Avis supprimé"),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=404, description="Avis non trouvé")
     * )
     */
    public function destroy($id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $review = ServiceReview::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }

        if ($review->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this review'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $serviceId = $review->service_id;
            $providerId = $review->provider_id;

            $review->delete();

            // Recalculer les notes
            $this->updateServiceRating($serviceId);
            $this->updateProviderRating($providerId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/provider/reviews/{id}/respond",
     *     summary="Répondre à un avis (Provider)",
     *     description="Permet au fournisseur de répondre à un avis client",
     *     operationId="respondToReview",
     *     tags={"Service Reviews"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'avis",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"response"},
     *             @OA\Property(property="response", type="string", example="Thank you for your feedback!")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Réponse ajoutée"),
     *     @OA\Response(response=403, description="Non autorisé")
     * )
     */
    public function respondToReview(Request $request, $id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user->serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a service provider'
            ], 403);
        }

        $review = ServiceReview::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }

        // Vérifier que c'est bien l'avis de son service
        if ($review->provider_id !== $user->serviceProvider->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to respond to this review'
            ], 403);
        }

        $validated = $request->validate([
            'response' => 'required|string|max:1000'
        ]);

        try {
            $review->update([
                'provider_response' => $validated['response'],
                'provider_response_at' => now()
            ]);

            // TODO: Notifier le client de la réponse

            return response()->json([
                'success' => true,
                'data' => $review->fresh(),
                'message' => 'Response added successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add response',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/my-reviews",
     *     summary="Mes avis (User)",
     *     description="Récupère tous les avis laissés par l'utilisateur connecté",
     *     operationId="getMyReviews",
     *     tags={"Service Reviews"},
     *     security={{"bearer":{}}},
     *     @OA\Response(response=200, description="Avis récupérés")
     * )
     */
    public function myReviews()
    {
        $user = JWTAuth::parseToken()->authenticate();

        $reviews = ServiceReview::where('user_id', $user->id)
            ->with([
                'service:id,name,name_ar,image',
                'provider:id,business_name,business_name_ar,logo',
                'booking:id,booking_date,status'
            ])
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $reviews,
            'message' => 'Reviews retrieved successfully'
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/provider/reviews",
     *     summary="Avis reçus (Provider)",
     *     description="Récupère tous les avis reçus par le fournisseur",
     *     operationId="getProviderReviews",
     *     tags={"Service Reviews"},
     *     security={{"bearer":{}}},
     *     @OA\Parameter(
     *         name="is_approved",
     *         in="query",
     *         description="Filtrer par statut d'approbation",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1})
     *     ),
     *     @OA\Response(response=200, description="Avis récupérés"),
     *     @OA\Response(response=403, description="Vous n'êtes pas fournisseur")
     * )
     */
    public function providerReviews(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user->serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a service provider'
            ], 403);
        }

        $query = ServiceReview::where('provider_id', $user->serviceProvider->id)
            ->with([
                'user:id,full_name,avatar',
                'service:id,name,name_ar',
                'booking:id,booking_date'
            ]);

        // Filtre par approbation
        if ($request->has('is_approved')) {
            $query->where('is_approved', $request->is_approved);
        }

        $reviews = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $reviews,
            'message' => 'Reviews retrieved successfully'
        ], 200);
    }

    /**
     * Mettre à jour la note moyenne d'un service
     */
    private function updateServiceRating($serviceId)
    {
        $service = Service::find($serviceId);
        if ($service) {
            $avgRating = $service->reviews()
                ->where('is_approved', true)
                ->avg('rating');
            
            // Note: Les services n'ont pas de colonne rating_average dans le schéma
            // Si vous voulez la stocker, ajoutez-la dans la migration
        }
    }

    /**
     * Mettre à jour la note moyenne d'un provider
     */
    private function updateProviderRating($providerId)
    {
        $provider = ServiceProvider::find($providerId);
        if ($provider) {
            $avgRating = $provider->reviews()
                ->where('is_approved', true)
                ->avg('rating');
            
            $reviewsCount = $provider->reviews()
                ->where('is_approved', true)
                ->count();

            $provider->update([
                'rating_average' => round($avgRating, 2),
                'reviews_count' => $reviewsCount
            ]);
        }
    }
}