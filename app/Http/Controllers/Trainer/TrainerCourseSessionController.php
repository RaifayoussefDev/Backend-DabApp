<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Models\TrainerCourse;
use App\Models\TrainerCourseSession;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Course Sessions",
 *     description="Per-session descriptions for trainer courses. A course with total_sessions=5 has 5 session slots, each with its own title and description shown to the client before booking."
 * )
 */
class TrainerCourseSessionController extends Controller
{
    private function trainerCourse(int $courseId): TrainerCourse
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->firstOrFail();

        return TrainerCourse::where('id', $courseId)
            ->where('trainer_id', $trainer->id)
            ->firstOrFail();
    }

    /**
     * @OA\Get(
     *     path="/api/trainer/courses/{courseId}/sessions",
     *     summary="Get session descriptions for a course (trainer)",
     *     tags={"Course Sessions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="courseId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Sessions retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="course_id",      type="integer", example=1),
     *                 @OA\Property(property="total_sessions", type="integer", example=5),
     *                 @OA\Property(property="sessions", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id",              type="integer", example=1),
     *                         @OA\Property(property="session_number",  type="integer", example=1),
     *                         @OA\Property(property="title",           type="string",  example="Introduction to bike controls"),
     *                         @OA\Property(property="title_ar",        type="string",  example="مقدمة في التحكم بالدراجة"),
     *                         @OA\Property(property="description",     type="string",  example="Safety gear, bike posture and basic throttle control"),
     *                         @OA\Property(property="description_ar",  type="string",  nullable=true),
     *                         @OA\Property(property="duration_hours",  type="integer", example=2)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Course not found or not yours")
     * )
     */
    public function index(int $courseId)
    {
        $course = $this->trainerCourse($courseId);

        return response()->json([
            'success' => true,
            'data'    => [
                'course_id'      => $course->id,
                'total_sessions' => $course->total_sessions,
                'sessions'       => $course->sessions,
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/trainer/courses/{courseId}/sessions",
     *     summary="Save all session descriptions (upsert)",
     *     description="Submit descriptions for all sessions at once. Existing sessions are updated, missing ones are created. Send all sessions you want to keep.",
     *     tags={"Course Sessions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="courseId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"sessions"},
     *             @OA\Property(property="sessions", type="array",
     *                 @OA\Items(
     *                     required={"session_number"},
     *                     @OA\Property(property="session_number",  type="integer", example=1, description="1-based index — must match total_sessions of the course"),
     *                     @OA\Property(property="title",           type="string",  example="Introduction"),
     *                     @OA\Property(property="title_ar",        type="string",  example="مقدمة"),
     *                     @OA\Property(property="description",     type="string",  example="Safety gear, bike posture, basic throttle control on a flat surface."),
     *                     @OA\Property(property="description_ar",  type="string",  example="معدات السلامة والوضعية الصحيحة والتحكم في الدراجة."),
     *                     @OA\Property(property="duration_hours",  type="integer", example=2)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sessions saved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="5 session(s) saved."),
     *             @OA\Property(property="data",    type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Course not found or not yours"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function sync(Request $request, int $courseId)
    {
        $course = $this->trainerCourse($courseId);

        $validated = $request->validate([
            'sessions'                    => 'required|array|min:1',
            'sessions.*.session_number'   => 'required|integer|min:1',
            'sessions.*.title'            => 'nullable|string|max:255',
            'sessions.*.title_ar'         => 'nullable|string|max:255',
            'sessions.*.description'      => 'nullable|string|max:2000',
            'sessions.*.description_ar'   => 'nullable|string|max:2000',
            'sessions.*.duration_hours'   => 'nullable|integer|min:1|max:8',
        ]);

        foreach ($validated['sessions'] as $s) {
            TrainerCourseSession::updateOrCreate(
                ['course_id' => $course->id, 'session_number' => $s['session_number']],
                [
                    'title'          => $s['title'] ?? null,
                    'title_ar'       => $s['title_ar'] ?? null,
                    'description'    => $s['description'] ?? null,
                    'description_ar' => $s['description_ar'] ?? null,
                    'duration_hours' => $s['duration_hours'] ?? $course->hours_per_session ?? 2,
                ]
            );
        }

        $sessions = $course->sessions()->get();

        return response()->json([
            'success' => true,
            'message' => count($sessions) . ' session(s) saved.',
            'data'    => $sessions,
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/trainer/courses/{courseId}/sessions/{sessionNumber}",
     *     summary="Update a single session description",
     *     description="Update one session by its number. Creates the session if it doesn't exist yet.",
     *     tags={"Course Sessions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="courseId",      in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="sessionNumber", in="path", required=true, @OA\Schema(type="integer", example=3)),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="title",          type="string", example="Cornering basics"),
     *             @OA\Property(property="title_ar",       type="string", example="أساسيات الانعطاف"),
     *             @OA\Property(property="description",    type="string", example="Entry point, apex and exit. Finding the ideal line."),
     *             @OA\Property(property="description_ar", type="string", example="نقطة الدخول والذروة والخروج. إيجاد المسار المثالي."),
     *             @OA\Property(property="duration_hours", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Session updated"),
     *     @OA\Response(response=404, description="Course not found or not yours")
     * )
     */
    public function update(Request $request, int $courseId, int $sessionNumber)
    {
        $course = $this->trainerCourse($courseId);

        $session = TrainerCourseSession::firstOrNew([
            'course_id'      => $course->id,
            'session_number' => $sessionNumber,
        ]);

        $validated = $request->validate([
            'title'          => 'nullable|string|max:255',
            'title_ar'       => 'nullable|string|max:255',
            'description'    => 'nullable|string|max:2000',
            'description_ar' => 'nullable|string|max:2000',
            'duration_hours' => 'nullable|integer|min:1|max:8',
        ]);

        $session->fill($validated)->save();

        return response()->json(['success' => true, 'data' => $session]);
    }

    /**
     * @OA\Get(
     *     path="/api/trainers/{id}/courses/{courseId}/sessions",
     *     summary="Get course session descriptions (public)",
     *     description="Returns session-by-session breakdown of a published course. Shown to the client on the course detail page before booking.",
     *     tags={"Course Sessions"},
     *     @OA\Parameter(name="id",       in="path", required=true, @OA\Schema(type="integer", example=1), description="Trainer ID"),
     *     @OA\Parameter(name="courseId", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Sessions retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="course_id",      type="integer", example=1),
     *                 @OA\Property(property="title",          type="string",  example="Beginner Circuit Training"),
     *                 @OA\Property(property="total_sessions", type="integer", example=5),
     *                 @OA\Property(property="sessions", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="session_number",  type="integer", example=1),
     *                         @OA\Property(property="title",           type="string",  example="Introduction"),
     *                         @OA\Property(property="title_ar",        type="string",  example="مقدمة"),
     *                         @OA\Property(property="description",     type="string",  example="Safety gear, bike posture and basic throttle control."),
     *                         @OA\Property(property="description_ar",  type="string",  nullable=true),
     *                         @OA\Property(property="duration_hours",  type="integer", example=2)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Course not found or not published")
     * )
     */
    public function publicIndex(int $trainerId, int $courseId)
    {
        $course = TrainerCourse::where('id', $courseId)
            ->where('trainer_id', $trainerId)
            ->where('status', 'published')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => [
                'course_id'      => $course->id,
                'title'          => $course->title,
                'total_sessions' => $course->total_sessions,
                'sessions'       => $course->sessions,
            ],
        ]);
    }
}
