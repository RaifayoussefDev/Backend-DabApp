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

        $course = TrainerCourse::with(['level', 'location.city'])
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
     *     description="Trainer creates a new course offering. Starts in 'draft' status. Publish it with the /publish endpoint.",
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
     *             @OA\Property(property="can_travel",        type="boolean", example=false,  description="Trainer can go to client's location")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Course created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data",    type="object",
     *                 @OA\Property(property="id",            type="integer", example=1),
     *                 @OA\Property(property="title",         type="string",  example="Beginner Circuit Training"),
     *                 @OA\Property(property="status",        type="string",  example="draft"),
     *                 @OA\Property(property="total_price",   type="string",  example="1500.00")
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
            'can_travel'        => 'nullable|boolean',
        ]);

        $course = TrainerCourse::create(array_merge($validated, [
            'trainer_id' => $trainer->id,
            'status'     => 'draft',
        ]));

        return response()->json([
            'success' => true,
            'data'    => $course->load(['level', 'location.city']),
            'message' => 'Course created successfully',
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/trainer/courses/{id}",
     *     summary="Update a course",
     *     description="Update any field of a draft or published course. Archived courses cannot be edited.",
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
     *             @OA\Property(property="can_travel",        type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Course updated"),
     *     @OA\Response(response=403, description="Cannot edit an archived course"),
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
        ]);

        $course->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json([
            'success' => true,
            'data'    => $course->fresh()->load(['level', 'location.city']),
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

        if ($course->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Only draft courses can be deleted. Archive it first.'], 403);
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

        if ($course->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Only draft courses can be published'], 400);
        }

        $course->update(['status' => 'published']);

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

        $course->update(['status' => 'archived']);

        return response()->json(['success' => true, 'message' => 'Course archived successfully']);
    }
}
