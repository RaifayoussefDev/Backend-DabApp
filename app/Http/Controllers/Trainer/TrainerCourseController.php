<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\EquipmentType;
use App\Models\MyGarage;
use App\Models\Trainer;
use App\Models\TrainerCourse;
use App\Models\TrainerEquipment;
use App\Models\TrainerLevelApproval;
use App\Models\TrainerTrainingBike;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Trainer - Courses",
 *     description="Trainer creates and manages their course offerings"
 * )
 */
class TrainerCourseController extends Controller
{
    private function getTrainer(): ?Trainer
    {
        return Trainer::where('user_id', JWTAuth::parseToken()->authenticate()->id)->first();
    }

    /**
     * Accepts either an existing trainer_equipment.id (already on the trainer's profile)
     * or an equipment_types.id (global catalog) — auto-provisioning the trainer_equipment
     * row for the latter so clients can select straight from the catalog when building a course.
     *
     * @return int[] resolved trainer_equipment ids
     * @throws \InvalidArgumentException
     */
    private function resolveEquipmentIds(Trainer $trainer, array $ids): array
    {
        $resolved = [];
        foreach ($ids as $id) {
            $owned = TrainerEquipment::where('id', $id)->where('trainer_id', $trainer->id)->first();
            if ($owned) {
                $resolved[] = $owned->id;
                continue;
            }

            $type = EquipmentType::find($id);
            if (!$type) {
                throw new \InvalidArgumentException("Equipment item {$id} not found in your trainer profile or the equipment catalog.");
            }

            $item = TrainerEquipment::firstOrCreate(
                ['trainer_id' => $trainer->id, 'equipment_type_id' => $type->id],
                ['name' => $type->name, 'name_ar' => $type->name_ar, 'icon' => $type->icon, 'is_available' => true, 'sort_order' => $type->sort_order]
            );
            $resolved[] = $item->id;
        }
        return array_values(array_unique($resolved));
    }

    /**
     * Accepts either an existing trainer_training_bikes.id (already selected as a training
     * bike) or a my_garage.id (any bike in the trainer's own garage) — auto-provisioning the
     * trainer_training_bikes row for the latter, same as TrainerTrainingBikeController@store.
     *
     * @return int[] resolved trainer_training_bikes ids
     * @throws \InvalidArgumentException
     */
    private function resolveTrainingBikeIds(Trainer $trainer, array $ids): array
    {
        $resolved = [];
        foreach ($ids as $id) {
            $owned = TrainerTrainingBike::where('id', $id)->where('trainer_id', $trainer->id)->first();
            if ($owned) {
                $resolved[] = $owned->id;
                continue;
            }

            $garage = MyGarage::where('id', $id)->where('user_id', $trainer->user_id)->first();
            if (!$garage) {
                throw new \InvalidArgumentException("Training bike {$id} not found in your training bikes or your garage.");
            }

            $bike = TrainerTrainingBike::firstOrCreate(
                ['trainer_id' => $trainer->id, 'garage_id' => $garage->id],
                ['is_primary' => false]
            );
            $resolved[] = $bike->id;
        }
        return array_values(array_unique($resolved));
    }

