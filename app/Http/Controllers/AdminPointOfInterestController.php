<?php

namespace App\Http\Controllers;

use App\Models\PointOfInterest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Admin POIs",
 *     description="Admin API Endpoints for Points of Interest"
 * )
 */
class AdminPointOfInterestController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/pois",
     *     summary="Get all points of interest (Admin)",
     *     tags={"Admin POIs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by name or address",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="type_id",
     *         in="query",
     *         description="Filter by POI type",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="is_verified",
     *         in="query",
     *         description="Filter by verification status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page. Leave empty to get all results.",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = PointOfInterest::with(['type', 'city', 'country', 'mainImage', 'seller.serviceProvider', 'tags']);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('name_ar', 'LIKE', "%{$search}%")
                    ->orWhere('address', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('type_id')) {
            $query->ofType($request->type_id);
        }

        if ($request->has('is_verified')) {
            if ($request->boolean('is_verified')) {
                $query->verified();
            } else {
                $query->where('is_verified', false);
            }
        }

        if ($request->has('is_active')) {
            if ($request->boolean('is_active')) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        $perPage = $request->input('per_page');

        if ($perPage === null || $perPage === '') {
            $pois = $query->get();
        } else {
            $pois = $query->paginate((int) $perPage);
        }

        return response()->json([
            'success' => true,
            'data' => $pois instanceof \Illuminate\Pagination\LengthAwarePaginator
                ? $pois->through(fn($poi) => $this->transformForAdmin($poi))
                : $pois->map(fn($poi) => $this->transformForAdmin($poi)),
        ]);
    }

    /**
     * Transform a POI for the admin panel.
     * Returns the raw stored custom_icon (no fallback to owner logo / type icon).
     */
    private function transformForAdmin($poi): array
    {
        /** @var PointOfInterest $poi */
        $data = $poi->toArray();

        // Override custom_icon with the raw DB value — no fallback logic.
        // This lets the admin frontend show exactly what is stored.
        $data['custom_icon'] = $poi->getRawOriginal('custom_icon');

        // Include tags if they are loaded
        if ($poi->relationLoaded('tags')) {
            $data['tags'] = $poi->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ];
            });
        }

        return $data;
    }


    /**
     * @OA\Post(
     *     path="/api/admin/pois",
     *     summary="Create a new point of interest (Admin)",
     *     tags={"Admin POIs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","type_id","latitude","longitude"},
     *             example={
     *                 "name": "Le Louvre",
     *                 "name_ar": "اللوفر",
     *                 "description": "The world's largest art museum.",
     *                 "type_id": 2,
     *                 "latitude": 48.8606,
     *                 "longitude": 2.3376,
     *                 "address": "Rue de Rivoli, 75001 Paris",
     *                 "phone": "+33140205050",
     *                 "email": "info@louvre.fr",
     *                 "website": "https://www.louvre.fr",
     *                 "is_verified": true,
     *                 "is_active": true,
     *                 "owner_id": 5,
     *                 "tags": {1, 3, 5}
     *             }
     *         )
     *     ),
     *     @OA\Response(response=201, description="POI created successfully"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->json()->all() ?: $request->all();

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'type_id' => 'required|exists:poi_types,id',
            'custom_icon' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string',
            'city_id' => 'nullable|exists:cities,id',
            'country_id' => 'nullable|exists:countries,id',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'opening_hours' => 'nullable|array',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:poi_tags,id',
            'services' => 'nullable|array',
            'services.*' => 'exists:poi_services,id',
            'google_place_id' => 'nullable|string|max:255',
            'google_rating' => 'nullable|numeric|between:0,5',
            'google_reviews_count' => 'nullable|integer|min:0',
            'owner_id' => 'nullable|exists:users,id',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
            'main_image' => 'nullable|string|max:2048',
            'images' => 'nullable|array',
            'images.*' => 'string|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();
        if (!isset($validatedData['owner_id'])) {
            $validatedData['owner_id'] = auth()->id();
        }

        $poi = PointOfInterest::create($validatedData);

        if ($request->has('tags')) {
            $poi->tags()->sync($request->tags);
        }

        if ($request->has('services')) {
            $poi->services()->sync($request->services);
        }

        // Handle images
        if ($request->has('main_image') || $request->has('images')) {
            $this->syncImages($poi, $request->input('main_image'), $request->input('images', []));
        }

        $poi->load(['type', 'city', 'country', 'tags', 'services', 'images', 'mainImage']);

        return response()->json([
            'success' => true,
            'message' => 'Point of interest created successfully',
            'data' => $poi,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/pois/{id}",
     *     summary="Get a specific point of interest (Admin)",
     *     tags={"Admin POIs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="POI not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $poi = PointOfInterest::with([
            'type',
            'seller.serviceProvider',
            'city',
            'country',
            'images',
            'mainImage',
            'services',
            'tags',
            'approvedReviews.user'
        ])->find($id);

        if (!$poi) {
            return response()->json([
                'success' => false,
                'message' => 'Point of interest not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $poi,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/pois/{id}",
     *     summary="Update a point of interest (Admin)",
     *     tags={"Admin POIs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             example={
     *                 "name": "Le Louvre (Updated)",
     *                 "is_verified": true,
     *                 "is_active": false
     *             }
     *         )
     *     ),
     *     @OA\Response(response=200, description="POI updated successfully"),
     *     @OA\Response(response=404, description="POI not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $poi = PointOfInterest::find($id);

        if (!$poi) {
            return response()->json([
                'success' => false,
                'message' => 'Point of interest not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'type_id' => 'sometimes|required|exists:poi_types,id',
            'custom_icon' => 'nullable|string|max:255',
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'address' => 'nullable|string',
            'city_id' => 'nullable|exists:cities,id',
            'country_id' => 'nullable|exists:countries,id',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'opening_hours' => 'nullable|array',
            'owner_id' => 'nullable|exists:users,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:poi_tags,id',
            'services' => 'nullable|array',
            'services.*' => 'exists:poi_services,id',
            'google_place_id' => 'nullable|string|max:255',
            'google_rating' => 'nullable|numeric|between:0,5',
            'google_reviews_count' => 'nullable|integer|min:0',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
            'main_image' => 'nullable|string|max:2048',
            'images' => 'nullable|array',
            'images.*' => 'string|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();

        $poi->update($validatedData);

        if ($request->has('tags')) {
            $poi->tags()->sync($request->tags);
        }

        if ($request->has('services')) {
            $poi->services()->sync($request->services);
        }

        // Handle images
        if ($request->has('main_image') || $request->has('images')) {
            $this->syncImages($poi, $request->input('main_image'), $request->input('images', []));
        }

        $poi->load(['type', 'city', 'country', 'tags', 'services', 'images', 'mainImage']);

        return response()->json([
            'success' => true,
            'message' => 'Point of interest updated successfully',
            'data' => $poi,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/pois/{id}",
     *     summary="Delete a point of interest (Admin)",
     *     tags={"Admin POIs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="POI deleted successfully"),
     *     @OA\Response(response=404, description="POI not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $poi = PointOfInterest::find($id);

        if (!$poi) {
            return response()->json([
                'success' => false,
                'message' => 'Point of interest not found',
            ], 404);
        }

        $poi->delete();

        return response()->json([
            'success' => true,
            'message' => 'Point of interest deleted successfully',
        ]);
    }

    /**
     * Sync images for a POI.
     */
    private function syncImages($poi, $mainImageUrl, array $otherImages)
    {
        // Delete existing images that are not in the new list
        $newUrls = array_filter(array_merge([$mainImageUrl], $otherImages));
        $poi->images()->whereNotIn('image_url', $newUrls)->delete();

        // Handle main image
        if ($mainImageUrl) {
            $poi->images()->updateOrCreate(
                ['image_url' => $mainImageUrl],
                ['is_main' => true, 'order_position' => 0]
            );
        }

        // Handle other images
        foreach ($otherImages as $index => $imageUrl) {
            if ($imageUrl === $mainImageUrl)
                continue;
            $poi->images()->updateOrCreate(
                ['image_url' => $imageUrl],
                ['is_main' => false, 'order_position' => $index + 1]
            );
        }

        // Ensure only one image is main (optional safety)
        if ($mainImageUrl) {
            $poi->images()->where('image_url', '!=', $mainImageUrl)->update(['is_main' => false]);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/pois/stats/overview",
     *     summary="Get POI statistics (Admin)",
     *     tags={"Admin POIs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_pois' => PointOfInterest::count(),
            'active_pois' => PointOfInterest::active()->count(),
            'verified_pois' => PointOfInterest::verified()->count(),
            'pois_this_month' => PointOfInterest::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
