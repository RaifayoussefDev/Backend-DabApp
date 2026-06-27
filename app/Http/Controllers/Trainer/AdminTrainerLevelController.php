<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Models\TrainerLevel;
use App\Models\TrainerLevelApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Admin - Trainer Levels",
 *     description="Admin management of dynamic training levels and per-trainer level approvals"
 * )
 */
class AdminTrainerLevelController extends Controller
{
    // ---------------------------------------------------------------
    // PUBLIC — list active levels (used by mobile when registering)
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/trainer-levels",
     *     summary="List training levels",
     *     description="Returns all active training levels. Used by the mobile app when a trainer registers to propose level prices.",
     *     operationId="listTrainerLevels",
     *     tags={"Admin - Trainer Levels"},
     *     @OA\Response(
     *         response=200,
     *         description="Levels retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id",                     type="integer", example=1),
     *                     @OA\Property(property="name_en",                type="string",  example="Beginner"),
     *                     @OA\Property(property="name_ar",                type="string",  example="مبتدئ"),
     *                     @OA\Property(property="slug",                   type="string",  example="beginner"),
     *                     @OA\Property(property="description",            type="string",  example="For first-time riders"),
     *                     @OA\Property(property="required_certifications", type="array",  @OA\Items(type="string")),
     *                     @OA\Property(property="sort_order",             type="integer", example=1)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function publicIndex()
    {
        $levels = TrainerLevel::active()->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data'    => $levels,
            'message' => 'Training levels retrieved successfully',
        ]);
    }

    // ---------------------------------------------------------------
    // ADMIN — CRUD for levels
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/admin/trainer-levels",
     *     summary="List all training levels (Admin)",
     *     description="Returns all training levels including inactive ones. Admin only.",
     *     operationId="adminListTrainerLevels",
     *     tags={"Admin - Trainer Levels"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Levels retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id",                      type="integer", example=1),
     *                     @OA\Property(property="name_en",                 type="string",  example="Beginner"),
     *                     @OA\Property(property="name_ar",                 type="string",  example="مبتدئ"),
     *                     @OA\Property(property="slug",                    type="string",  example="beginner"),
     *                     @OA\Property(property="description",             type="string"),
     *                     @OA\Property(property="required_certifications", type="array",   @OA\Items(type="string")),
     *                     @OA\Property(property="sort_order",              type="integer", example=1),
     *                     @OA\Property(property="is_active",               type="boolean", example=true),
     *                     @OA\Property(property="trainers_count",          type="integer", example=12)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $levels = TrainerLevel::withCount(['approvals as trainers_count' => fn ($q) => $q->where('status', 'approved')])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $levels,
            'message' => 'Training levels retrieved successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/trainer-levels",
     *     summary="Create a training level (Admin)",
     *     description="Create a new training level. The slug is auto-generated from name_en if not provided.",
     *     operationId="adminCreateTrainerLevel",
     *     tags={"Admin - Trainer Levels"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name_en"},
     *             @OA\Property(property="name_en",                 type="string",  example="Beginner"),
     *             @OA\Property(property="name_ar",                 type="string",  example="مبتدئ"),
     *             @OA\Property(property="slug",                    type="string",  example="beginner", description="Auto-generated if omitted"),
     *             @OA\Property(property="description",             type="string",  example="For first-time riders"),
     *             @OA\Property(property="required_certifications", type="array",   @OA\Items(type="string"), example={"Basic riding license"}),
     *             @OA\Property(property="sort_order",              type="integer", example=1),
     *             @OA\Property(property="is_active",               type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Level created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data",    type="object"),
     *             @OA\Property(property="message", type="string",  example="Training level created successfully")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $admin = JWTAuth::parseToken()->authenticate();

        $validated = $request->validate([
            'name_en'                 => 'required|string|max:100',
            'name_ar'                 => 'nullable|string|max:100',
            'slug'                    => 'nullable|string|max:100|unique:trainer_levels,slug',
            'description'             => 'nullable|string|max:1000',
            'required_certifications' => 'nullable|array',
            'required_certifications.*' => 'string|max:200',
            'sort_order'              => 'nullable|integer|min:0',
            'is_active'               => 'nullable|boolean',
        ]);

        $validated['slug']       = $validated['slug'] ?? Str::slug($validated['name_en']);
        $validated['created_by'] = $admin->id;

        $level = TrainerLevel::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $level,
            'message' => 'Training level created successfully',
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/trainer-levels/{id}",
     *     summary="Update a training level (Admin)",
     *     operationId="adminUpdateTrainerLevel",
     *     tags={"Admin - Trainer Levels"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name_en",                 type="string"),
     *             @OA\Property(property="name_ar",                 type="string"),
     *             @OA\Property(property="description",             type="string"),
     *             @OA\Property(property="required_certifications", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="sort_order",              type="integer"),
     *             @OA\Property(property="is_active",               type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Level updated"),
     *     @OA\Response(response=404, description="Level not found")
     * )
     */
    public function update(Request $request, int $id)
    {
        $level = TrainerLevel::find($id);
        if (!$level) {
            return response()->json(['success' => false, 'message' => 'Level not found'], 404);
        }

        $validated = $request->validate([
            'name_en'                 => 'nullable|string|max:100',
            'name_ar'                 => 'nullable|string|max:100',
            'description'             => 'nullable|string|max:1000',
            'required_certifications' => 'nullable|array',
            'required_certifications.*' => 'string|max:200',
            'sort_order'              => 'nullable|integer|min:0',
            'is_active'               => 'nullable|boolean',
        ]);

        $level->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json([
            'success' => true,
            'data'    => $level->fresh(),
            'message' => 'Training level updated successfully',
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/trainer-levels/{id}",
     *     summary="Delete a training level (Admin)",
     *     description="Soft-deletes by setting is_active=false if trainers are assigned. Hard-deletes if no approvals exist.",
     *     operationId="adminDeleteTrainerLevel",
     *     tags={"Admin - Trainer Levels"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Level deleted or deactivated"),
     *     @OA\Response(response=404, description="Level not found")
     * )
     */
    public function destroy(int $id)
    {
        $level = TrainerLevel::find($id);
        if (!$level) {
            return response()->json(['success' => false, 'message' => 'Level not found'], 404);
        }

        if ($level->approvals()->exists()) {
            $level->update(['is_active' => false]);
            return response()->json(['success' => true, 'message' => 'Level deactivated (trainers still linked)']);
        }

        $level->delete();
        return response()->json(['success' => true, 'message' => 'Training level deleted successfully']);
    }

    // ---------------------------------------------------------------
    // ADMIN — Approve / manage trainer level approvals
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/admin/trainers/{id}/approve-levels",
     *     summary="Approve training levels for a trainer (Admin)",
     *     description="Approves specific levels for a trainer, setting the final price per hour for each. Also sets the trainer status to 'approved' if not already. You can approve one, two, or all available levels.",
     *     operationId="adminApproveTrainerLevels",
     *     tags={"Admin - Trainer Levels"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1), description="Trainer ID"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"levels"},
     *             @OA\Property(
     *                 property="levels",
     *                 type="array",
     *                 description="Array of levels to approve with final prices",
     *                 @OA\Items(type="object",
     *                     required={"level_id","approved_price"},
     *                     @OA\Property(property="level_id",       type="integer", example=1),
     *                     @OA\Property(property="approved_price", type="number",  example=150.00),
     *                     @OA\Property(property="notes",          type="string",  example="Good basic certifications")
     *                 )
     *             ),
     *             @OA\Property(property="approve_trainer", type="boolean", example=true, description="Also set trainer status to approved (default true)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Levels approved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="3 level(s) approved for this trainer"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="approved_levels", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="level_id",       type="integer"),
     *                     @OA\Property(property="level_name",     type="string"),
     *                     @OA\Property(property="approved_price", type="number")
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Trainer not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function approveLevels(Request $request, int $id)
    {
        $admin   = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::find($id);

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $validated = $request->validate([
            'levels'                    => 'required|array|min:1',
            'levels.*.level_id'         => 'required|integer|exists:trainer_levels,id',
            'levels.*.approved_price'   => 'required|numeric|min:0',
            'levels.*.notes'            => 'nullable|string|max:500',
            'approve_trainer'           => 'nullable|boolean',
        ]);

        $approvedLevels = [];
        foreach ($validated['levels'] as $item) {
            $approval = TrainerLevelApproval::updateOrCreate(
                ['trainer_id' => $trainer->id, 'level_id' => $item['level_id']],
                [
                    'approved_price' => $item['approved_price'],
                    'status'         => 'approved',
                    'notes'          => $item['notes'] ?? null,
                    'approved_by'    => $admin->id,
                    'approved_at'    => now(),
                ]
            );

            $level = $approval->level;
            $approvedLevels[] = [
                'level_id'       => $level->id,
                'level_name'     => $level->name_en,
                'approved_price' => $approval->approved_price,
            ];
        }

        // Also flip trainer status to approved unless caller opts out
        if ($request->boolean('approve_trainer', true) && $trainer->status !== 'approved') {
            $trainer->update(['status' => 'approved', 'is_available' => true]);
        }

        return response()->json([
            'success' => true,
            'data'    => ['approved_levels' => $approvedLevels],
            'message' => count($approvedLevels) . ' level(s) approved for this trainer',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/trainers/{id}/reject-level",
     *     summary="Reject a specific level for a trainer (Admin)",
     *     description="Rejects a single proposed level without affecting the trainer's overall status or other approved levels.",
     *     operationId="adminRejectTrainerLevel",
     *     tags={"Admin - Trainer Levels"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1), description="Trainer ID"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"level_id"},
     *             @OA\Property(property="level_id", type="integer", example=3),
     *             @OA\Property(property="notes",    type="string",  example="Advanced certification not verified")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Level rejected"),
     *     @OA\Response(response=404, description="Trainer or level not found")
     * )
     */
    public function rejectLevel(Request $request, int $id)
    {
        $admin   = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::find($id);

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $validated = $request->validate([
            'level_id' => 'required|integer|exists:trainer_levels,id',
            'notes'    => 'nullable|string|max:500',
        ]);

        TrainerLevelApproval::updateOrCreate(
            ['trainer_id' => $trainer->id, 'level_id' => $validated['level_id']],
            [
                'status'      => 'rejected',
                'notes'       => $validated['notes'] ?? null,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]
        );

        return response()->json(['success' => true, 'message' => 'Level rejected for this trainer']);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/trainers/{id}/levels",
     *     summary="Get trainer level approvals (Admin)",
     *     description="Returns all level proposals and their approval status for a specific trainer.",
     *     operationId="adminGetTrainerLevels",
     *     tags={"Admin - Trainer Levels"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Trainer levels retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="level_id",       type="integer", example=1),
     *                     @OA\Property(property="level_name",     type="string",  example="Beginner"),
     *                     @OA\Property(property="proposed_price", type="number",  example=150.00),
     *                     @OA\Property(property="approved_price", type="number",  example=180.00),
     *                     @OA\Property(property="status",         type="string",  example="approved")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Trainer not found")
     * )
     */
    public function trainerLevels(int $id)
    {
        $trainer = Trainer::find($id);
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $approvals = TrainerLevelApproval::with('level')
            ->where('trainer_id', $trainer->id)
            ->get()
            ->map(fn ($a) => [
                'level_id'       => $a->level_id,
                'level_name'     => $a->level?->name_en,
                'level_name_ar'  => $a->level?->name_ar,
                'proposed_price' => $a->proposed_price,
                'approved_price' => $a->approved_price,
                'status'         => $a->status,
                'notes'          => $a->notes,
                'approved_at'    => $a->approved_at,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $approvals,
            'message' => 'Trainer level approvals retrieved',
        ]);
    }
}
