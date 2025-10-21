<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EventCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class EventCategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/event-categories",
     *     summary="Get all event categories",
     *     tags={"Event Categories"},
     *     @OA\Parameter(
     *         name="with_count",
     *         in="query",
     *         description="Include events count",
     *         required=false,
     *         @OA\Schema(type="boolean", default=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of event categories",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Categories retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Motorcycle Racing"),
     *                     @OA\Property(property="slug", type="string", example="motorcycle-racing"),
     *                     @OA\Property(property="description", type="string", example="Professional motorcycle racing events"),
     *                     @OA\Property(property="icon", type="string", example="race-flag"),
     *                     @OA\Property(property="color", type="string", example="#FF5733"),
     *                     @OA\Property(property="events_count", type="integer", example=15),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = EventCategory::query();

        if ($request->get('with_count', true)) {
            $query->withCount('events');
        }

        $categories = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'message' => 'Categories retrieved successfully',
            'data' => $categories
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/event-categories/{id}",
     *     summary="Get category details",
     *     tags={"Event Categories"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Category ID or slug",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="with_events",
     *         in="query",
     *         description="Include recent events",
     *         @OA\Schema(type="boolean", default=true)
     *     ),
     *     @OA\Parameter(
     *         name="events_limit",
     *         in="query",
     *         description="Number of events to include",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category details with recent events",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="slug", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="icon", type="string"),
     *                 @OA\Property(property="color", type="string"),
     *                 @OA\Property(property="events", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Category not found")
     * )
     */
    public function show(Request $request, $id)
    {
        $query = EventCategory::query();

        // Check if ID is numeric or slug
        if (is_numeric($id)) {
            $query->where('id', $id);
        } else {
            $query->where('slug', $id);
        }

        if ($request->get('with_events', true)) {
            $limit = $request->get('events_limit', 10);
            $query->with(['events' => function($q) use ($limit) {
                $q->published()->upcoming()->limit($limit);
            }]);
        }

        $category = $query->firstOrFail();

        return response()->json([
            'message' => 'Category retrieved successfully',
            'data' => $category
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/event-categories",
     *     summary="Create a new category (auth required)",
     *     tags={"Event Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Motorcycle Racing"),
     *             @OA\Property(property="description", type="string", example="Professional motorcycle racing events"),
     *             @OA\Property(property="icon", type="string", example="race-flag"),
     *             @OA\Property(property="color", type="string", example="#FF5733")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Category created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Category created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="slug", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="icon", type="string"),
     *                 @OA\Property(property="color", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:event_categories,name',
            'description' => 'nullable|string|max:2000',
            'icon' => 'nullable|string|max:255',
            'color' => ['nullable', 'string', 'max:50', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
        ]);

        $slug = Str::slug($validated['name']);

        // Ensure unique slug
        $originalSlug = $slug;
        $counter = 1;
        while (EventCategory::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $category = EventCategory::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'color' => $validated['color'] ?? null,
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/event-categories/{id}",
     *     summary="Update a category (auth required)",
     *     tags={"Event Categories"},
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
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="icon", type="string"),
     *             @OA\Property(property="color", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $category = EventCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:event_categories,name,' . $id,
            'description' => 'nullable|string|max:2000',
            'icon' => 'nullable|string|max:255',
            'color' => ['nullable', 'string', 'max:50', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
        ]);

        if (isset($validated['name']) && $validated['name'] !== $category->name) {
            $slug = Str::slug($validated['name']);

            // Ensure unique slug
            $originalSlug = $slug;
            $counter = 1;
            while (EventCategory::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $validated['slug'] = $slug;
        }

        $category->update($validated);

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $category->fresh()
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/event-categories/{id}",
     *     summary="Delete a category (auth required)",
     *     tags={"Event Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Category deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=400, description="Cannot delete category with existing events")
     * )
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $category = EventCategory::findOrFail($id);

        // Check if category has events
        $eventsCount = $category->events()->count();
        if ($eventsCount > 0) {
            return response()->json([
                'message' => "Cannot delete category. It has {$eventsCount} event(s) associated with it."
            ], 400);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/event-categories/{id}/events",
     *     summary="Get all events in a category",
     *     tags={"Event Categories"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"upcoming", "ongoing", "completed"})),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(
     *         response=200,
     *         description="Events in category",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Category not found")
     * )
     */
    public function events(Request $request, $id)
    {
        $category = EventCategory::findOrFail($id);

        $query = $category->events()->with(['organizer', 'city', 'country'])->published();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $events = $query->orderBy('event_date', 'asc')->paginate($perPage);

        return response()->json([
            'message' => 'Events retrieved successfully',
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug
            ],
            'data' => $events
        ]);
    }
}
