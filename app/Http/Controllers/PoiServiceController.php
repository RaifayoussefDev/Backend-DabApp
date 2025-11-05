<?php

namespace App\Http\Controllers;

use App\Models\PoiService;
use App\Models\PoiType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PoiServiceController extends Controller
{
    /**
     * Display a listing of POI services.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PoiService::with('poiType');

        if ($request->has('type_id')) {
            $query->where('type_id', $request->type_id);
        }

        $services = $query->get();

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }

    /**
     * Store a newly created POI service.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type_id' => 'required|exists:poi_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $service = PoiService::create($validator->validated());
        $service->load('poiType');

        return response()->json([
            'success' => true,
            'message' => 'POI service created successfully',
            'data' => $service,
        ], 201);
    }

    /**
     * Display the specified POI service.
     */
    public function show(int $id): JsonResponse
    {
        $service = PoiService::with('poiType')->find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'POI service not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $service,
        ]);
    }

    /**
     * Update the specified POI service.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $service = PoiService::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'POI service not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'type_id' => 'sometimes|required|exists:poi_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $service->update($validator->validated());
        $service->load('poiType');

        return response()->json([
            'success' => true,
            'message' => 'POI service updated successfully',
            'data' => $service,
        ]);
    }

    /**
     * Remove the specified POI service.
     */
    public function destroy(int $id): JsonResponse
    {
        $service = PoiService::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'POI service not found',
            ], 404);
        }

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'POI service deleted successfully',
        ]);
    }
}
