<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\TowType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin Tow Services",
 *     description="API endpoints for managing tow types (Admin)"
 * )
 */
class AdminTowServiceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/tow-types",
     *     summary="List tow types (Admin)",
     *     operationId="adminGetTowTypes",
     *     tags={"Admin Tow Services"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_active", in="query", description="Filter by active status", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="Tow types retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Flatbed Towing"),
     *                     @OA\Property(property="base_price", type="number", example=150.00),
     *                     @OA\Property(property="price_per_km", type="number", example=5.00),
     *                     @OA\Property(property="is_active", type="boolean", example=true)
     *                 )),
     *                 @OA\Property(property="total", type="integer", example=3)
     *             ),
     *             @OA\Property(property="message", type="string", example="Tow types retrieved successfully")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = TowType::orderBy('order_position', 'asc');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->input('per_page');

        if ($perPage) {
            $types = $query->paginate($perPage);
        } else {
            $types = $query->get();
        }

        return response()->json([
            'success' => true,
            'data' => $types,
            'message' => 'Tow types retrieved successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/tow-types",
     *     summary="Create tow type (Admin)",
     *     operationId="adminCreateTowType",
     *     tags={"Admin Tow Services"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "name_ar", "base_price", "price_per_km"},
     *             @OA\Property(property="name", type="string", example="Flatbed Towing"),
     *             @OA\Property(property="name_ar", type="string", example="سطحة عادية"),
     *             @OA\Property(property="base_price", type="number", example=100.00),
     *             @OA\Property(property="price_per_km", type="number", example=5.00),
     *             @OA\Property(property="description", type="string", example="Standard flatbed towing service suitable for most cars."),
     *             @OA\Property(property="description_ar", type="string", example="خدمة سطحة عادية مناسبة لمعظم السيارات."),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tow type created")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'price_per_km' => 'required|numeric|min:0',
            'icon' => 'nullable|string',
            'image' => 'nullable|string',
            'is_active' => 'boolean',
            'order_position' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $towType = TowType::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $towType,
            'message' => 'Tow type created successfully'
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/tow-types/{id}",
     *     summary="Get tow type details (Admin)",
     *     operationId="adminGetTowType",
     *     tags={"Admin Tow Services"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Tow type details retrieved")
     * )
     */
    public function show($id)
    {
        $towType = TowType::findOrFail($id);
        return response()->json(['success' => true, 'data' => $towType]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/tow-types/{id}",
     *     summary="Update tow type (Admin)",
     *     operationId="adminUpdateTowType",
     *     tags={"Admin Tow Services"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/TowType")),
     *     @OA\Response(response=200, description="Tow type updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $towType = TowType::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'name_ar' => 'string|max:255',
            'base_price' => 'numeric|min:0',
            'price_per_km' => 'numeric|min:0',
            'is_active' => 'boolean',
            'order_position' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $towType->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $towType,
            'message' => 'Tow type updated successfully'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/tow-types/{id}",
     *     summary="Delete tow type (Admin)",
     *     operationId="adminDeleteTowType",
     *     tags={"Admin Tow Services"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Tow type deleted")
     * )
     */
    public function destroy($id)
    {
        $towType = TowType::findOrFail($id);
        $towType->delete();

        return response()->json(['success' => true, 'message' => 'Tow type deleted successfully']);
    }
}
