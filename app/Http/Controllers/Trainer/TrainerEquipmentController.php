<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\EquipmentType;
use App\Models\Trainer;
use App\Models\TrainerEquipment;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Trainer Equipment",
 *     description="Manage equipment available per trainer (helmet, jacket, knee protector, ...)"
 * )
 */
class TrainerEquipmentController extends Controller
{
    private function trainer(): Trainer
    {
        $user = JWTAuth::parseToken()->authenticate();
        return Trainer::where('user_id', $user->id)->firstOrFail();
    }

    /**
     * @OA\Get(
     *     path="/api/trainer/equipment",
     *     summary="List my equipment",
     *     tags={"Trainer Equipment"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Equipment list",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id",           type="integer", example=1),
     *                     @OA\Property(property="name",         type="string",  example="Helmet"),
     *                     @OA\Property(property="name_ar",      type="string",  example="خوذة"),
     *                     @OA\Property(property="icon",         type="string",  example="helmet"),
     *                     @OA\Property(property="is_available", type="boolean", example=true),
     *                     @OA\Property(property="sort_order",   type="integer", example=0)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $trainer = $this->trainer();
        return response()->json([
            'success' => true,
            'data'    => $trainer->equipment,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/trainer/equipment",
     *     summary="Add equipment items",
     *     description="Add one or multiple equipment items at once.",
     *     tags={"Trainer Equipment"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"items"},
     *             @OA\Property(property="items", type="array",
     *                 @OA\Items(
     *                     required={"name"},
     *                     @OA\Property(property="name",         type="string",  example="Helmet"),
     *                     @OA\Property(property="name_ar",      type="string",  example="خوذة"),
     *                     @OA\Property(property="icon",         type="string",  example="helmet",
     *                         description="Icon identifier string used by the frontend (e.g. icon name, emoji, or SVG key)"),
     *                     @OA\Property(property="is_available", type="boolean", example=true),
     *                     @OA\Property(property="sort_order",   type="integer", example=0)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Equipment added"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(Request $request)
    {
        $trainer = $this->trainer();

        $request->validate([
            'equipment_type_ids'   => 'required|array|min:1',
            'equipment_type_ids.*' => 'integer|exists:equipment_types,id',
        ]);

        $created = [];
        foreach ($request->equipment_type_ids as $typeId) {
            // Skip if already selected
            if (TrainerEquipment::where('trainer_id', $trainer->id)->where('equipment_type_id', $typeId)->exists()) {
                continue;
            }

            $type = EquipmentType::find($typeId);
            $created[] = TrainerEquipment::create([
                'trainer_id'        => $trainer->id,
                'equipment_type_id' => $type->id,
                'name'              => $type->name,
                'name_ar'           => $type->name_ar,
                'icon'              => $type->icon,
                'is_available'      => true,
                'sort_order'        => $type->sort_order,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' equipment item(s) selected.',
            'data'    => $created,
        ], 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/trainer/equipment/{id}",
     *     summary="Update an equipment item",
     *     tags={"Trainer Equipment"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name",         type="string",  example="Full-face Helmet"),
     *             @OA\Property(property="name_ar",      type="string",  example="خوذة كاملة"),
     *             @OA\Property(property="icon",         type="string",  example="helmet-full"),
     *             @OA\Property(property="is_available", type="boolean", example=false),
     *             @OA\Property(property="sort_order",   type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=404, description="Not found or not yours")
     * )
     */
    public function update(Request $request, int $id)
    {
        $trainer = $this->trainer();
        $item    = TrainerEquipment::where('id', $id)->where('trainer_id', $trainer->id)->firstOrFail();

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:100',
            'name_ar'      => 'nullable|string|max:100',
            'icon'         => 'nullable|string|max:100',
            'is_available' => 'sometimes|boolean',
            'sort_order'   => 'sometimes|integer|min:0',
        ]);

        $item->update($validated);

        return response()->json(['success' => true, 'data' => $item]);
    }

    /**
     * @OA\Delete(
     *     path="/api/trainer/equipment/{id}",
     *     summary="Delete an equipment item",
     *     tags={"Trainer Equipment"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=404, description="Not found or not yours")
     * )
     */
    public function destroy(int $id)
    {
        $trainer = $this->trainer();
        TrainerEquipment::where('id', $id)->where('trainer_id', $trainer->id)->firstOrFail()->delete();

        return response()->json(['success' => true, 'message' => 'Equipment item deleted.']);
    }

    /**
     * @OA\Get(
     *     path="/api/trainers/{id}/equipment",
     *     summary="Get trainer equipment (public)",
     *     description="Returns available equipment for a trainer. Only items with is_available=true.",
     *     tags={"Trainer Equipment"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Equipment list",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id",         type="integer", example=1),
     *                     @OA\Property(property="name",       type="string",  example="Helmet"),
     *                     @OA\Property(property="name_ar",    type="string",  example="خوذة"),
     *                     @OA\Property(property="icon",       type="string",  example="helmet"),
     *                     @OA\Property(property="sort_order", type="integer", example=0)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Trainer not found")
     * )
     */
    public function publicIndex(int $id)
    {
        $trainer = Trainer::approved()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $trainer->equipment()->where('is_available', true)->get(),
        ]);
    }
}
