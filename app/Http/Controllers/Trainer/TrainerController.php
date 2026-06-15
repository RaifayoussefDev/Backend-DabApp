<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Models\TrainerLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Trainers",
 *     description="Browse trainers, manage profiles, training locations"
 * )
 */
class TrainerController extends Controller
{
    // ---------------------------------------------------------------
    // PUBLIC — List
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/trainers",
     *     summary="List trainers",
     *     description="Returns a paginated list of approved trainers. Filter by specialty, city, rating, experience, or free-text search.",
     *     operationId="listTrainers",
     *     tags={"Trainers"},
     *     @OA\Parameter(name="specialty",            in="query", required=false, @OA\Schema(type="string", enum={"coaching","competition","off-road","street","custom"})),
     *     @OA\Parameter(name="city_id",              in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="min_experience_years", in="query", required=false, @OA\Schema(type="integer", example=3)),
     *     @OA\Parameter(name="min_rating",           in="query", required=false, @OA\Schema(type="number", format="float", example=4.0)),
     *     @OA\Parameter(name="is_available",         in="query", required=false, @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Parameter(name="search",               in="query", required=false, @OA\Schema(type="string", example="Khalid")),
     *     @OA\Parameter(name="sort_by",              in="query", required=false, @OA\Schema(type="string", enum={"rating","experience","sessions","price"}, default="rating")),
     *     @OA\Parameter(name="per_page",             in="query", required=false, @OA\Schema(type="integer", example=15)),
     *     @OA\Response(
     *         response=200,
     *         description="Trainers retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="total",        type="integer", example=42),
     *                 @OA\Property(property="per_page",     type="integer", example=15),
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",               type="integer", example=1),
     *                         @OA\Property(property="name",             type="string",  example="Khalid Al-Mansouri"),
     *                         @OA\Property(property="name_ar",          type="string",  example="خالد المنصوري"),
     *                         @OA\Property(property="specialty",        type="string",  example="coaching"),
     *                         @OA\Property(property="experience_years", type="integer", example=8),
     *                         @OA\Property(property="price_per_hour",   type="number",  format="float", example=150.00),
     *                         @OA\Property(property="rating_average",   type="number",  format="float", example=4.8),
     *                         @OA\Property(property="total_sessions",   type="integer", example=200),
     *                         @OA\Property(property="likes_count",      type="integer", example=35),
     *                         @OA\Property(property="photo_url",        type="string",  example="https://example.com/storage/trainers/photo.jpg"),
     *                         @OA\Property(property="is_available",     type="boolean", example=true),
     *                         @OA\Property(property="locations", type="array",
     *                             @OA\Items(type="object",
     *                                 @OA\Property(property="id",            type="integer"),
     *                                 @OA\Property(property="location_name", type="string"),
     *                                 @OA\Property(property="city",          type="object")
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Trainer::with(['locations.city'])
            ->approved()
            ->available();

        if ($request->filled('specialty')) {
            $query->bySpecialty($request->specialty);
        }

        if ($request->filled('city_id')) {
            $query->whereHas('locations', fn ($q) => $q->where('city_id', $request->city_id)->where('is_available', true));
        }

        if ($request->filled('min_experience_years')) {
            $query->where('experience_years', '>=', $request->min_experience_years);
        }

        if ($request->filled('min_rating')) {
            $query->where('rating_average', '>=', $request->min_rating);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'LIKE', "%{$s}%")->orWhere('name_ar', 'LIKE', "%{$s}%"));
        }

        match ($request->get('sort_by', 'rating')) {
            'experience' => $query->orderByDesc('experience_years'),
            'sessions'   => $query->orderByDesc('total_sessions'),
            'price'      => $query->orderBy('price_per_hour'),
            default      => $query->orderByDesc('rating_average'),
        };

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($request->get('per_page', 15)),
            'message' => 'Trainers retrieved successfully',
        ]);
    }

    // ---------------------------------------------------------------
    // PUBLIC — Show
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/trainers/{id}",
     *     summary="Trainer detail",
     *     description="Full trainer profile with locations, schedule, recent reviews, and like/favorite status for authenticated users.",
     *     operationId="showTrainer",
     *     tags={"Trainers"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Trainer found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",               type="integer", example=1),
     *                 @OA\Property(property="name",             type="string",  example="Khalid Al-Mansouri"),
     *                 @OA\Property(property="name_ar",          type="string",  example="خالد المنصوري"),
     *                 @OA\Property(property="bio",              type="string"),
     *                 @OA\Property(property="specialty",        type="string",  example="coaching"),
     *                 @OA\Property(property="certifications",   type="array",   @OA\Items(type="string")),
     *                 @OA\Property(property="experience_years", type="integer", example=8),
     *                 @OA\Property(property="price_per_hour",   type="number",  format="float", example=150.00),
     *                 @OA\Property(property="rating_average",   type="number",  format="float", example=4.8),
     *                 @OA\Property(property="total_sessions",   type="integer", example=200),
     *                 @OA\Property(property="likes_count",      type="integer", example=35),
     *                 @OA\Property(property="photo_url",        type="string"),
     *                 @OA\Property(property="is_liked_by_auth",     type="boolean", example=false, description="true if authenticated user liked this trainer"),
     *                 @OA\Property(property="is_favorited_by_auth", type="boolean", example=false, description="true if authenticated user favorited this trainer"),
     *                 @OA\Property(property="locations",  type="array",  @OA\Items(type="object")),
     *                 @OA\Property(property="schedules",  type="array",  @OA\Items(type="object")),
     *                 @OA\Property(property="reviews",    type="array",  @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Trainer not found")
     * )
     */
    public function show(int $id)
    {
        $trainer = Trainer::with([
            'locations.city',
            'schedules',
            'reviews' => fn ($q) => $q->with('user:id,first_name,last_name,avatar')->latest()->limit(10),
        ])->approved()->find($id);

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $trainer->append(['photo_url', 'is_liked_by_auth', 'is_favorited_by_auth']),
            'message' => 'Trainer retrieved successfully',
        ]);
    }

    // ---------------------------------------------------------------
    // PUBLIC — Locations
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/trainer-locations",
     *     summary="List all training locations",
     *     description="All available training locations with GPS coordinates and trainer info.",
     *     operationId="listTrainerLocations",
     *     tags={"Trainers"},
     *     @OA\Parameter(name="city_id",    in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="trainer_id", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Locations retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="count",   type="integer", example=8),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id",             type="integer", example=1),
     *                     @OA\Property(property="location_name",  type="string",  example="Al-Naseem Training Circuit"),
     *                     @OA\Property(property="location_name_ar", type="string", example="حلبة النسيم"),
     *                     @OA\Property(property="latitude",       type="number",  format="float", example=24.7250),
     *                     @OA\Property(property="longitude",      type="number",  format="float", example=46.6900),
     *                     @OA\Property(property="is_available",   type="boolean", example=true),
     *                     @OA\Property(property="city",           type="object",
     *                         @OA\Property(property="id",   type="integer"),
     *                         @OA\Property(property="name", type="string", example="Riyadh")
     *                     ),
     *                     @OA\Property(property="trainer", type="object",
     *                         @OA\Property(property="id",   type="integer"),
     *                         @OA\Property(property="name", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function locations(Request $request)
    {
        $query = TrainerLocation::with(['city', 'trainer:id,name,name_ar,photo,rating_average'])
            ->where('is_available', true)
            ->whereHas('trainer', fn ($q) => $q->approved());

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        if ($request->filled('trainer_id')) {
            $query->where('trainer_id', $request->trainer_id);
        }

        $locations = $query->get();

        return response()->json([
            'success' => true,
            'data'    => $locations,
            'count'   => $locations->count(),
            'message' => 'Trainer locations retrieved successfully',
        ]);
    }

    // ---------------------------------------------------------------
    // PROVIDER — Register as trainer
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/trainer/register",
     *     summary="Register like Provider as trainer",
     *     description="Creates a trainer profile for the authenticated user. Profile starts in 'pending' status and must be approved by admin before appearing publicly. No subscription required.",
     *     operationId="registerTrainer",
     *     tags={"Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name","specialty","experience_years","price_per_hour"},
     *                 @OA\Property(property="name",             type="string",  example="Khalid Al-Mansouri"),
     *                 @OA\Property(property="name_ar",          type="string",  example="خالد المنصوري"),
     *                 @OA\Property(property="bio",              type="string",  example="Professional motorcycle coach with 8 years experience"),
     *                 @OA\Property(property="bio_ar",           type="string",  example="مدرب دراجات نارية محترف"),
     *                 @OA\Property(property="specialty",        type="string",  enum={"coaching","competition","off-road","street","custom"}, example="coaching"),
     *                 @OA\Property(property="experience_years", type="integer", example=8),
     *                 @OA\Property(property="price_per_hour",   type="number",  example=150.00),
     *                 @OA\Property(property="certifications",   type="array",   @OA\Items(type="string"), example={"FIM Level 2","MSF Certified"}),
     *                 @OA\Property(property="photo",            type="string",  format="binary", description="Profile photo (max 2MB)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Trainer profile submitted — pending admin approval",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Your trainer profile has been submitted and is pending approval."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",      type="integer", example=1),
     *                 @OA\Property(property="name",    type="string",  example="Khalid Al-Mansouri"),
     *                 @OA\Property(property="status",  type="string",  example="pending"),
     *                 @OA\Property(property="specialty", type="string", example="coaching")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=409, description="User already has a trainer profile"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (Trainer::where('user_id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'You already have a trainer profile'], 409);
        }

        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'name_ar'               => 'nullable|string|max:255',
            'bio'                   => 'nullable|string|max:3000',
            'bio_ar'                => 'nullable|string|max:3000',
            'specialty'             => 'required|in:coaching,competition,off-road,street,custom',
            'experience_years'      => 'required|integer|min:0|max:50',
            'price_per_hour'        => 'required|numeric|min:0',
            'certifications'        => 'nullable|string|max:3000',
            'photo'                 => 'nullable|image|max:2048',
            'cover'                 => 'nullable|image|max:5120',
            // Certification file paths already uploaded via POST /api/trainer/upload-certificates
            'certification_files'   => 'nullable|array|max:10',
            'certification_files.*' => 'string|max:500',
        ]);

        DB::beginTransaction();
        try {
            if ($request->hasFile('photo')) {
                $validated['photo'] = $request->file('photo')->store('trainers/photos', 'public');
            }
            if ($request->hasFile('cover')) {
                $validated['cover'] = $request->file('cover')->store('trainers/covers', 'public');
            }

            $trainer = Trainer::create([
                'user_id'             => $user->id,
                'name'                => $validated['name'],
                'name_ar'             => $validated['name_ar'] ?? null,
                'bio'                 => $validated['bio'] ?? null,
                'bio_ar'              => $validated['bio_ar'] ?? null,
                'specialty'           => $validated['specialty'],
                'experience_years'    => $validated['experience_years'],
                'price_per_hour'      => $validated['price_per_hour'],
                'certifications'      => $validated['certifications'] ?? null,
                'certification_files' => $validated['certification_files'] ?? [],
                'photo'               => $validated['photo'] ?? null,
                'status'              => 'pending',
                'is_available'        => false,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Registration failed', 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => $trainer,
            'message' => 'Your trainer profile has been submitted and is pending approval.',
        ], 201);
    }

    // ---------------------------------------------------------------
    // PROVIDER — Update profile
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/trainer/profile",
     *     summary="Update trainer profile",
     *     description="Update the authenticated trainer's profile. Only the trainer who owns the profile can update it.",
     *     operationId="updateTrainerProfile",
     *     tags={"Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="name",             type="string",  example="Khalid Al-Mansouri"),
     *                 @OA\Property(property="name_ar",          type="string",  example="خالد المنصوري"),
     *                 @OA\Property(property="bio",              type="string"),
     *                 @OA\Property(property="bio_ar",           type="string"),
     *                 @OA\Property(property="specialty",        type="string",  enum={"coaching","competition","off-road","street","custom"}),
     *                 @OA\Property(property="experience_years", type="integer"),
     *                 @OA\Property(property="price_per_hour",   type="number"),
     *                 @OA\Property(property="certifications",   type="array",   @OA\Items(type="string")),
     *                 @OA\Property(property="is_available",     type="integer", enum={0,1}, example=1),
     *                 @OA\Property(property="photo",            type="string",  format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Profile updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data",    type="object"),
     *             @OA\Property(property="message", type="string", example="Profile updated successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Trainer profile not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function updateProfile(Request $request)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->first();

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer profile not found'], 404);
        }

        $validated = $request->validate([
            'name'                  => 'nullable|string|max:255',
            'name_ar'               => 'nullable|string|max:255',
            'bio'                   => 'nullable|string|max:3000',
            'bio_ar'                => 'nullable|string|max:3000',
            'specialty'             => 'nullable|in:coaching,competition,off-road,street,custom',
            'experience_years'      => 'nullable|integer|min:0|max:50',
            'price_per_hour'        => 'nullable|numeric|min:0',
            'certifications'        => 'nullable|string|max:3000',
            'is_available'          => 'nullable|boolean',
            'photo'                 => 'nullable|image|max:2048',
            'cover'                 => 'nullable|image|max:5120',
            // Paths returned by POST /api/trainer/upload-certificates
            'certification_files'   => 'nullable|array|max:10',
            'certification_files.*' => 'string|max:500',
        ]);

        if ($request->hasFile('photo')) {
            if ($trainer->photo) Storage::disk('public')->delete($trainer->photo);
            $validated['photo'] = $request->file('photo')->store('trainers/photos', 'public');
        }

        if ($request->hasFile('cover')) {
            if ($trainer->cover) Storage::disk('public')->delete($trainer->cover);
            $validated['cover'] = $request->file('cover')->store('trainers/covers', 'public');
        }

        $trainer->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json([
            'success' => true,
            'data'    => $trainer->fresh()->append('photo_url'),
            'message' => 'Profile updated successfully',
        ]);
    }

    // ---------------------------------------------------------------
    // PROVIDER — Manage locations
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/trainer/locations",
     *     summary="Add a training location",
     *     description="Add a new training location to the authenticated trainer's profile.",
     *     operationId="addTrainerLocation",
     *     tags={"Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"location_name","city_id"},
     *             @OA\Property(property="location_name",    type="string",  example="Al-Naseem Training Circuit"),
     *             @OA\Property(property="location_name_ar", type="string",  example="حلبة النسيم للتدريب"),
     *             @OA\Property(property="city_id",          type="integer", example=1),
     *             @OA\Property(property="latitude",         type="number",  format="float", example=24.7250),
     *             @OA\Property(property="longitude",        type="number",  format="float", example=46.6900)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Location added",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data",    type="object",
     *                 @OA\Property(property="id",            type="integer", example=3),
     *                 @OA\Property(property="location_name", type="string",  example="Al-Naseem Training Circuit"),
     *                 @OA\Property(property="city",          type="object")
     *             ),
     *             @OA\Property(property="message", type="string", example="Location added successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, description="No trainer profile found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function addLocation(Request $request)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->first();

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $validated = $request->validate([
            'location_name'    => 'required|string|max:255',
            'location_name_ar' => 'nullable|string|max:255',
            'city_id'          => 'required|exists:cities,id',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',
        ]);

        $location = $trainer->locations()->create(array_merge($validated, ['is_available' => true]));

        return response()->json([
            'success' => true,
            'data'    => $location->load('city'),
            'message' => 'Location added successfully',
        ], 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/trainer/locations/{locationId}",
     *     summary="Delete a training location",
     *     description="Remove a training location from the trainer's profile.",
     *     operationId="deleteTrainerLocation",
     *     tags={"Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="locationId", in="path", required=true, @OA\Schema(type="integer", example=3)),
     *     @OA\Response(response=200, description="Location deleted"),
     *     @OA\Response(response=403, description="Location does not belong to your profile"),
     *     @OA\Response(response=404, description="Location not found")
     * )
     */
    public function deleteLocation(int $locationId)
    {
        $user     = JWTAuth::parseToken()->authenticate();
        $trainer  = Trainer::where('user_id', $user->id)->first();
        $location = TrainerLocation::find($locationId);

        if (!$location) {
            return response()->json(['success' => false, 'message' => 'Location not found'], 404);
        }

        if (!$trainer || $location->trainer_id !== $trainer->id) {
            return response()->json(['success' => false, 'message' => 'Location does not belong to your profile'], 403);
        }

        $location->delete();

        return response()->json(['success' => true, 'message' => 'Location deleted successfully']);
    }

    // ---------------------------------------------------------------
    // PROVIDER — My profile
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/trainer/me",
     *     summary="Get my trainer profile",
     *     description="Returns the authenticated user's trainer profile with all locations, schedule, bookings summary, and payout balance.",
     *     operationId="getMyTrainerProfile",
     *     tags={"Trainers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Profile retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="trainer",  type="object"),
     *                 @OA\Property(property="stats", type="object",
     *                     @OA\Property(property="total_bookings",    type="integer", example=45),
     *                     @OA\Property(property="pending_bookings",  type="integer", example=3),
     *                     @OA\Property(property="total_earnings",    type="number",  format="float", example=3600.00),
     *                     @OA\Property(property="pending_payout",    type="number",  format="float", example=800.00)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="No trainer profile found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function myProfile()
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::with(['locations.city', 'schedules'])
            ->where('user_id', $user->id)
            ->first();

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 404);
        }

        $stats = [
            'total_bookings'   => $trainer->bookings()->count(),
            'pending_bookings' => $trainer->bookings()->pending()->count(),
            'total_earnings'   => $trainer->payouts()->where('status', 'paid')->sum('amount'),
            'pending_payout'   => $trainer->payouts()->where('status', 'pending')->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'data'    => ['trainer' => $trainer->append('photo_url'), 'stats' => $stats],
            'message' => 'Trainer profile retrieved successfully',
        ]);
    }
}
