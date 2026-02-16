<?php

namespace App\Http\Controllers;

use App\Models\RouteTag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RouteTagController extends Controller
{
    /**
     * Display a listing of route tags.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RouteTag::withCount('routes')->orderBy('usage_count', 'desc');

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('slug', 'like', '%' . $request->search . '%');
        }

        if ($request->has('per_page')) {
            $tags = $query->paginate($request->per_page);
            return response()->json([
                'success' => true,
                'data' => $tags->items(),
                'current_page' => $tags->currentPage(),
                'last_page' => $tags->lastPage(),
                'per_page' => $tags->perPage(),
                'total' => $tags->total(),
            ]);
        } else {
            $tags = $query->get();
            return response()->json([
                'success' => true,
                'data' => $tags,
            ]);
        }
    }

    /**
     * Store a newly created route tag.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $slug = Str::slug($request->name);

        $existingTag = RouteTag::where('slug', $slug)->first();
        if ($existingTag) {
            return response()->json([
                'success' => false,
                'message' => 'Tag already exists',
                'data' => $existingTag,
            ], 409);
        }

        $tag = RouteTag::create([
            'name' => $request->name,
            'slug' => $slug,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Route tag created successfully',
            'data' => $tag,
        ], 201);
    }

    /**
     * Display the specified route tag.
     */
    public function show(int $id): JsonResponse
    {
        $tag = RouteTag::withCount('routes')->find($id);

        if (!$tag) {
            return response()->json([
                'success' => false,
                'message' => 'Route tag not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $tag,
        ]);
    }

    /**
     * Display the specified route tag by slug.
     */
    public function showBySlug(string $slug): JsonResponse
    {
        $tag = RouteTag::where('slug', $slug)
            ->withCount('routes')
            ->first();

        if (!$tag) {
            return response()->json([
                'success' => false,
                'message' => 'Route tag not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $tag,
        ]);
    }

    /**
     * Search for route tags.
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $tags = RouteTag::where('name', 'like', '%' . $request->query . '%')
            ->orWhere('slug', 'like', '%' . $request->query . '%')
            ->withCount('routes')
            ->orderBy('usage_count', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tags,
        ]);
    }

    /**
     * Update the specified route tag.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tag = RouteTag::find($id);

        if (!$tag) {
            return response()->json([
                'success' => false,
                'message' => 'Route tag not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $slug = Str::slug($request->name);

        $existingTag = RouteTag::where('slug', $slug)
            ->where('id', '!=', $id)
            ->first();

        if ($existingTag) {
            return response()->json([
                'success' => false,
                'message' => 'Another tag with this name already exists',
            ], 409);
        }

        $tag->update([
            'name' => $request->name,
            'slug' => $slug,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Route tag updated successfully',
            'data' => $tag->fresh(),
        ]);
    }

    /**
     * Remove the specified route tag.
     */
    public function destroy(int $id): JsonResponse
    {
        $tag = RouteTag::find($id);

        if (!$tag) {
            return response()->json([
                'success' => false,
                'message' => 'Route tag not found',
            ], 404);
        }

        $tag->routes()->detach();
        $tag->delete();

        return response()->json([
            'success' => true,
            'message' => 'Route tag deleted successfully',
        ]);
    }
}
