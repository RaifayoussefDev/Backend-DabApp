<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventActivityController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/activities",
     *     summary="Get event activities schedule",
     *     tags={"Event Activities"},
     *     @OA\Parameter(
     *         name="eventId",
     *         in="path",
     *         required=true,
     *         description="Event ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of event activities",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="event_id", type="integer", example=5),
     *                 @OA\Property(property="title", type="string", example="Opening Ceremony"),
     *                 @OA\Property(property="description", type="string", example="Official event opening with special guests"),
     *                 @OA\Property(property="start_time", type="string", format="time", example="09:00:00"),
     *                 @OA\Property(property="end_time", type="string", format="time", example="09:30:00"),
     *                 @OA\Property(property="location", type="string", example="Main Stage"),
     *                 @OA\Property(property="order_position", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Event not found")
     * )
     */
    public function index($eventId)
    {
        $event = Event::findOrFail($eventId);

        $activities = EventActivity::where('event_id', $eventId)
            ->orderBy('order_position')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'message' => 'Activities retrieved successfully',
            'data' => $activities
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/activities/{activityId}",
     *     summary="Get single activity details",
     *     tags={"Event Activities"},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="activityId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Activity details",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="event_id", type="integer"),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="start_time", type="string"),
     *             @OA\Property(property="end_time", type="string"),
     *             @OA\Property(property="location", type="string"),
     *             @OA\Property(property="order_position", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Activity not found")
     * )
     */
    public function show($eventId, $activityId)
    {
        $activity = EventActivity::where('event_id', $eventId)
            ->where('id', $activityId)
            ->firstOrFail();

        return response()->json([
            'message' => 'Activity retrieved successfully',
            'data' => $activity
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/events/{eventId}/activities",
     *     summary="Add an activity to event schedule (organizer only)",
     *     tags={"Event Activities"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="eventId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title"},
     *             @OA\Property(property="title", type="string", example="Opening Ceremony"),
     *             @OA\Property(property="description", type="string", example="Official event opening"),
     *             @OA\Property(property="start_time", type="string", format="time", example="09:00:00"),
     *             @OA\Property(property="end_time", type="string", format="time", example="09:30:00"),
     *             @OA\Property(property="location", type="string", example="Main Stage"),
     *             @OA\Property(property="order_position", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Activity created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Activity created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized - Not the event organizer"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, $eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can add activities.'
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'start_time' => 'nullable|date_format:H:i:s',
            'end_time' => 'nullable|date_format:H:i:s|after:start_time',
            'location' => 'nullable|string|max:255',
            'order_position' => 'nullable|integer|min:0',
        ]);

        $activity = EventActivity::create([
            'event_id' => $eventId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'location' => $validated['location'] ?? null,
            'order_position' => $validated['order_position'] ?? 0,
        ]);

        return response()->json([
            'message' => 'Activity created successfully',
            'data' => $activity
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/events/{eventId}/activities/{activityId}",
     *     summary="Update an activity",
     *     tags={"Event Activities"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="activityId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="start_time", type="string", format="time"),
     *             @OA\Property(property="end_time", type="string", format="time"),
     *             @OA\Property(property="location", type="string"),
     *             @OA\Property(property="order_position", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Activity updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Activity not found")
     * )
     */
    public function update(Request $request, $eventId, $activityId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can update activities.'
            ], 403);
        }

        $activity = EventActivity::where('event_id', $eventId)
            ->where('id', $activityId)
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'start_time' => 'nullable|date_format:H:i:s',
            'end_time' => 'nullable|date_format:H:i:s|after:start_time',
            'location' => 'nullable|string|max:255',
            'order_position' => 'nullable|integer|min:0',
        ]);

        $activity->update($validated);

        return response()->json([
            'message' => 'Activity updated successfully',
            'data' => $activity->fresh()
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/events/{eventId}/activities/{activityId}",
     *     summary="Delete an activity",
     *     tags={"Event Activities"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="activityId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Activity deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Activity deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Activity not found")
     * )
     */
    public function destroy($eventId, $activityId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can delete activities.'
            ], 403);
        }

        $activity = EventActivity::where('event_id', $eventId)
            ->where('id', $activityId)
            ->firstOrFail();

        $activity->delete();

        return response()->json([
            'message' => 'Activity deleted successfully'
        ]);
    }
}
