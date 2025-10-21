<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventUpdateController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/updates",
     *     summary="Get event updates",
     *     tags={"Event Updates"},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="important_only", in="query", description="Show only important updates", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="List of event updates",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="content", type="string"),
     *                     @OA\Property(property="is_important", type="boolean"),
     *                     @OA\Property(property="posted_by", type="object"),
     *                     @OA\Property(property="created_at", type="string")
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Event not found")
     * )
     */
    public function index(Request $request, $eventId)
    {
        $event = Event::findOrFail($eventId);

        $query = EventUpdate::where('event_id', $eventId)
            ->with('postedBy');

        if ($request->has('important_only') && $request->boolean('important_only')) {
            $query->where('is_important', true);
        }

        $updates = $query->latest()->paginate(20);

        return response()->json([
            'message' => 'Updates retrieved successfully',
            'event' => [
                'id' => $event->id,
                'title' => $event->title
            ],
            'data' => $updates
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/updates/{updateId}",
     *     summary="Get single update details",
     *     tags={"Event Updates"},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="updateId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Update details",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Update not found")
     * )
     */
    public function show($eventId, $updateId)
    {
        $update = EventUpdate::where('event_id', $eventId)
            ->where('id', $updateId)
            ->with('postedBy')
            ->firstOrFail();

        return response()->json([
            'message' => 'Update retrieved successfully',
            'data' => $update
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/events/{eventId}/updates",
     *     summary="Post an update (organizer only)",
     *     tags={"Event Updates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","content"},
     *             @OA\Property(property="title", type="string", maxLength=255, example="Schedule Change"),
     *             @OA\Property(property="content", type="string", example="Main race now starts at 2 PM"),
     *             @OA\Property(property="is_important", type="boolean", example=true, description="Mark as important update")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Update created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(Request $request, $eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can post updates.'
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_important' => 'boolean',
        ]);

        $update = EventUpdate::create([
            'event_id' => $eventId,
            'posted_by' => $user->id,
            'title' => $validated['title'],
            'content' => $validated['content'],
            'is_important' => $validated['is_important'] ?? false,
        ]);

        return response()->json([
            'message' => 'Update created successfully',
            'data' => $update->load('postedBy')
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/events/{eventId}/updates/{updateId}",
     *     summary="Edit an update (organizer only)",
     *     tags={"Event Updates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="updateId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", maxLength=255),
     *             @OA\Property(property="content", type="string"),
     *             @OA\Property(property="is_important", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Update modified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(Request $request, $eventId, $updateId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can edit updates.'
            ], 403);
        }

        $eventUpdate = EventUpdate::where('event_id', $eventId)
            ->where('id', $updateId)
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'is_important' => 'sometimes|boolean',
        ]);

        $eventUpdate->update($validated);

        return response()->json([
            'message' => 'Update modified successfully',
            'data' => $eventUpdate->fresh()->load('postedBy')
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/events/{eventId}/updates/{updateId}",
     *     summary="Delete an update (organizer only)",
     *     tags={"Event Updates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="updateId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Update deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Update deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy($eventId, $updateId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can delete updates.'
            ], 403);
        }

        $eventUpdate = EventUpdate::where('event_id', $eventId)
            ->where('id', $updateId)
            ->firstOrFail();

        $eventUpdate->delete();

        return response()->json([
            'message' => 'Update deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/updates/important",
     *     summary="Get important updates only",
     *     tags={"Event Updates"},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="List of important updates",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function important($eventId)
    {
        $event = Event::findOrFail($eventId);

        $updates = EventUpdate::where('event_id', $eventId)
            ->where('is_important', true)
            ->with('postedBy')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Important updates retrieved successfully',
            'event' => [
                'id' => $event->id,
                'title' => $event->title
            ],
            'data' => $updates
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/updates/latest",
     *     summary="Get latest update",
     *     tags={"Event Updates"},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Latest update",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object", nullable=true)
     *         )
     *     )
     * )
     */
    public function latest($eventId)
    {
        $event = Event::findOrFail($eventId);

        $latestUpdate = EventUpdate::where('event_id', $eventId)
            ->with('postedBy')
            ->latest()
            ->first();

        return response()->json([
            'message' => 'Latest update retrieved successfully',
            'event' => [
                'id' => $event->id,
                'title' => $event->title
            ],
            'data' => $latestUpdate
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/events/{eventId}/updates/bulk-delete",
     *     summary="Delete multiple updates (organizer only)",
     *     tags={"Event Updates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"update_ids"},
     *             @OA\Property(
     *                 property="update_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 3}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Updates deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="deleted_count", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function bulkDelete(Request $request, $eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can delete updates.'
            ], 403);
        }

        $validated = $request->validate([
            'update_ids' => 'required|array',
            'update_ids.*' => 'required|integer|exists:event_updates,id',
        ]);

        $deletedCount = EventUpdate::where('event_id', $eventId)
            ->whereIn('id', $validated['update_ids'])
            ->delete();

        return response()->json([
            'message' => 'Updates deleted successfully',
            'deleted_count' => $deletedCount
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/my-event-updates",
     *     summary="Get all updates from my registered events (auth required)",
     *     tags={"Event Updates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="important_only", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="List of updates from registered events",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function myEventUpdates(Request $request)
    {
        $user = Auth::user();

        // Get IDs of events user is registered for
        $registeredEventIds = $user->participations()
            ->whereIn('status', ['registered', 'confirmed', 'attended'])
            ->pluck('event_id');

        $query = EventUpdate::whereIn('event_id', $registeredEventIds)
            ->with(['event', 'postedBy']);

        if ($request->has('important_only') && $request->boolean('important_only')) {
            $query->where('is_important', true);
        }

        $updates = $query->latest()->paginate(20);

        return response()->json([
            'message' => 'Updates from your events retrieved successfully',
            'data' => $updates
        ]);
    }
}
