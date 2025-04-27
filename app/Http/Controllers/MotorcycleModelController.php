<?php

namespace App\Http\Controllers;

use App\Models\MotorcycleModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(name="Motorcycle Models")
 */
class MotorcycleModelController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/motorcycle-models",
     *     tags={"Motorcycle Models"},
     *     summary="Get all motorcycle models",
     *     @OA\Response(response=200, description="List of motorcycle models")
     * )
     */
    public function index()
    {
        return response()->json(MotorcycleModel::all());
    }

    /**
     * @OA\Post(
     *     path="/api/motorcycle-models",
     *     tags={"Motorcycle Models"},
     *     summary="Create a new motorcycle model",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"brand_id", "name", "type_id"},
     *             @OA\Property(property="brand_id", type="integer"),
     *             @OA\Property(property="name", type="string", example="CBR600RR"),
     *             @OA\Property(property="type_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Motorcycle model created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */ 
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'brand_id' => 'required|exists:motorcycle_brands,id',
            'name' => 'required|string|unique:motorcycle_models,name',
            'type_id' => 'required|exists:motorcycle_types,id',
        ], [
            'brand_id.required' => 'The brand ID is required.',
            'brand_id.exists' => 'The selected brand does not exist.',
            'name.required' => 'The name is required.',
            'name.string' => 'The name must be a string.',
            'name.unique' => 'The name has already been taken.',
            'type_id.required' => 'The type ID is required.',
            'type_id.exists' => 'The selected type does not exist.',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }
    
        $model = MotorcycleModel::create($validator->validated());
    
        return response()->json($model, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/motorcycle-models/{id}",
     *     tags={"Motorcycle Models"},
     *     summary="Get a motorcycle model by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Motorcycle model data"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function show($id)
    {
        $model = MotorcycleModel::find($id);
        if (!$model) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json($model);
    }

    /**
     * @OA\Put(
     *     path="/api/motorcycle-models/{id}",
     *     tags={"Motorcycle Models"},
     *     summary="Update a motorcycle model",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"brand_id", "name", "type_id"},
     *             @OA\Property(property="brand_id", type="integer"),
     *             @OA\Property(property="name", type="string", example="MT-07"),
     *             @OA\Property(property="type_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Motorcycle model updated"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function update(Request $request, $id)
    {
        $model = MotorcycleModel::find($id);
        if (!$model) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $validated = $request->validate([
            'brand_id' => 'required|exists:motorcycle_brands,id',
            'name' => 'required|string|unique:motorcycle_models,name,' . $id,
            'type_id' => 'required|exists:motorcycle_types,id',
        ]);

        $model->update($validated);

        return response()->json($model);
    }

    /**
     * @OA\Delete(
     *     path="/api/motorcycle-models/{id}",
     *     tags={"Motorcycle Models"},
     *     summary="Delete a motorcycle model",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function destroy($id)
    {
        $model = MotorcycleModel::find($id);
        if (!$model) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $model->delete();

        return response()->json(null, 204);
    }
}
