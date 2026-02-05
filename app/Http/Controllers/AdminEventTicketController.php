<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventTicket;
use Illuminate\Http\Request;

class AdminEventTicketController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/events/{eventId}/tickets",
     *     summary="Admin: Get tickets for an event",
     *     tags={"Admin Event Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="List of tickets",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EventTicket"))
     *         )
     *     )
     * )
     */
    public function index($eventId)
    {
        $event = Event::findOrFail($eventId);
        $tickets = $event->tickets;

        return response()->json([
            'message' => 'Tickets retrieved successfully',
            'event' => [
                'id' => $event->id,
                'title' => $event->title
            ],
            'data' => $tickets
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/events/{eventId}/tickets",
     *     summary="Admin: Create a ticket pool for an event",
     *     tags={"Admin Event Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ticket_type", "price", "quantity_available"},
     *             @OA\Property(property="ticket_type", type="string", example="VIP"),
     *             @OA\Property(property="price", type="number", format="double", example=100.00),
     *             @OA\Property(property="quantity_available", type="integer", example=500),
     *             @OA\Property(property="description", type="string", example="VIP Access"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Ticket created")
     * )
     */
    public function store(Request $request, $eventId)
    {
        $event = Event::findOrFail($eventId);

        $validated = $request->validate([
            'ticket_type' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'quantity_available' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $ticket = $event->tickets()->create($validated);

        return response()->json(['message' => 'Ticket created', 'data' => $ticket], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/event-tickets/{id}",
     *     summary="Admin: Update a ticket",
     *     tags={"Admin Event Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="ticket_type", type="string"),
     *             @OA\Property(property="price", type="number"),
     *             @OA\Property(property="quantity_available", type="integer"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Ticket updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $ticket = EventTicket::findOrFail($id);

        $validated = $request->validate([
            'ticket_type' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'quantity_available' => 'nullable|integer|min:0', // Can be 0 if sold out or stopped
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $ticket->update($validated);

        return response()->json(['message' => 'Ticket updated', 'data' => $ticket]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/event-tickets/{id}",
     *     summary="Admin: Delete a ticket",
     *     tags={"Admin Event Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Ticket deleted")
     * )
     */
    public function destroy($id)
    {
        $ticket = EventTicket::findOrFail($id);

        // Check if any purchases exist
        if ($ticket->purchases()->count() > 0) {
            return response()->json(['message' => 'Cannot delete ticket with existing purchases'], 400);
        }

        $ticket->delete();

        return response()->json(['message' => 'Ticket deleted']);
    }
}
