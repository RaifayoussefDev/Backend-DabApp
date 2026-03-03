<?php

namespace App\Http\Controllers;

use App\Models\MotorcycleType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin Motorcycle Types",
 *     description="Admin API Endpoints for managing motorcycle types"
 * )
 */
class AdminMotorcycleTypeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/motorcycle-types",
     *     summary="Get all motorcycle types (Admin)",
     *     tags={"Admin Motorcycle Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by type name",
     *         required=false,
     *         @OA\Schema(type="string")
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
        $query = MotorcycleType::query();

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('name_ar', 'LIKE', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page');

        if ($perPage === null || $perPage === '') {
            $types = $query->get();
        } else {
            $types = $query->paginate((int) $perPage);
        }

        return response()->json([
            'success' => true,
            'message' => 'Motorcycle types retrieved successfully.',
            'data' => $types,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/motorcycle-types",
     *     summary="Create a new motorcycle type (Admin)",
     *     tags={"Admin Motorcycle Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             example={
     *                 "name": "Cruiser",
     *                 "name_ar": "كروزر",
     *                 "icon": "https://api.dabapp.co/storage/icons/cruiser.png"
     *             },
     *             @OA\Property(property="name", type="string", example="Cruiser"),
     *             @OA\Property(property="name_ar", type="string", example="كروزر"),
     *             @OA\Property(property="icon", type="string", example="url-to-icon.png")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Motorcycle type created"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:motorcycle_types',
            'name_ar' => 'nullable|string|max:255',
            'icon' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $type = MotorcycleType::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Motorcycle type created successfully.',
            'data' => $type,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/motorcycle-types/{id}",
     *     summary="Get a specific motorcycle type (Admin)",
     *     tags={"Admin Motorcycle Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Type not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $type = MotorcycleType::find($id);

        if (!$type) {
            return response()->json([
                'success' => false,
                'message' => 'Motorcycle type not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $type,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/motorcycle-types/{id}",
     *     summary="Update a motorcycle type (Admin)",
     *     tags={"Admin Motorcycle Types"},
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
     *             required={"name"},
     *             example={
     *                 "name": "Sport Cruiser",
     *                 "name_ar": "كروزر رياضي"
     *             },
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="name_ar", type="string"),
     *             @OA\Property(property="icon", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Motorcycle type updated"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $type = MotorcycleType::find($id);

        if (!$type) {
            return response()->json([
                'success' => false,
                'message' => 'Motorcycle type not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:motorcycle_types,name,' . $id,
            'name_ar' => 'nullable|string|max:255',
            'icon' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $type->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Motorcycle type updated successfully.',
            'data' => $type,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/motorcycle-types/{id}",
     *     summary="Delete a motorcycle type (Admin)",
     *     tags={"Admin Motorcycle Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Motorcycle type deleted successfully"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $type = MotorcycleType::find($id);

        if (!$type) {
            return response()->json([
                'success' => false,
                'message' => 'Motorcycle type not found.',
            ], 404);
        }

        $type->delete();

        return response()->json([
            'success' => true,
            'message' => 'Motorcycle type deleted successfully.',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/motorcycle-types/stats/overview",
     *     summary="Get motorcycle types statistics (Admin)",
     *     tags={"Admin Motorcycle Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_types' => MotorcycleType::count(),
            'types_this_month' => MotorcycleType::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
