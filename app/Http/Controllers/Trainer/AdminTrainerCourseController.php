<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\TrainerCourse;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Admin - Trainer Courses",
 *     description="Admin view and moderation of trainer courses"
 * )
 */
class AdminTrainerCourseController extends Controller
{
    protected NotificationService $notifications;

    public function __construct(NotificationService $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * @OA\Get(
     *     path="/api/admin/trainer-courses",
     *     summary="List all trainer courses (Admin)",
     *     description="Returns all courses across all trainers with optional filters. Admin only.",
     *     operationId="adminListTrainerCourses",
     *     tags={"Admin - Trainer Courses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="trainer_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status",     in="query", required=false, @OA\Schema(type="string", enum={"draft","published","archived"})),
     *     @OA\Parameter(name="level_id",   in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="price_type", in="query", required=false, @OA\Schema(type="string", enum={"per_hour","total"})),
     *     @OA\Parameter(name="search",     in="query", required=false, @OA\Schema(type="string", example="circuit")),
     *     @OA\Parameter(name="per_page",   in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(
     *         response=200,
     *         description="Courses retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data",    type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = TrainerCourse::with([
            'trainer:id,name,name_ar,status',
            'level:id,name_en,name_ar',
            'location:id,location_name,city_id',
            'equipment',
            'trainingBikes.garage.brand', 'trainingBikes.garage.model', 'trainingBikes.garage.year',
        ]);

        if ($request->filled('trainer_id')) { $query->where('trainer_id', $request->trainer_id); }
        if ($request->filled('status'))     { $query->where('status', $request->status); }
        if ($request->filled('level_id'))   { $query->where('level_id', $request->level_id); }
        if ($request->filled('price_type')) { $query->where('price_type', $request->price_type); }
        if ($request->filled('search'))     { $query->where('title', 'LIKE', '%' . $request->search . '%'); }

        return response()->json([
            'success' => true,
            'data'    => $query->latest()->paginate($request->get('per_page', 20)),
            'message' => 'Courses retrieved successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/trainer-courses/{id}",
     *     summary="Get a single trainer course (Admin)",
     *     operationId="adminShowTrainerCourse",
     *     tags={"Admin - Trainer Courses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Course retrieved"),
     *     @OA\Response(response=404, description="Course not found")
     * )
     */
    public function show(int $id)
    {
        $course = TrainerCourse::with(['trainer', 'level', 'location.city', 'equipment', 'trainingBikes.garage.brand', 'trainingBikes.garage.model', 'trainingBikes.garage.year', 'sessions'])->find($id);
        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $course, 'message' => 'Course retrieved successfully']);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/trainer-courses/{id}",
     *     summary="Delete a trainer course (Admin)",
     *     description="Admin can delete any course regardless of status.",
     *     operationId="adminDeleteTrainerCourse",
     *     tags={"Admin - Trainer Courses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Course deleted"),
     *     @OA\Response(response=404, description="Course not found")
     * )
     */
    public function destroy(int $id)
    {
        $course = TrainerCourse::find($id);
        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course not found'], 404);
        }

        $course->delete();

        return response()->json(['success' => true, 'message' => 'Course deleted successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/trainer-courses/{id}/set-draft",
     *     summary="Force a course to draft (Admin)",
     *     description="Admin moves a published course back to draft, requiring a reason. The trainer is notified with the reason so they know why their course was taken down.",
     *     operationId="adminSetTrainerCourseDraft",
     *     tags={"Admin - Trainer Courses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(required={"reason"}, @OA\Property(property="reason", type="string", example="Course pricing does not match approved level rates"))
     *     ),
     *     @OA\Response(response=200, description="Course set to draft"),
     *     @OA\Response(response=400, description="Course is already a draft"),
     *     @OA\Response(response=404, description="Course not found"),
     *     @OA\Response(response=422, description="Validation error — reason is required")
     * )
     */
    public function setDraft(Request $request, int $id)
    {
        $course = TrainerCourse::with('trainer.user')->find($id);
        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course not found'], 404);
        }

        if ($course->status === 'draft') {
            return response()->json(['success' => false, 'message' => 'Course is already a draft'], 400);
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $course->update([
            'status'       => 'draft',
            'draft_reason' => $request->reason,
        ]);

        try {
            if ($course->trainer?->user) {
                $this->notifications->notifyTrainerCourseSetToDraft($course->trainer->user, $course, $request->reason);
            }
        } catch (\Exception $e) {
            Log::error('AdminTrainerCourseController@setDraft notify failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'data'    => $course->fresh(),
            'message' => 'Course set to draft and trainer notified',
        ]);
    }
}
