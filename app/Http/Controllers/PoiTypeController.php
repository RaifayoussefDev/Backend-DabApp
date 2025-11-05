<?php

namespace App\Http\Controllers;

use App\Models\PoiType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PoiTypeController extends Controller
{
    /**
     * Display a listing of POI types.
     */
    public function index(): JsonResponse
    {
        $poiTypes = PoiType::withCount('pois')->get();

        return response()->json([
            'success' => true,
            'data' => $poiTypes,
        ]);
    }

    /**
     * Store a newly created POI type.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:poi_types,name',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $poiType = PoiType::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'POI type created successfully',
            'data' => $poiType,
        ], 201);
    }

    /**
     * Display the specified POI type.
     */
    public function show(int $id): JsonResponse
    {
        $poiType = PoiType::withCount('pois')->find($id);

        if (!$poiType) {
            return response()->json([
                'success' => false,
                'message' => 'POI type not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $poiType,
        ]);
    }

    /**
     * Update the specified POI type.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $poiType = PoiType::find($id);

        if (!$poiType) {
            return response()->json([
                'success' => false,
                'message' => 'POI type not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:poi_types,name,' . $id,
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $poiType->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'POI type updated successfully',
            'data' => $poiType->fresh(),
        ]);
    }

    /**
     * Remove the specified POI type.
     */
    public function destroy(int $id): JsonResponse
    {
        $poiType = PoiType::withCount('pois')->find($id);

        if (!$poiType) {
            return response()->json([
                'success' => false,
                'message' => 'POI type not found',
            ], 404);
        }

        if ($poiType->pois_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete POI type with associated POIs',
            ], 409);
        }

        $poiType->delete();

        return response()->json([
            'success' => true,
            'message' => 'POI type deleted successfully',
        ]);
    }
}
