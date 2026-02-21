<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PoiFavorite;
use App\Models\PointOfInterest;
use App\Models\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Points of Interest",
 *     description="API Endpoints for Points of Interest"
 * )
 */
class PointOfInterestController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pois",
     *     summary="Get all points of interest",
     *     tags={"Points of Interest"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="type_id",
     *         in="query",
     *         description="Filter by POI type",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="latitude",
     *         in="query",
     *         description="Latitude for nearby search",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="longitude",
     *         in="query",
     *         description="Longitude for nearby search",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="radius",
     *         in="query",
     *         description="Search radius in km (default 10)",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="is_verified",
     *         in="query",
     *         description="Filter by verification status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Ne charger que mainImage au lieu de images et mainImage
        $query = PointOfInterest::with(['type', 'city', 'country', 'mainImage']);

        // Filter by type
        if ($request->has('type_id')) {
            $query->ofType($request->type_id);
        }

        // Filter by verification status
        if ($request->has('is_verified')) {
            if ($request->boolean('is_verified')) {
                $query->verified();
            }
        }

        // Filter by active status
        $query->active();

        // Nearby search
        if ($request->has('latitude') && $request->has('longitude')) {
            $radius = $request->input('radius', 10);
            $query->nearby($request->latitude, $request->longitude, $radius);
        }

        $pois = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $pois,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/pois",
     *     summary="Create a new point of interest",
     *     tags={"Points of Interest"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","type_id","latitude","longitude"},
     *             @OA\Property(property="name", type="string", example="Eiffel Tower"),
     *             @OA\Property(property="description", type="string", example="A wrought-iron lattice tower on the Champ de Mars."),
     *             @OA\Property(property="type_id", type="integer", example=1),
     *             @OA\Property(property="latitude", type="number", format="float", example=48.8584),
     *             @OA\Property(property="longitude", type="number", format="float", example=2.2945),
     *             @OA\Property(property="address", type="string", example="Champ de Mars, 5 Av. Anatole France, 75007 Paris"),
     *             @OA\Property(property="city_id", type="integer", example=1),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="phone", type="string", example="+33 892 70 12 39"),
     *             @OA\Property(property="email", type="string", example="info@toureiffel.paris"),
     *             @OA\Property(property="website", type="string", example="https://www.toureiffel.paris"),
     *             @OA\Property(
     *                 property="opening_hours", 
     *                 type="object",
     *                 example={
     *                     "monday": "09:00-23:45",
     *                     "tuesday": "09:00-23:45",
     *                     "wednesday": "09:00-23:45",
     *                     "thursday": "09:00-23:45",
     *                     "friday": "09:00-23:45",
     *                     "saturday": "09:00-23:45",
     *                     "sunday": "09:00-23:45"
     *                 }
     *             ),
     *             @OA\Property(property="owner_id", type="integer", description="Assign POI to a specific user (Admin only)", example=123)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201, 
     *         description="POI created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Point of interest created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        // Try to get data from JSON first, then fall back to request data
        $data = $request->json()->all() ?: $request->all();

        \Log::info('Received data:', $data);

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type_id' => 'required|exists:poi_types,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string',
            'city_id' => 'nullable|exists:cities,id',
            'country_id' => 'nullable|exists:countries,id',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'opening_hours' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();
        $validatedData['owner_id'] = auth()->id();

        $poi = PointOfInterest::create($validatedData);
        $poi->load(['type', 'city', 'country']);

        return response()->json([
            'success' => true,
            'message' => 'Point of interest created successfully',
            'data' => $poi,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pois/{id}",
     *     summary="Get a specific point of interest",
     *     tags={"Points of Interest"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="POI not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $poi = PointOfInterest::with([
            'type',
            'seller',
            'city',
            'country',
            'images',
            'approvedReviews.user',
            'services',
            'brands',
        ])->find($id);


        if (!$poi) {
            return response()->json([
                'success' => false,
                'message' => 'Point of interest not found',
            ], 404);
        }

        $user = Auth::user();
        $this->recordView($poi, $user);

        $isFavorited = false;
        if ($user) {
            $isFavorited = DB::table('poi_favorites')
                ->where('poi_id', $poi->id)
                ->where('user_id', $user->id)
                ->exists();
        }

        $poi->is_favorited = $isFavorited;

        return response()->json([
            'success' => true,
            'data' => $poi,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/pois/{id}",
     *     summary="Update a point of interest",
     *     tags={"Points of Interest"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Eiffel Tower (Updated)"),
     *             @OA\Property(property="description", type="string", example="Updated description for the tower."),
     *             @OA\Property(property="type_id", type="integer", example=1),
     *             @OA\Property(property="latitude", type="number", format="float", example=48.8584),
     *             @OA\Property(property="longitude", type="number", format="float", example=2.2945),
     *             @OA\Property(property="address", type="string", example="Updated Address, Paris"),
     *             @OA\Property(property="city_id", type="integer", example=1),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="phone", type="string", example="+33 1 23 45 67 89"),
     *             @OA\Property(property="email", type="string", example="updated@toureiffel.paris"),
     *             @OA\Property(property="website", type="string", example="https://www.toureiffel.paris"),
     *             @OA\Property(
     *                 property="opening_hours", 
     *                 type="object",
     *                 example={
     *                     "monday": "closed",
     *                     "tuesday": "10:00-18:00"
     *                 }
     *             ),
     *             @OA\Property(property="owner_id", type="integer", description="Transfer POI ownership (Admin only)", example=123)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200, 
     *         description="POI updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Point of interest updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="POI not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
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

        // Check if user is owner or admin
        if ($poi->owner_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this POI',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type_id' => 'sometimes|required|exists:poi_types,id',
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
        ]);


        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();

        // Only admins can change the owner
        if (isset($validatedData['owner_id']) && $validatedData['owner_id'] != $poi->owner_id) {
            if (!auth()->user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can transfer POI ownership',
                ], 403);
            }
        }

        $poi->update($validatedData);

        $poi->load(['type', 'city', 'country']);

        return response()->json([
            'success' => true,
            'message' => 'Point of interest updated successfully',
            'data' => $poi,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/pois/{id}",
     *     summary="Delete a point of interest",
     *     tags={"Points of Interest"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="POI deleted successfully"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="POI not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
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

        // Check if user is owner or admin
        if ($poi->owner_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this POI',
            ], 403);
        }

        $poi->delete();

        return response()->json([
            'success' => true,
            'message' => 'Point of interest deleted successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/pois/{id}/favorite",
     *     summary="Toggle favorite status for a POI",
     *     tags={"Points of Interest"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Favorite toggled successfully"),
     *     @OA\Response(response=404, description="POI not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function toggleFavorite(int $id): JsonResponse
    {
        $poi = PointOfInterest::find($id);

        if (!$poi) {
            return response()->json([
                'success' => false,
                'message' => 'Point of interest not found',
            ], 404);
        }

        $user = auth()->user();
        $favorite = PoiFavorite::where('poi_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if ($favorite) {
            $favorite->delete();
            $message = 'POI removed from favorites';
            $isFavorited = false;
        } else {
            PoiFavorite::create([
                'poi_id' => $id,
                'user_id' => $user->id,
            ]);
            $message = 'POI added to favorites';
            $isFavorited = true;
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'is_favorited' => $isFavorited,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/pois/nearby",
     *     summary="Get nearby points of interest",
     *     tags={"Points of Interest"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="latitude",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="longitude",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="radius",
     *         in="query",
     *         description="Search radius in km",
     *         required=false,
     *         @OA\Schema(type="number", format="float", default=10)
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function nearby(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $radius = $request->input('radius', 10);

        $pois = PointOfInterest::with(['type', 'city', 'country', 'mainImage'])
            ->active()
            ->nearby($request->latitude, $request->longitude, $radius)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pois,
        ]);
    }
    /**
     * @OA\Get(
     *     path="/api/pois/favorites",
     *     summary="Get user's favorite points of interest",
     *     tags={"Points of Interest"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="type_id",
     *         in="query",
     *         description="Filter by POI type",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function favorites(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = PointOfInterest::with(['type', 'city', 'country', 'images', 'mainImage'])
            ->whereHas('favorites', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->active();

        // Filter by type if provided
        if ($request->has('type_id')) {
            $query->ofType($request->type_id);
        }

        $perPage = $request->input('per_page', 20);
        $pois = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $pois,
        ]);
    }

    private function recordView($viewable, $user)
    {
        if (!$user) {
            $viewable->increment('views_count');
            return;
        }

        $exists = View::where('user_id', $user->id)
            ->where('viewable_id', $viewable->id)
            ->where('viewable_type', get_class($viewable))
            ->exists();

        if (!$exists) {
            try {
                View::create([
                    'user_id' => $user->id,
                    'viewable_id' => $viewable->id,
                    'viewable_type' => get_class($viewable),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
                $viewable->increment('views_count');
            } catch (\Exception $e) {
                // Ignore unique constraint violation
            }
        }
    }
}
