<?php

namespace App\Http\Controllers;

use App\Models\MotorcycleModel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin Motorcycle Models",
 *     description="Admin API Endpoints for managing motorcycle models"
 * )
 */
class AdminMotorcycleModelController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/motorcycle-models",
     *     summary="Get all motorcycle models (Admin)",
     *     tags={"Admin Motorcycle Models"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by model name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="brand_id",
     *         in="query",
     *         description="Filter by brand",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="type_id",
     *         in="query",
     *         description="Filter by type",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page. Leave empty to get all results.",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = MotorcycleModel::with(['brand', 'type']);

        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'LIKE', "%{$request->search}%");
        }

        if ($request->has('brand_id') && !empty($request->brand_id)) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->has('type_id') && !empty($request->type_id)) {
            $query->where('type_id', $request->type_id);
        }

        $perPage = $request->input('per_page');

        if ($perPage === null || $perPage === '') {
            $models = $query->get();
        } else {
            $models = $query->paginate((int) $perPage);
        }

        return response()->json([
            'success' => true,
            'message' => 'Motorcycle models retrieved successfully.',
            'data' => $models,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/motorcycle-models",
     *     summary="Create a new motorcycle model (Admin)",
     *     tags={"Admin Motorcycle Models"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"brand_id", "name", "type_id"},
     *             example={
     *                 "brand_id": 1,
     *                 "name": "Panigale V4",
     *                 "type_id": 2
     *             },
     *             @OA\Property(property="brand_id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="type_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Motorcycle model created"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'brand_id' => 'required|exists:motorcycle_brands,id',
            'name' => 'required|string|max:255|unique:motorcycle_models,name',
            'type_id' => 'required|exists:motorcycle_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $model = MotorcycleModel::create($validator->validated());
        $model->load(['brand', 'type']);

        return response()->json([
            'success' => true,
            'message' => 'Motorcycle model created successfully.',
            'data' => $model,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/motorcycle-models/{id}",
     *     summary="Get a specific motorcycle model (Admin)",
     *     tags={"Admin Motorcycle Models"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Model not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $model = MotorcycleModel::with(['brand', 'type'])->find($id);

        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => 'Motorcycle model not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $model,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/motorcycle-models/{id}",
     *     summary="Update a motorcycle model (Admin)",
     *     tags={"Admin Motorcycle Models"},
     *     security={{"bearerAuth":{}}},
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
     *             example={
     *                 "brand_id": 1,
     *                 "name": "Panigale V4 S",
     *                 "type_id": 2
     *             },
     *             @OA\Property(property="brand_id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="type_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Motorcycle model updated"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $model = MotorcycleModel::find($id);

        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => 'Motorcycle model not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'brand_id' => 'required|exists:motorcycle_brands,id',
            'name' => 'required|string|max:255|unique:motorcycle_models,name,' . $id,
            'type_id' => 'required|exists:motorcycle_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $model->update($validator->validated());
        $model->load(['brand', 'type']);

        return response()->json([
            'success' => true,
            'message' => 'Motorcycle model updated successfully.',
            'data' => $model,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/motorcycle-models/{id}",
     *     summary="Delete a motorcycle model (Admin)",
     *     tags={"Admin Motorcycle Models"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Motorcycle model deleted successfully"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $model = MotorcycleModel::find($id);

        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => 'Motorcycle model not found.',
            ], 404);
        }

        $model->delete();

        return response()->json([
            'success' => true,
            'message' => 'Motorcycle model deleted successfully.',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/motorcycle-models/stats/overview",
     *     summary="Get motorcycle models statistics (Admin)",
     *     tags={"Admin Motorcycle Models"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_models' => MotorcycleModel::count(),
            'by_brand' => MotorcycleModel::select('brand_id', \DB::raw('count(*) as count'))
                ->with('brand:id,name')
                ->groupBy('brand_id')
                ->orderBy('count', 'desc')
                ->take(5)
                ->get(),
            'by_type' => MotorcycleModel::select('type_id', \DB::raw('count(*) as count'))
                ->with('type:id,name')
                ->groupBy('type_id')
                ->orderBy('count', 'desc')
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
