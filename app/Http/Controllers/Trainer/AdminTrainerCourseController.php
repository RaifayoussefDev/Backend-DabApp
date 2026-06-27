<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\TrainerCourse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Admin - Trainer Courses",
 *     description="Admin view and moderation of trainer courses"
 * )
 */
class AdminTrainerCourseController extends Controller
{
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
        ]);

        if ($request->filled('trainer_id')) { $query->where('trainer_id', $request->trainer_id); }
        if ($request->filled('status'))     { $query->where('status', $request->status); }
        if ($request->filled('level_id'))   { $query->where('level_id', $request->level_id); }

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
        $course = TrainerCourse::with(['trainer', 'level', 'location.city'])->find($id);
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
}
