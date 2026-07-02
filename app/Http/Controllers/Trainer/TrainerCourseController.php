<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Models\TrainerCourse;
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
     *                     @OA\Property(property="session_date",      type="string",  format="date",    example="2026-07-10", nullable=true),
     *                     @OA\Property(property="session_time",      type="string",  format="time",    example="09:00:00",   nullable=true),
     *                     @OA\Property(property="original_price",    type="number",  example=150.00),
     *                     @OA\Property(property="promo_price",       type="number",  example=120.00,   nullable=true),
     *                     @OA\Property(property="effective_price",   type="number",  example=120.00),
     *                     @OA\Property(property="total_price",       type="string",  example="1200.00"),
     *                     @OA\Property(property="can_travel",        type="boolean", example=false),
     *                     @OA\Property(property="level",             type="object",  nullable=true),
     *                     @OA\Property(property="location",          type="object",  nullable=true)
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

        $query = TrainerCourse::with(['level', 'location.city'])
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

        $query = TrainerCourse::with(['level', 'location.city'])
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

        $course = TrainerCourse::with(['level', 'location.city', 'sessions'])
            ->where('trainer_id', $trainer->id)
            ->find($id);

        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $course, 'message' => 'Course retrieved successfully']);
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
     *             required={"title","hours_per_session","total_sessions","original_price"},
     *             @OA\Property(property="title",             type="string",  example="Beginner Circuit Training"),
     *             @OA\Property(property="title_ar",          type="string",  example="تدريب المبتدئين في الحلبة"),
     *             @OA\Property(property="description",       type="string",  example="Learn the basics of motorcycle control"),
     *             @OA\Property(property="description_ar",    type="string"),
     *             @OA\Property(property="level_id",          type="integer", example=1,     description="ID of an approved level (optional)"),
     *             @OA\Property(property="hours_per_session", type="integer", example=2,     description="1 to 8 hours"),
     *             @OA\Property(property="total_sessions",    type="integer", example=5,     description="Number of sessions in the package"),
     *             @OA\Property(property="session_date",      type="string",  format="date", example="2026-07-10", description="Optional fixed date"),
     *             @OA\Property(property="session_time",      type="string",  format="time", example="09:00",     description="Optional start time"),
     *             @OA\Property(property="original_price",    type="number",  example=150.00, description="Price per hour in SAR"),
     *             @OA\Property(property="promo_price",       type="number",  example=120.00, description="Optional promotional price per hour"),
     *             @OA\Property(property="location_id",       type="integer", example=2,     description="ID from trainer's locations (optional)"),
     *             @OA\Property(property="can_travel",        type="boolean", example=false,  description="Trainer can go to client's location"),
     *             @OA\Property(property="price_per_km",      type="number",  example=5.00,  description="Required when can_travel=true"),
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

        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'title_ar'          => 'nullable|string|max:255',
            'description'       => 'nullable|string|max:3000',
            'description_ar'    => 'nullable|string|max:3000',
            'level_id'          => 'nullable|integer|exists:trainer_levels,id',
            'hours_per_session' => 'required|integer|min:1|max:8',
            'total_sessions'    => 'required|integer|min:1',
            'session_date'      => 'nullable|date|after_or_equal:today',
            'session_time'      => 'nullable|date_format:H:i',
            'original_price'    => 'required|numeric|min:0',
            'promo_price'       => 'nullable|numeric|min:0|lt:original_price',
            'location_id'       => ['nullable', 'integer',
                function ($attr, $value, $fail) use ($trainer) {
                    if (!\App\Models\TrainerLocation::where('id', $value)->where('trainer_id', $trainer->id)->exists()) {
                        $fail('Location not found in your trainer profile.');
                    }
                }
            ],
            'can_travel'                => 'nullable|boolean',
            'price_per_km'              => 'nullable|numeric|min:0',
            'sessions'                  => 'nullable|array',
            'sessions.*.session_number' => 'required_with:sessions|integer|min:1',
            'sessions.*.title'          => 'required_with:sessions|string|max:255',
            'sessions.*.title_ar'       => 'nullable|string|max:255',
            'sessions.*.description'    => 'nullable|string|max:3000',
            'sessions.*.description_ar' => 'nullable|string|max:3000',
            'sessions.*.duration_hours' => 'nullable|integer|min:1|max:24',
        ]);

        $course = TrainerCourse::create(array_merge(
            collect($validated)->except('sessions')->toArray(),
            ['trainer_id' => $trainer->id, 'status' => 'draft']
        ));

        if (!empty($validated['sessions'])) {
            foreach ($validated['sessions'] as $sessionData) {
                $course->sessions()->create($sessionData);
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $course->load(['level', 'location.city', 'sessions']),
            'message' => 'Course created successfully',
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/trainer/courses/{id}",
     *     summary="Update a course",
     *     description="Update any field of a draft course. Optionally include a 'sessions' array to bulk upsert session descriptions in the same request (same shape as course creation). Published and archived courses cannot be edited — archive a published course, delete it, and recreate it as a draft.",
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
     *             @OA\Property(property="level_id",          type="integer"),
     *             @OA\Property(property="hours_per_session", type="integer"),
     *             @OA\Property(property="total_sessions",    type="integer"),
     *             @OA\Property(property="session_date",      type="string", format="date"),
     *             @OA\Property(property="session_time",      type="string", format="time"),
     *             @OA\Property(property="original_price",    type="number"),
     *             @OA\Property(property="promo_price",       type="number"),
     *             @OA\Property(property="location_id",       type="integer"),
     *             @OA\Property(property="can_travel",        type="boolean"),
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
            return response()->json(['success' => false, 'message' => 'Cannot edit a published course. Archive it first.'], 403);
        }

        $validated = $request->validate([
            'title'             => 'nullable|string|max:255',
            'title_ar'          => 'nullable|string|max:255',
            'description'       => 'nullable|string|max:3000',
            'description_ar'    => 'nullable|string|max:3000',
            'level_id'          => 'nullable|integer|exists:trainer_levels,id',
            'hours_per_session' => 'nullable|integer|min:1|max:8',
            'total_sessions'    => 'nullable|integer|min:1',
            'session_date'      => 'nullable|date',
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
            'sessions'                  => 'nullable|array',
            'sessions.*.session_number' => 'required_with:sessions|integer|min:1',
            'sessions.*.title'          => 'nullable|string|max:255',
            'sessions.*.title_ar'       => 'nullable|string|max:255',
            'sessions.*.description'    => 'nullable|string|max:3000',
            'sessions.*.description_ar' => 'nullable|string|max:3000',
            'sessions.*.duration_hours' => 'nullable|integer|min:1|max:24',
        ]);

        $sessions = $validated['sessions'] ?? null;

        $course->update(array_filter(collect($validated)->except('sessions')->toArray(), fn ($v) => $v !== null));

        if (!empty($sessions)) {
            foreach ($sessions as $sessionData) {
                $course->sessions()->updateOrCreate(
                    ['session_number' => $sessionData['session_number']],
                    collect($sessionData)->except('session_number')->toArray()
                );
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $course->fresh()->load(['level', 'location.city', 'sessions']),
            'message' => 'Course updated successfully',
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/trainer/courses/{id}",
     *     summary="Delete a course",
     *     description="Only draft courses can be deleted. Published courses must be archived first.",
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
            return response()->json(['success' => false, 'message' => 'Cannot delete a published course. Archive it first.'], 403);
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

        $course->update(['status' => 'published', 'is_active' => true]);

        return response()->json(['success' => true, 'data' => $course->fresh(), 'message' => 'Course published successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/trainer/courses/{id}/archive",
     *     summary="Archive a course",
     *     description="Moves a published course to archived status, hiding it from clients.",
     *     operationId="archiveTrainerCourse",
     *     tags={"Trainer - Courses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Course archived"),
     *     @OA\Response(response=400, description="Only published courses can be archived"),
     *     @OA\Response(response=404, description="Course not found")
     * )
     */
    public function archive(int $id)
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
            return response()->json(['success' => false, 'message' => 'Only published courses can be archived'], 400);
        }

        $course->update(['status' => 'archived', 'is_active' => false]);

        return response()->json(['success' => true, 'message' => 'Course archived successfully']);
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
