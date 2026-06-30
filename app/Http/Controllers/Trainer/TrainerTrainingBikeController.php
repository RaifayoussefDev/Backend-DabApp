<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\MyGarage;
use App\Models\Trainer;
use App\Models\TrainerTrainingBike;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Trainer - Training Bikes",
 *     description="Trainer selects bikes from My Garage to use for training sessions"
 * )
 */
class TrainerTrainingBikeController extends Controller
{
    private function resolveTrainer(): ?Trainer
    {
        return Trainer::where('user_id', JWTAuth::parseToken()->authenticate()->id)->first();
    }

    /**
     * @OA\Get(
     *     path="/api/trainer/training-bikes",
     *     summary="List my training bikes",
     *     description="Returns all garage bikes the trainer has selected as training bikes.",
     *     operationId="listTrainingBikes",
     *     tags={"Trainer - Training Bikes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Training bikes retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id",          type="integer", example=1),
     *                     @OA\Property(property="garage_id",   type="integer", example=3),
     *                     @OA\Property(property="is_primary",  type="boolean", example=true),
     *                     @OA\Property(property="is_training_ready", type="boolean", example=false,
     *                         description="True if plate_number, insurance_expiry and insurance_covers_training are set"
     *                     ),
     *                     @OA\Property(property="garage", type="object",
     *                         @OA\Property(property="id",                        type="integer"),
     *                         @OA\Property(property="plate_number",              type="string",  nullable=true),
     *                         @OA\Property(property="insurance_expiry",          type="string",  format="date", nullable=true),
     *                         @OA\Property(property="insurance_covers_training", type="boolean", nullable=true),
     *                         @OA\Property(property="brand",  type="object"),
     *                         @OA\Property(property="model",  type="object"),
     *                         @OA\Property(property="year",   type="object")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="No trainer profile found")
     * )
     */
    public function index()
    {
        $trainer = $this->resolveTrainer();
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $bikes = TrainerTrainingBike::with(['garage.brand', 'garage.model', 'garage.year'])
            ->where('trainer_id', $trainer->id)
            ->get()
            ->map(fn ($b) => array_merge($b->toArray(), [
                'is_training_ready' => $b->garage
                    && $b->garage->plate_number
                    && $b->garage->insurance_expiry
                    && $b->garage->insurance_covers_training,
            ]));

        return response()->json([
            'success' => true,
            'data'    => $bikes,
            'message' => 'Training bikes retrieved successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/trainer/training-bikes",
     *     summary="Add a training bike",
     *     description="Select a bike from My Garage as a training bike. The bike must belong to the authenticated user. You can mark it as primary.",
     *     operationId="addTrainingBike",
     *     tags={"Trainer - Training Bikes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"garage_id"},
     *             @OA\Property(property="garage_id",  type="integer", example=3,    description="ID from My Garage"),
     *             @OA\Property(property="is_primary", type="boolean", example=true, description="Mark as primary training bike")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Training bike added",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data",    type="object"),
     *             @OA\Property(property="message", type="string", example="Training bike added successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, description="No trainer profile found or bike does not belong to you"),
     *     @OA\Response(response=409, description="Bike already added as training bike"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = $this->resolveTrainer();

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $validated = $request->validate([
            'garage_id'  => 'required|integer|exists:my_garage,id',
            'is_primary' => 'nullable|boolean',
        ]);

        // Verify ownership
        $garage = MyGarage::where('id', $validated['garage_id'])->where('user_id', $user->id)->first();
        if (!$garage) {
            return response()->json(['success' => false, 'message' => 'Bike not found in your garage'], 403);
        }

        if (TrainerTrainingBike::where('trainer_id', $trainer->id)->where('garage_id', $garage->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'This bike is already added as a training bike'], 409);
        }

        // Unset other primary bikes if this is primary
        if ($request->boolean('is_primary')) {
            TrainerTrainingBike::where('trainer_id', $trainer->id)->update(['is_primary' => false]);
        }

        $bike = TrainerTrainingBike::create([
            'trainer_id' => $trainer->id,
            'garage_id'  => $garage->id,
            'is_primary' => $request->boolean('is_primary', false),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $bike->load(['garage.brand', 'garage.model', 'garage.year']),
            'message' => 'Training bike added successfully',
        ], 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/trainer/training-bikes/{garageId}",
     *     summary="Remove a training bike",
     *     description="Remove a garage bike from the trainer's training bike list.",
     *     operationId="removeTrainingBike",
     *     tags={"Trainer - Training Bikes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="garageId", in="path", required=true, @OA\Schema(type="integer", example=3), description="Garage bike ID"),
     *     @OA\Response(response=200, description="Training bike removed"),
     *     @OA\Response(response=404, description="Training bike not found")
     * )
     */
    public function destroy(int $garageId)
    {
        $trainer = $this->resolveTrainer();
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $bike = TrainerTrainingBike::where('trainer_id', $trainer->id)->where('garage_id', $garageId)->first();
        if (!$bike) {
            return response()->json(['success' => false, 'message' => 'Training bike not found'], 404);
        }

        $bike->delete();

        return response()->json(['success' => true, 'message' => 'Training bike removed successfully']);
    }

    /**
     * @OA\Patch(
     *     path="/api/trainer/training-bikes/{garageId}/set-primary",
     *     summary="Set a training bike as primary",
     *     operationId="setPrimaryTrainingBike",
     *     tags={"Trainer - Training Bikes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="garageId", in="path", required=true, @OA\Schema(type="integer", example=3)),
     *     @OA\Response(response=200, description="Primary bike updated"),
     *     @OA\Response(response=404, description="Training bike not found")
     * )
     */
    public function setPrimary(int $garageId)
    {
        $trainer = $this->resolveTrainer();
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $bike = TrainerTrainingBike::where('trainer_id', $trainer->id)->where('garage_id', $garageId)->first();
        if (!$bike) {
            return response()->json(['success' => false, 'message' => 'Training bike not found'], 404);
        }

        TrainerTrainingBike::where('trainer_id', $trainer->id)->update(['is_primary' => false]);
        $bike->update(['is_primary' => true]);

        return response()->json(['success' => true, 'message' => 'Primary training bike updated']);
    }

    /**
     * @OA\Get(
     *     path="/api/trainers/{id}/training-bikes",
     *     summary="Get trainer training bikes (public)",
     *     description="Returns the bikes a trainer uses for training sessions. Only bikes belonging to an approved trainer.",
     *     operationId="publicTrainingBikes",
     *     tags={"Trainer - Training Bikes"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=4)),
     *     @OA\Response(
     *         response=200,
     *         description="Training bikes list",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="is_primary", type="boolean"),
     *                     @OA\Property(property="brand",      type="string", example="BMW"),
     *                     @OA\Property(property="model",      type="string", example="GS 1250"),
     *                     @OA\Property(property="year",       type="integer", example=2024),
     *                     @OA\Property(property="title",      type="string", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Trainer not found")
     * )
     */
    public function publicIndex(int $trainerId)
    {
        $trainer = Trainer::approved()->find($trainerId);
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'Trainer not found'], 404);
        }

        $bikes = TrainerTrainingBike::with(['garage.brand', 'garage.model', 'garage.year'])
            ->where('trainer_id', $trainer->id)
            ->get()
            ->map(fn ($b) => [
                'is_primary' => $b->is_primary,
                'brand'      => $b->garage?->brand?->name,
                'model'      => $b->garage?->model?->name,
                'year'       => $b->garage?->year?->year,
                'title'      => $b->garage?->title,
            ]);

        return response()->json(['success' => true, 'data' => $bikes]);
    }
}