    // ---------------------------------------------------------------
    // PUBLIC — browse all courses across all trainers
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/courses",
     *     summary="Browse all published courses",
     *     description="Returns a paginated list of published courses across all approved trainers. Each course includes its own equipment and training bikes selection.",
     *     operationId="listAllCourses",
     *     tags={"Trainer - Courses"},
     *     @OA\Parameter(name="level_id",   in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="city_id",    in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="location_id",in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="can_travel", in="query", required=false, @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Parameter(name="min_price",  in="query", required=false, @OA\Schema(type="number", example=50)),
     *     @OA\Parameter(name="max_price",  in="query", required=false, @OA\Schema(type="number", example=500)),
     *     @OA\Parameter(name="search",     in="query", required=false, @OA\Schema(type="string", example="circuit")),
     *     @OA\Parameter(name="sort_by",    in="query", required=false, @OA\Schema(type="string", enum={"newest","price_asc","price_desc"}, default="newest")),
     *     @OA\Parameter(name="per_page",   in="query", required=false, @OA\Schema(type="integer", example=15)),
     *     @OA\Response(
     *         response=200,
     *         description="Courses retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",             type="integer", example=6),
     *                         @OA\Property(property="title",          type="string",  example="Beginner Circuit Training"),
     *                         @OA\Property(property="effective_price",type="number",  example=120.00),
     *                         @OA\Property(property="price_per_hour", type="number",  example=120.00),
     *                         @OA\Property(property="price_per_session", type="string", example="240.00"),
     *                         @OA\Property(property="can_travel",     type="boolean", example=false),
     *                         @OA\Property(property="level",          type="object", nullable=true),
     *                         @OA\Property(property="location",       type="object", nullable=true),
     *                         @OA\Property(property="equipment", type="array",
     *                             @OA\Items(type="object",
     *                                 @OA\Property(property="icon", type="string", example="helmet"),
     *                                 @OA\Property(property="name", type="string", example="Helmet")
     *                             )
     *                         ),
     *                         @OA\Property(property="trainingBikes", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="trainer", type="object",
     *                             @OA\Property(property="id",               type="integer"),
     *                             @OA\Property(property="name",             type="string"),
     *                             @OA\Property(property="name_ar",          type="string"),
     *                             @OA\Property(property="photo_url",        type="string"),
     *                             @OA\Property(property="rating_average",   type="number"),
     *                             @OA\Property(property="experience_years", type="integer")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function browseAll(Request $request)
    {
        $query = TrainerCourse::with([
                'level',
                'location.city',
                'trainer:id,name,name_ar,photo,rating_average,experience_years,is_available',
                'equipment',
                'trainingBikes.garage.brand', 'trainingBikes.garage.model', 'trainingBikes.garage.year',
            ])
            ->published()
            ->whereHas('trainer', fn ($q) => $q->approved());

        if ($request->filled('level_id')) {
            $query->where('level_id', $request->level_id);
        }

        if ($request->filled('city_id')) {
            $query->whereHas('location', fn ($q) => $q->where('city_id', $request->city_id));
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->has('can_travel')) {
            $query->where('can_travel', $request->boolean('can_travel'));
        }

        if ($request->filled('min_price')) {
            $query->where('original_price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('original_price', '<=', $request->max_price);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('title', 'LIKE', "%{$s}%")->orWhere('title_ar', 'LIKE', "%{$s}%"));
        }

        match ($request->get('sort_by', 'newest')) {
            'price_asc'  => $query->orderBy('original_price', 'asc'),
            'price_desc' => $query->orderBy('original_price', 'desc'),
            default      => $query->latest(),
        };

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($request->get('per_page', 15)),
            'message' => 'Courses retrieved successfully',
        ]);
    }

    // ---------------------------------------------------------------
    // PUBLIC — browse courses for a specific trainer
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/trainers/{id}/courses",
     *     summary="List a trainer's published courses",
     *     description="Returns all published and active courses offered by the given trainer. Visible to everyone.",
     *     operationId="listTrainerCourses",
     *     tags={"Trainer - Courses"},
     *     @OA\Parameter(name="id",       in="path",  required=true,  @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="level_id", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Courses retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id",                type="integer", example=1),
     *                     @OA\Property(property="title",             type="string",  example="Beginner Circuit Training"),
     *                     @OA\Property(property="hours_per_session", type="integer", example=2),
     *                     @OA\Property(property="total_sessions",    type="integer", example=5),
     *                     @OA\Property(property="start_date",        type="string",  format="date",    example="2026-07-10", nullable=true),
     *                     @OA\Property(property="session_time",      type="string",  format="time",    example="09:00:00",   nullable=true),
     *                     @OA\Property(property="original_price",    type="number",  example=150.00),
     *                     @OA\Property(property="promo_price",       type="number",  example=120.00,   nullable=true),
     *                     @OA\Property(property="effective_price",   type="number",  example=120.00),
     *                     @OA\Property(property="price_per_hour",    type="number",  example=120.00, description="Same as effective_price"),
     *                     @OA\Property(property="price_per_session", type="string",  example="240.00", description="price_per_hour × hours_per_session"),
     *                     @OA\Property(property="total_price",       type="string",  example="1200.00"),
     *                     @OA\Property(property="can_travel",        type="boolean", example=false),
     *                     @OA\Property(property="level",             type="object",  nullable=true),
     *                     @OA\Property(property="location",          type="object",  nullable=true),
     *                     @OA\Property(property="equipment",         type="array",   @OA\Items(type="object")),
     *                     @OA\Property(property="trainingBikes",     type="array",   @OA\Items(type="object"))
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Trainer not found")
     * )
     */
    public function publicIndex(Request $request, int $trainerId)
    {
        $trainer = Trainer::approved()->find($trainerId);
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $query = TrainerCourse::with(['level', 'location.city', 'equipment', 'trainingBikes.garage.brand', 'trainingBikes.garage.model', 'trainingBikes.garage.year'])
            ->where('trainer_id', $trainer->id)
            ->published();

        if ($request->filled('level_id')) {
            $query->where('level_id', $request->level_id);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->latest()->get(),
            'message' => 'Courses retrieved successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/trainers/{id}/courses/{courseId}",
     *     summary="Course details (public)",
     *     description="Returns full detail for one published course — description, trainer info, duration, pricing, image, sessions, equipment and training bikes. Visible to everyone.",
     *     operationId="getPublicCourseDetail",
     *     tags={"Trainer - Courses"},
     *     @OA\Parameter(name="id",       in="path", required=true, @OA\Schema(type="integer", example=1), description="Trainer ID"),
     *     @OA\Parameter(name="courseId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Course retrieved"),
     *     @OA\Response(response=404, description="Course not found or not published")
     * )
     */
    public function publicShow(int $trainerId, int $courseId)
    {
        $trainer = Trainer::approved()->find($trainerId);
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $course = TrainerCourse::with([
                'level',
                'location.city',
                'sessions',
                'equipment',
                'trainingBikes.garage.brand', 'trainingBikes.garage.model', 'trainingBikes.garage.year',
                'trainer:id,name,name_ar,photo,cover,bio,bio_ar,rating_average,experience_years,is_available',
            ])
            ->where('id', $courseId)
            ->where('trainer_id', $trainer->id)
            ->published()
            ->first();

        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $course,
            'message' => 'Course retrieved successfully',
        ]);
    }

