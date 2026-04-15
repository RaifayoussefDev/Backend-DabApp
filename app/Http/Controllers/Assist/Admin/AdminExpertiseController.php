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
 *     description="Admin CRUD for expertise types (tire_repair, fuel, mechanical…)"
 * )
 */
class AdminExpertiseController extends AssistBaseController
{
    /**
     * @OA\Get(
     *     path="/api/assist/expertise-types",
     *     summary="List all available expertise types",
     *     description="Public (authenticated) endpoint. Used by seekers to pick expertise when creating a request, and by helpers when setting up their profile.",
     *     tags={"Assist - Admin Expertise"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="All expertise types",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id",   type="integer", example=1),
     *                     @OA\Property(property="name", type="string",  example="tire_repair"),
     *                     @OA\Property(property="icon", type="string",  example="tire_repair")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
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
     *                 description="Unique slug name (snake_case). Available: tire_repair, fuel, mechanical, towing, first_aid, ev_support, battery_jump"),
     *             @OA\Property(property="icon", type="string", example="battery_charging_full",
     *                 description="Material Design icon name")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Expertise type created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expertise type created."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",         type="integer", example=7),
     *                 @OA\Property(property="name",       type="string",  example="battery_jump"),
     *                 @OA\Property(property="icon",       type="string",  example="battery_charging_full"),
     *                 @OA\Property(property="created_at", type="string",  format="date-time", example="2026-04-15T10:00:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string",  format="date-time", example="2026-04-15T10:00:00.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error or name already taken",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="name", type="array",
     *                     @OA\Items(type="string", example="The name has already been taken.")
     *                 )
     *             )
     *         )
     *     )
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
     *         description="ExpertiseType ID",
     *         @OA\Schema(type="integer", example=1)
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
     *             @OA\Property(property="message", type="string", example="Expertise type updated."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",   type="integer", example=1),
     *                 @OA\Property(property="name", type="string",  example="battery_jump"),
     *                 @OA\Property(property="icon", type="string",  example="battery_charging_full")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Expertise type not found")
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
     *         description="ExpertiseType ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expertise type deleted.")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Expertise type not found")
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
