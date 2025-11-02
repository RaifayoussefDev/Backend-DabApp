<?php

namespace App\Http\Controllers;

use App\Models\RouteCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RouteCategoryController extends Controller
{
    /**
     * Display a listing of route categories.
     */
    public function index(): JsonResponse
    {
        $categories = RouteCategory::withCount('routes')->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Store a newly created route category.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:route_categories,name',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['slug'] = Str::slug($request->name);

        $category = RouteCategory::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Route category created successfully',
            'data' => $category,
        ], 201);
    }

    /**
     * Display the specified route category.
     */
    public function show(int $id): JsonResponse
    {
        $category = RouteCategory::withCount('routes')->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Route category not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }

    /**
     * Update the specified route category.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $category = RouteCategory::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Route category not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:route_categories,name,' . $id,
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Route category updated successfully',
            'data' => $category->fresh(),
        ]);
    }

    /**
     * Remove the specified route category.
     */
    public function destroy(int $id): JsonResponse
    {
        $category = RouteCategory::withCount('routes')->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Route category not found',
            ], 404);
        }

        if ($category->routes_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with associated routes',
            ], 409);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Route category deleted successfully',
        ]);
    }
}