    // ---------------------------------------------------------------
    // PROVIDER — my courses
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/trainer/courses",
     *     summary="List my courses",
     *     description="Returns all courses (all statuses) for the authenticated trainer.",
     *     operationId="myTrainerCourses",
     *     tags={"Trainer - Courses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"draft","published","archived"})),
     *     @OA\Response(
     *         response=200,
     *         description="Courses retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data",    type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=403, description="No trainer profile found")
     * )
     */
    public function index(Request $request)
    {
        $trainer = $this->getTrainer();
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $query = TrainerCourse::with(['level', 'location.city', 'equipment', 'trainingBikes.garage.brand', 'trainingBikes.garage.model', 'trainingBikes.garage.year', 'sessions'])
            ->where('trainer_id', $trainer->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->latest()->get(),
            'message' => 'Courses retrieved successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/trainer/courses/{id}",
     *     summary="Get a single course",
     *     operationId="showTrainerCourse",
     *     tags={"Trainer - Courses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Course retrieved"),
     *     @OA\Response(response=404, description="Course not found")
     * )
     */
    public function show(int $id)
    {
        $trainer = $this->getTrainer();
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $course = TrainerCourse::with(['level', 'location.city', 'sessions', 'equipment', 'trainingBikes.garage.brand', 'trainingBikes.garage.model', 'trainingBikes.garage.year'])
            ->where('trainer_id', $trainer->id)
            ->find($id);

        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $course, 'message' => 'Course retrieved successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/trainer/courses/price-preview",
     *     summary="Preview the auto-calculated price for a level (price_type=per_hour courses)",
     *     description="Returns the trainer's approved price for a given level, so the app can display it in the original_price field before creating a 'per_hour'-priced course. Optionally pass hours_per_session and total_sessions to also get the per-session and total price breakdown, using the same formula the course will use. Not applicable to 'total'-priced courses, where original_price/promo_price are entered directly as the whole package price.",
     *     operationId="previewTrainerCoursePrice",
     *     tags={"Trainer - Courses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="level_id",          in="query", required=true,  @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="hours_per_session",  in="query", required=false, @OA\Schema(type="integer", example=2)),
     *     @OA\Parameter(name="total_sessions",     in="query", required=false, @OA\Schema(type="integer", example=5)),
     *     @OA\Response(
     *         response=200,
     *         description="Price breakdown",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="level_id",          type="integer", example=1),
     *                 @OA\Property(property="original_price",    type="number", example=150.00, description="Auto-calculated — display this in the original_price field"),
     *                 @OA\Property(property="price_per_hour",     type="number", example=150.00),
     *                 @OA\Property(property="price_per_session",  type="string", example="300.00", nullable=true, description="Only returned when hours_per_session is passed"),
     *                 @OA\Property(property="total_price",        type="string", example="1500.00", nullable=true, description="Only returned when hours_per_session and total_sessions are passed")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="No trainer profile found"),
     *     @OA\Response(response=422, description="This level is not approved for your account yet")
     * )
     */
    public function pricePreview(Request $request)
    {
        $trainer = $this->getTrainer();
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $validated = $request->validate([
            'level_id'          => 'required|integer|exists:trainer_levels,id',
            'hours_per_session' => 'nullable|integer|min:1|max:8',
            'total_sessions'    => 'nullable|integer|min:1',
        ]);

        $approval = TrainerLevelApproval::where('trainer_id', $trainer->id)
            ->where('level_id', $validated['level_id'])
            ->approved()
            ->first();

        if (!$approval) {
            return response()->json(['success' => false, 'message' => 'This level is not approved for your account yet.'], 422);
        }

        $pricePerHour = (float) $approval->approved_price;
        $data = [
            'level_id'       => $validated['level_id'],
            'original_price' => $approval->approved_price,
            'price_per_hour' => $approval->approved_price,
        ];

        if (!empty($validated['hours_per_session'])) {
            $pricePerSession = $pricePerHour * $validated['hours_per_session'];
            $data['price_per_session'] = number_format($pricePerSession, 2, '.', '');

            if (!empty($validated['total_sessions'])) {
                $data['total_price'] = number_format($pricePerSession * $validated['total_sessions'], 2, '.', '');
            }
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * @OA\Post(
     *     path="/api/trainer/courses",
     *     summary="Create a course",
     *     description="Trainer creates a new course offering. Starts in 'draft' status. Optionally include 'sessions' array to create all session descriptions in one call. Publish it with the /publish endpoint.",
     *     operationId="createTrainerCourse",
     *     tags={"Trainer - Courses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","hours_per_session","total_sessions","price_type","location_id","original_price"},
     *             @OA\Property(property="title",             type="string",  example="Beginner Circuit Training"),
     *             @OA\Property(property="title_ar",          type="string",  example="تدريب المبتدئين في الحلبة"),
     *             @OA\Property(property="description",       type="string",  example="Learn the basics of motorcycle control"),
     *             @OA\Property(property="description_ar",    type="string"),
     *             @OA\Property(property="image",             type="string",  example="trainer-courses/images/abc123.jpg", description="Path returned by POST /trainer/courses/upload-image"),
     *             @OA\Property(property="price_type",        type="string",  enum={"per_hour","total"}, example="per_hour", description="'per_hour': original_price is an hourly rate, requires an approved level_id, use GET /trainer/courses/price-preview to compute it. 'total': original_price/promo_price are the whole package price, level_id optional."),
     *             @OA\Property(property="level_id",          type="integer", example=1,     description="Required when price_type=per_hour — must be a level approved for this trainer"),
     *             @OA\Property(property="hours_per_session", type="integer", example=2,     description="1 to 8 hours"),
     *             @OA\Property(property="total_sessions",    type="integer", example=5,     description="Number of sessions in the package"),
     *             @OA\Property(property="start_date",        type="string",  format="date", example="2026-07-10", description="Optional fixed start date"),
     *             @OA\Property(property="session_time",      type="string",  format="time", example="09:00",     description="Optional start time"),
     *             @OA\Property(property="original_price",    type="number",  example=150.00, description="per_hour: hourly rate (see price-preview). total: whole package price."),
     *             @OA\Property(property="promo_price",       type="number",  example=120.00, description="Optional promotional price, must be less than original_price"),
     *             @OA\Property(property="location_id",       type="integer", example=2,     description="Required — must be one of the trainer's own locations"),
     *             @OA\Property(property="can_travel",        type="boolean", example=false,  description="Trainer can go to client's location"),
     *             @OA\Property(property="price_per_km",      type="number",  example=5.00,  description="Required when can_travel=true"),
     *             @OA\Property(property="equipment_ids",     type="array",   description="Optional — accepts either an existing trainer_equipment id, or an equipment_types catalog id (auto-added to your equipment list)", @OA\Items(type="integer")),
     *             @OA\Property(property="training_bike_ids", type="array",   description="Optional — accepts either an existing trainer_training_bikes id, or a my_garage id (auto-added as a training bike)", @OA\Items(type="integer")),
     *             @OA\Property(property="sessions",          type="array",   description="Optional — create session descriptions in the same request",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="session_number", type="integer", example=1),
     *                     @OA\Property(property="title",          type="string",  example="Introduction & Safety"),
     *                     @OA\Property(property="title_ar",       type="string",  example="المقدمة والسلامة"),
     *                     @OA\Property(property="description",    type="string",  example="Getting familiar with the bike."),
     *                     @OA\Property(property="description_ar", type="string",  example="التعرف على الدراجة."),
     *                     @OA\Property(property="duration_hours", type="integer", example=2)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Course created (with sessions if provided)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data",    type="object",
     *                 @OA\Property(property="id",            type="integer", example=1),
     *                 @OA\Property(property="title",         type="string",  example="Beginner Circuit Training"),
     *                 @OA\Property(property="status",        type="string",  example="draft"),
     *                 @OA\Property(property="sessions",      type="array", @OA\Items(type="object"))
     *             ),
     *             @OA\Property(property="message", type="string", example="Course created successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, description="No trainer profile found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $trainer = $this->getTrainer();
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }
        if ($trainer->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'Your trainer profile is not approved yet. You can create courses once an admin approves your profile.'], 403);
        }

        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'title_ar'          => 'nullable|string|max:255',
            'description'       => 'nullable|string|max:3000',
            'description_ar'    => 'nullable|string|max:3000',
            'image'             => 'nullable|string|max:500',
            'price_type'        => 'required|in:per_hour,total',
            'level_id'          => 'required_if:price_type,per_hour|nullable|integer|exists:trainer_levels,id',
            'hours_per_session' => 'required|integer|min:1|max:8',
            'total_sessions'    => 'required|integer|min:1',
            'start_date'        => 'nullable|date|after_or_equal:today',
            'session_time'      => 'nullable|date_format:H:i',
            'original_price'    => 'required|numeric|min:0',
            'promo_price'       => 'nullable|numeric|min:0|lt:original_price',
            'location_id'       => ['required', 'integer',
                function ($attr, $value, $fail) use ($trainer) {
                    if (!\App\Models\TrainerLocation::where('id', $value)->where('trainer_id', $trainer->id)->exists()) {
                        $fail('Location not found in your trainer profile.');
                    }
                }
            ],
            'can_travel'                 => 'nullable|boolean',
            'price_per_km'               => 'nullable|numeric|min:0',
            'equipment_ids'              => 'nullable|array',
            'equipment_ids.*'            => 'integer',
            'training_bike_ids'          => 'nullable|array',
            'training_bike_ids.*'        => 'integer',
            'sessions'                  => 'nullable|array',
            'sessions.*.session_number' => 'required_with:sessions|integer|min:1',
            'sessions.*.title'          => 'required_with:sessions|string|max:255',
            'sessions.*.title_ar'       => 'nullable|string|max:255',
            'sessions.*.description'    => 'nullable|string|max:3000',
            'sessions.*.description_ar' => 'nullable|string|max:3000',
            'sessions.*.duration_hours' => 'nullable|integer|min:1|max:24',
        ]);

        if ($validated['price_type'] === 'per_hour') {
            $approval = TrainerLevelApproval::where('trainer_id', $trainer->id)
                ->where('level_id', $validated['level_id'])
                ->approved()
                ->first();

            if (!$approval) {
                return response()->json(['success' => false, 'message' => 'This level is not approved for your account yet.'], 422);
            }
        }

        try {
            $equipmentIds = $this->resolveEquipmentIds($trainer, $validated['equipment_ids'] ?? []);
            $trainingBikeIds = $this->resolveTrainingBikeIds($trainer, $validated['training_bike_ids'] ?? []);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $courseData = collect($validated)
            ->except(['sessions', 'equipment_ids', 'training_bike_ids'])
            ->toArray();

        $course = TrainerCourse::create(array_merge(
            $courseData,
            ['trainer_id' => $trainer->id, 'status' => 'draft']
        ));

        if (!empty($validated['sessions'])) {
            foreach ($validated['sessions'] as $sessionData) {
                $course->sessions()->create($sessionData);
            }
        }

        $course->equipment()->sync($equipmentIds);
        $course->trainingBikes()->sync($trainingBikeIds);

        return response()->json([
            'success' => true,
            'data'    => $course->load(['level', 'location.city', 'sessions', 'equipment', 'trainingBikes.garage.brand', 'trainingBikes.garage.model', 'trainingBikes.garage.year']),
            'message' => 'Course created successfully',
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/trainer/courses/{id}",
     *     summary="Update a course",
     *     description="Update any field of a draft course. Optionally include a 'sessions' array to bulk upsert session descriptions in the same request (same shape as course creation). Published courses cannot be edited — unpublish it first to make it editable again.",
     *     operationId="updateTrainerCourse",
     *     tags={"Trainer - Courses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="title",             type="string"),
     *             @OA\Property(property="title_ar",          type="string"),
     *             @OA\Property(property="description",       type="string"),
     *             @OA\Property(property="description_ar",    type="string"),
     *             @OA\Property(property="image",             type="string",  example="trainer-courses/images/abc123.jpg", description="Path returned by POST /trainer/courses/upload-image"),
     *             @OA\Property(property="price_type",        type="string",  enum={"per_hour","total"}, description="Switching to per_hour requires an approved level_id (new or already on the course)"),
     *             @OA\Property(property="level_id",          type="integer"),
     *             @OA\Property(property="hours_per_session", type="integer"),
     *             @OA\Property(property="total_sessions",    type="integer"),
     *             @OA\Property(property="start_date",        type="string", format="date"),
     *             @OA\Property(property="session_time",      type="string", format="time"),
     *             @OA\Property(property="original_price",    type="number", description="per_hour: hourly rate. total: whole package price."),
     *             @OA\Property(property="promo_price",       type="number", description="Must be less than original_price"),
     *             @OA\Property(property="location_id",       type="integer"),
     *             @OA\Property(property="can_travel",        type="boolean"),
     *             @OA\Property(property="equipment_ids",     type="array",   description="Optional — replaces the course's equipment selection. Accepts trainer_equipment ids or equipment_types catalog ids.", @OA\Items(type="integer")),
     *             @OA\Property(property="training_bike_ids", type="array",   description="Optional — replaces the course's training bikes selection. Accepts trainer_training_bikes ids or my_garage ids.", @OA\Items(type="integer")),
     *             @OA\Property(property="sessions",          type="array",   description="Optional — bulk upsert session descriptions in the same request",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="session_number", type="integer", example=1),
     *                     @OA\Property(property="title",          type="string",  example="Introduction & Safety"),
     *                     @OA\Property(property="title_ar",       type="string",  example="المقدمة والسلامة"),
     *                     @OA\Property(property="description",    type="string",  example="Getting familiar with the bike."),
     *                     @OA\Property(property="description_ar", type="string",  example="التعرف على الدراجة."),
     *                     @OA\Property(property="duration_hours", type="integer", example=2)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Course updated"),
     *     @OA\Response(response=403, description="Cannot edit an archived or published course"),
     *     @OA\Response(response=404, description="Course not found")
     * )
     */
    public function update(Request $request, int $id)
    {
        $trainer = $this->getTrainer();
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $course = TrainerCourse::where('trainer_id', $trainer->id)->find($id);
        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course not found'], 404);
        }

        if ($course->status === 'archived') {
            return response()->json(['success' => false, 'message' => 'Cannot edit an archived course'], 403);
        }

        if ($course->status === 'published') {
            return response()->json(['success' => false, 'message' => 'Cannot edit a published course. Unpublish it first.'], 403);
        }

        $validated = $request->validate([
            'title'             => 'nullable|string|max:255',
            'title_ar'          => 'nullable|string|max:255',
            'description'       => 'nullable|string|max:3000',
            'description_ar'    => 'nullable|string|max:3000',
            'image'             => 'nullable|string|max:500',
            'price_type'        => 'nullable|in:per_hour,total',
            'level_id'          => 'nullable|integer|exists:trainer_levels,id',
            'hours_per_session' => 'nullable|integer|min:1|max:8',
            'total_sessions'    => 'nullable|integer|min:1',
            'start_date'        => 'nullable|date',
            'session_time'      => 'nullable|date_format:H:i',
            'original_price'    => 'nullable|numeric|min:0',
            'promo_price'       => 'nullable|numeric|min:0',
            'location_id'       => ['nullable', 'integer',
                function ($attr, $value, $fail) use ($trainer) {
                    if ($value && !\App\Models\TrainerLocation::where('id', $value)->where('trainer_id', $trainer->id)->exists()) {
                        $fail('Location not found in your trainer profile.');
                    }
                }
            ],
            'can_travel'        => 'nullable|boolean',
            'price_per_km'      => 'nullable|numeric|min:0',
            'equipment_ids'              => 'nullable|array',
            'equipment_ids.*'            => 'integer',
            'training_bike_ids'          => 'nullable|array',
            'training_bike_ids.*'        => 'integer',
            'sessions'                  => 'nullable|array',
            'sessions.*.session_number' => 'required_with:sessions|integer|min:1',
            'sessions.*.title'          => 'nullable|string|max:255',
            'sessions.*.title_ar'       => 'nullable|string|max:255',
            'sessions.*.description'    => 'nullable|string|max:3000',
            'sessions.*.description_ar' => 'nullable|string|max:3000',
            'sessions.*.duration_hours' => 'nullable|integer|min:1|max:24',
        ]);

        $sessions = $validated['sessions'] ?? null;

        try {
            $equipmentIds = isset($validated['equipment_ids']) ? $this->resolveEquipmentIds($trainer, $validated['equipment_ids']) : null;
            $trainingBikeIds = isset($validated['training_bike_ids']) ? $this->resolveTrainingBikeIds($trainer, $validated['training_bike_ids']) : null;
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $effectivePriceType = $validated['price_type'] ?? $course->price_type;
        $effectiveLevelId   = $validated['level_id'] ?? $course->level_id;

        if ($effectivePriceType === 'per_hour') {
            if (!$effectiveLevelId) {
                return response()->json(['success' => false, 'message' => 'level_id is required for per_hour priced courses.'], 422);
            }

            $approval = TrainerLevelApproval::where('trainer_id', $trainer->id)
                ->where('level_id', $effectiveLevelId)
                ->approved()
                ->first();

            if (!$approval) {
                return response()->json(['success' => false, 'message' => 'This level is not approved for your account yet.'], 422);
            }
        }

        $promoPrice = $validated['promo_price'] ?? null;
        $originalPrice = $validated['original_price'] ?? $course->original_price;
        if ($promoPrice !== null && $promoPrice >= $originalPrice) {
            return response()->json(['success' => false, 'message' => 'promo_price must be less than the course price.'], 422);
        }

        $course->update(array_filter(
            collect($validated)->except(['sessions', 'equipment_ids', 'training_bike_ids'])->toArray(),
            fn ($v) => $v !== null
        ));

        if (!empty($sessions)) {
            foreach ($sessions as $sessionData) {
                $course->sessions()->updateOrCreate(
                    ['session_number' => $sessionData['session_number']],
                    collect($sessionData)->except('session_number')->toArray()
                );
            }
        }

        if ($equipmentIds !== null) {
            $course->equipment()->sync($equipmentIds);
        }

        if ($trainingBikeIds !== null) {
            $course->trainingBikes()->sync($trainingBikeIds);
        }

        return response()->json([
            'success' => true,
            'data'    => $course->fresh()->load(['level', 'location.city', 'sessions', 'equipment', 'trainingBikes.garage.brand', 'trainingBikes.garage.model', 'trainingBikes.garage.year']),
            'message' => 'Course updated successfully',
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/trainer/courses/{id}",
     *     summary="Delete a course",
     *     description="Only draft courses can be deleted. Published courses must be unpublished first.",
     *     operationId="deleteTrainerCourse",
     *     tags={"Trainer - Courses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Course deleted"),
     *     @OA\Response(response=403, description="Only draft courses can be deleted"),
     *     @OA\Response(response=404, description="Course not found")
     * )
     */
    public function destroy(int $id)
    {
        $trainer = $this->getTrainer();
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $course = TrainerCourse::where('trainer_id', $trainer->id)->find($id);
        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course not found'], 404);
        }

        if ($course->status === 'published') {
            return response()->json(['success' => false, 'message' => 'Cannot delete a published course. Unpublish it first.'], 403);
        }

        // course_id cascades at the DB level — without this check, deleting a course with
        // active bookings would silently wipe the trainee's booking/session rows (no
        // cancellation, no refund, and the linked payment is left orphaned but still 'paid').
        if ($course->bookings()->whereNotIn('status', ['cancelled'])->exists()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete a course with active bookings. Cancel them first.'], 400);
        }

        $course->delete();

        return response()->json(['success' => true, 'message' => 'Course deleted successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/trainer/courses/{id}/publish",
     *     summary="Publish a course",
     *     description="Moves a draft course to published status, making it visible to clients.",
     *     operationId="publishTrainerCourse",
     *     tags={"Trainer - Courses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Course published"),
     *     @OA\Response(response=400, description="Course is already published or archived"),
     *     @OA\Response(response=404, description="Course not found")
     * )
     */
    public function publish(int $id)
    {
        $trainer = $this->getTrainer();
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $course = TrainerCourse::where('trainer_id', $trainer->id)->find($id);
        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course not found'], 404);
        }

        if ($course->status === 'published') {
            return response()->json(['success' => false, 'message' => 'Course is already published'], 400);
        }

        $course->update(['status' => 'published', 'is_active' => true, 'draft_reason' => null]);

        try {
            app(\App\Services\NotificationService::class)->notifyUsersInCityNewCourse($course->fresh()->load('location'));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('TrainerCourseController@publish city-notify failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'data' => $course->fresh(), 'message' => 'Course published successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/trainer/courses/{id}/unpublish",
     *     summary="Unpublish a course",
     *     description="Moves a published course back to draft status, hiding it from clients and making it editable again.",
     *     operationId="unpublishTrainerCourse",
     *     tags={"Trainer - Courses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Course unpublished"),
     *     @OA\Response(response=400, description="Only published courses can be unpublished"),
     *     @OA\Response(response=404, description="Course not found")
     * )
     */
    public function unpublish(int $id)
    {
        $trainer = $this->getTrainer();
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $course = TrainerCourse::where('trainer_id', $trainer->id)->find($id);
        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course not found'], 404);
        }

        if ($course->status !== 'published') {
            return response()->json(['success' => false, 'message' => 'Only published courses can be unpublished'], 400);
        }

        $course->update(['status' => 'draft', 'is_active' => false]);

        return response()->json(['success' => true, 'data' => $course->fresh(), 'message' => 'Course unpublished — now editable as a draft']);
    }

    /**
     * @OA\Get(
     *     path="/api/trainers/{id}/travel-price",
     *     summary="Calculate travel price for a course",
     *     description="Returns the travel surcharge and total price based on km distance. Only applicable when course.can_travel=true.",
     *     operationId="calculateTravelPrice",
     *     tags={"Trainer - Courses"},
     *     @OA\Parameter(name="id",        in="path",  required=true, @OA\Schema(type="integer", example=1), description="Trainer ID"),
     *     @OA\Parameter(name="course_id", in="query", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="km",        in="query", required=true, @OA\Schema(type="number",  example=30), description="Distance in km from trainer location to client"),
     *     @OA\Response(
     *         response=200,
     *         description="Price breakdown",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="course_id",          type="integer", example=1),
     *                 @OA\Property(property="course_title",       type="string",  example="Beginner Circuit Training"),
     *                 @OA\Property(property="km",                 type="number",  example=30),
     *                 @OA\Property(property="price_per_km",       type="number",  example=2.50),
     *                 @OA\Property(property="travel_cost",        type="number",  example=75.00),
     *                 @OA\Property(property="session_price",      type="number",  example=120.00, description="Effective course price per session"),
     *                 @OA\Property(property="total_with_travel",  type="number",  example=195.00, description="session_price + travel_cost")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Course does not allow travel or price_per_km not set"),
     *     @OA\Response(response=404, description="Trainer or course not found")
     * )
     */
    public function travelPrice(Request $request, int $trainerId)
    {
        $request->validate([
            'course_id' => 'required|integer|exists:trainer_courses,id',
            'km'        => 'required|numeric|min:0',
        ]);

        $trainer = Trainer::approved()->find($trainerId);
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $course = TrainerCourse::where('id', $request->course_id)
            ->where('trainer_id', $trainer->id)
            ->published()
            ->first();

        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course not found'], 404);
        }

        if (!$course->can_travel) {
            return response()->json(['success' => false, 'message' => 'This course does not offer travel to client location'], 400);
        }

        if (!$course->price_per_km) {
            return response()->json(['success' => false, 'message' => 'Travel price per km not set for this course'], 400);
        }

        $km          = (float) $request->km;
        $pricePerKm  = (float) $course->price_per_km;
        $travelCost  = round($km * $pricePerKm, 2);
        $sessionPrice = (float) $course->effective_price;
        $total       = round($sessionPrice + $travelCost, 2);

        return response()->json([
            'success' => true,
            'data'    => [
                'course_id'         => $course->id,
                'course_title'      => $course->title,
                'km'                => $km,
                'price_per_km'      => $pricePerKm,
                'travel_cost'       => $travelCost,
                'session_price'     => $sessionPrice,
                'total_with_travel' => $total,
            ],
        ]);
    }
}
