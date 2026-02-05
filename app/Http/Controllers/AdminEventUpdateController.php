<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminEventUpdateController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/events/{eventId}/updates",
     *     summary="Admin: Get updates for an event",
     *     tags={"Admin Event Updates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="List of updates",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EventUpdate"))
     *         )
     *     )
     * )
     */
    public function index($eventId)
    {
        $event = Event::findOrFail($eventId);
        $updates = $event->updates()->with('postedBy')->latest()->get();

        return response()->json(['data' => $updates]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/events/{eventId}/updates",
     *     summary="Admin: Post a new update for an event",
     *     tags={"Admin Event Updates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "content"},
     *             @OA\Property(property="title", type="string", example="Important Announcement"),
     *             @OA\Property(property="content", type="string", example="The event start time has changed."),
     *             @OA\Property(property="is_important", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Update created")
     * )
     */
    public function store(Request $request, $eventId)
    {
        $event = Event::findOrFail($eventId);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_important' => 'boolean'
        ]);

        $update = $event->updates()->create(array_merge($validated, [
            'posted_by' => Auth::id()
        ]));

        return response()->json(['message' => 'Update created', 'data' => $update], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/event-updates/{id}",
     *     summary="Admin: Edit an event update",
     *     tags={"Admin Event Updates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="content", type="string"),
     *             @OA\Property(property="is_important", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Update modified")
     * )
     */
    public function update(Request $request, $id)
    {
        $update = EventUpdate::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'is_important' => 'boolean'
        ]);

        $update->update($validated);

        return response()->json(['message' => 'Update modified', 'data' => $update]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/event-updates/{id}",
     *     summary="Admin: Delete an event update",
     *     tags={"Admin Event Updates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Update deleted")
     * )
     */
    public function destroy($id)
    {
        $update = EventUpdate::findOrFail($id);
        $update->delete();

        return response()->json(['message' => 'Update deleted']);
    }
}
