<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Http\Request;

class AdminEventParticipantController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/events/{eventId}/participants",
     *     summary="Admin: Get participants for an event",
     *     tags={"Admin Event Participants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="List of participants",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EventParticipant"))
     *         )
     *     )
     * )
     */
    public function index($eventId)
    {
        $event = Event::findOrFail($eventId);
        $participants = $event->participants()->with('user')->get();

        return response()->json([
            'message' => 'Participants retrieved successfully',
            'event' => [
                'id' => $event->id,
                'title' => $event->title
            ],
            'data' => $participants
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/event-participants/{id}",
     *     summary="Admin: Remove a participant",
     *     tags={"Admin Event Participants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Participant removed")
     * )
     */
    public function destroy($id)
    {
        $participant = EventParticipant::findOrFail($id);
        $participant->delete();

        return response()->json(['message' => 'Participant removed successfully']);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/event-participants/{id}",
     *     summary="Admin: Update participant status",
     *     tags={"Admin Event Participants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", enum={"confirmed", "pending", "cancelled", "attended"}),
     *             @OA\Property(property="payment_status", type="string", enum={"paid", "pending", "failed", "refunded"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Participant updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $participant = EventParticipant::findOrFail($id);

        $validated = $request->validate([
            'status' => 'sometimes|string|in:confirmed,pending,cancelled,attended',
            'payment_status' => 'sometimes|string|in:paid,pending,failed,refunded',
            'notes' => 'nullable|string'
        ]);

        $participant->update($validated);

        return response()->json(['message' => 'Participant updated', 'data' => $participant]);
    }
}
