<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EventCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminEventCategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/event-categories",
     *     summary="Admin: Get all event categories",
     *     tags={"Admin Event Categories"},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="Vide = tous", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", example="Music")),
     *     @OA\Response(
     *         response=200,
     *         description="List of event categories",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EventCategory")),
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="last_page", type="integer"),
     *             @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = EventCategory::query();

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('name_ar', 'like', '%' . $request->search . '%');
        }

        if ($request->has('per_page')) {
            $categories = $query->paginate($request->per_page);
        } else {
            $categories = $query->get();
        }

        return response()->json(['data' => $categories]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/event-categories",
     *     summary="Admin: Create event category",
     *     tags={"Admin Event Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Music"),
     *             @OA\Property(property="name_ar", type="string", example="موسيقى"),
     *             @OA\Property(property="icon", type="string", example="music_note")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Category created")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255', // Assuming icon path or class
        ]);

        $category = EventCategory::create([
            'name' => $validated['name'],
            'name_ar' => $validated['name_ar'] ?? null,
            'slug' => Str::slug($validated['name']),
            'icon' => $validated['icon'] ?? null,
            'is_active' => true
        ]);

        return response()->json(['message' => 'Category created', 'data' => $category], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/event-categories/{id}",
     *     summary="Admin: Update event category",
     *     tags={"Admin Event Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="name_ar", type="string"),
     *             @OA\Property(property="icon", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Category updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $category = EventCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ]);

        if (isset($validated['name']) && $validated['name'] !== $category->name) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return response()->json(['message' => 'Category updated', 'data' => $category]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/event-categories/{id}",
     *     summary="Admin: Delete event category",
     *     tags={"Admin Event Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Category deleted")
     * )
     */
    public function destroy($id)
    {
        $category = EventCategory::findOrFail($id);

        // Prevent deleting if events exist? Optional check.
        if ($category->events()->exists()) {
            return response()->json(['message' => 'Cannot delete category with associated events'], 400);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted']);
    }
}
