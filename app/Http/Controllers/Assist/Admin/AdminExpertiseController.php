<?php

namespace App\Http\Controllers\Assist\Admin;

use App\Http\Controllers\Assist\AssistBaseController;
use App\Http\Requests\Assist\CreateExpertiseTypeRequest;
use App\Http\Requests\Assist\UpdateExpertiseTypeRequest;
use App\Models\Assist\ExpertiseType;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Assist - Admin Expertise",
 *     description="Admin CRUD for expertise types"
 * )
 */
class AdminExpertiseController extends AssistBaseController
{
    /**
     * @OA\Get(
     *     path="/api/assist/admin/expertise-types",
     *     summary="List all expertise types",
     *     tags={"Assist - Admin Expertise"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="All expertise types",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(ref="#/components/schemas/ExpertiseType")
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        return $this->success(ExpertiseType::all());
    }

    /**
     * @OA\Post(
     *     path="/api/assist/admin/expertise-types",
     *     summary="Create a new expertise type",
     *     tags={"Assist - Admin Expertise"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","icon"},
     *             @OA\Property(property="name", type="string", example="battery_jump",
     *                 description="Unique slug-like name for the expertise"),
     *             @OA\Property(property="icon", type="string", example="battery_charging_full",
     *                 description="Material icon name or asset key")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Expertise type created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expertise type created."),
     *             @OA\Property(property="data", ref="#/components/schemas/ExpertiseType")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error or name already exists")
     * )
     */
    public function store(CreateExpertiseTypeRequest $request): JsonResponse
    {
        $type = ExpertiseType::create($request->validated());

        return $this->success($type, 'Expertise type created.', 201);
    }

    /**
     * @OA\Put(
     *     path="/api/assist/admin/expertise-types/{id}",
     *     summary="Update an expertise type",
     *     tags={"Assist - Admin Expertise"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="ExpertiseType UUID",
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440001")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="battery_jump"),
     *             @OA\Property(property="icon", type="string", example="battery_charging_full")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/ExpertiseType")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(UpdateExpertiseTypeRequest $request, string $id): JsonResponse
    {
        $type = ExpertiseType::find($id);

        if (!$type) {
            return $this->error('Expertise type not found.', 404);
        }

        $type->update($request->validated());

        return $this->success($type, 'Expertise type updated.');
    }

    /**
     * @OA\Delete(
     *     path="/api/assist/admin/expertise-types/{id}",
     *     summary="Delete an expertise type",
     *     tags={"Assist - Admin Expertise"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440001")
     *     ),
     *     @OA\Response(response=200, description="Deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expertise type deleted.")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $type = ExpertiseType::find($id);

        if (!$type) {
            return $this->error('Expertise type not found.', 404);
        }

        $type->delete();

        return $this->success([], 'Expertise type deleted.');
    }
}
