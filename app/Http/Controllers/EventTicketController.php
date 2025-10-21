<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\EventTicketPurchase;
use App\Models\EventParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EventTicketController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/tickets",
     *     summary="Get event tickets",
     *     tags={"Event Tickets"},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="available_only", in="query", description="Show only available tickets", @OA\Schema(type="boolean", default=true)),
     *     @OA\Response(
     *         response=200,
     *         description="List of event tickets",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function index(Request $request, $eventId)
    {
        $event = Event::findOrFail($eventId);

        $query = EventTicket::where('event_id', $eventId);

        if ($request->get('available_only', true)) {
            $query->where('is_active', 1);
        }

        $tickets = $query->orderBy('price')->get()->map(function($ticket) {
            return [
                'id' => $ticket->id,
                'ticket_type' => $ticket->ticket_type,
                'price' => $ticket->price,
                'description' => $ticket->description,
                'quantity_available' => $ticket->quantity_available,
                'quantity_sold' => $ticket->quantity_sold,
                'is_available' => $ticket->isAvailable(),
                'remaining_quantity' => $ticket->remainingQuantity(),
                'is_active' => $ticket->is_active,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at,
            ];
        });

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
     * @OA\Get(
     *     path="/api/events/{eventId}/tickets/{ticketId}",
     *     summary="Get ticket details",
     *     tags={"Event Tickets"},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="ticketId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Ticket details"),
     *     @OA\Response(response=404, description="Ticket not found")
     * )
     */
    public function show($eventId, $ticketId)
    {
        $ticket = EventTicket::where('event_id', $eventId)
            ->where('id', $ticketId)
            ->with('event')
            ->firstOrFail();

        return response()->json([
            'message' => 'Ticket retrieved successfully',
            'data' => [
                'id' => $ticket->id,
                'event_id' => $ticket->event_id,
                'ticket_type' => $ticket->ticket_type,
                'price' => $ticket->price,
                'description' => $ticket->description,
                'quantity_available' => $ticket->quantity_available,
                'quantity_sold' => $ticket->quantity_sold,
                'is_available' => $ticket->isAvailable(),
                'remaining_quantity' => $ticket->remainingQuantity(),
                'is_active' => $ticket->is_active,
                'event' => $ticket->event,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at,
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/events/{eventId}/tickets",
     *     summary="Create a ticket type (organizer only)",
     *     tags={"Event Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ticket_type","price"},
     *             @OA\Property(property="ticket_type", type="string", example="VIP Pass"),
     *             @OA\Property(property="price", type="number", format="float", example=300.00),
     *             @OA\Property(property="quantity_available", type="integer", example=50),
     *             @OA\Property(property="description", type="string", maxLength=1000)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Ticket created successfully"),
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
                'message' => 'Unauthorized. Only event organizer can create tickets.'
            ], 403);
        }

        $validated = $request->validate([
            'ticket_type' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'quantity_available' => 'nullable|integer|min:1',
            'description' => 'nullable|string|max:1000',
        ]);

        $ticket = EventTicket::create([
            'event_id' => $eventId,
            'ticket_type' => $validated['ticket_type'],
            'price' => $validated['price'],
            'quantity_available' => $validated['quantity_available'] ?? null,
            'quantity_sold' => 0,
            'description' => $validated['description'] ?? null,
            'is_active' => 1,
        ]);

        return response()->json([
            'message' => 'Ticket created successfully',
            'data' => $ticket
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/events/{eventId}/tickets/{ticketId}/purchase",
     *     summary="Purchase a ticket (auth required)",
     *     tags={"Event Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="ticketId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"quantity"},
     *             @OA\Property(property="quantity", type="integer", minimum=1, maximum=10, example=2)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Purchase successful"),
     *     @OA\Response(response=400, description="Ticket not available or insufficient quantity"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function purchase(Request $request, $eventId, $ticketId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:10',
        ]);

        $ticket = EventTicket::where('event_id', $eventId)
            ->where('id', $ticketId)
            ->firstOrFail();

        if (!$ticket->isAvailable()) {
            return response()->json([
                'message' => 'This ticket is no longer available'
            ], 400);
        }

        $remaining = $ticket->remainingQuantity();
        if ($remaining !== null && $validated['quantity'] > $remaining) {
            return response()->json([
                'message' => "Only {$remaining} ticket(s) available"
            ], 400);
        }

        // Create or get participant
        $participant = EventParticipant::firstOrCreate(
            [
                'event_id' => $eventId,
                'user_id' => $user->id,
            ],
            [
                'status' => 'registered',
                'registration_date' => now(),
                'payment_status' => 'pending',
            ]
        );

        DB::beginTransaction();
        try {
            $totalPrice = $ticket->price * $validated['quantity'];

            $purchase = EventTicketPurchase::create([
                'ticket_id' => $ticketId,
                'participant_id' => $participant->id,
                'quantity' => $validated['quantity'],
                'total_price' => $totalPrice,
                'purchase_date' => now(),
                'qr_code' => uniqid('TICKET-' . $eventId . '-' . $user->id . '-'),
                'checked_in_at' => null,
            ]);

            $ticket->increment('quantity_sold', $validated['quantity']);

            $participant->update([
                'payment_status' => 'paid',
                'payment_amount' => ($participant->payment_amount ?? 0) + $totalPrice,
            ]);

            // Increment event participants count if newly created
            if ($participant->wasRecentlyCreated) {
                $event->increment('participants_count');
            }

            DB::commit();

            return response()->json([
                'message' => 'Purchase successful',
                'data' => $purchase->load('ticket', 'participant.user')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Purchase failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/my-tickets",
     *     summary="Get my purchased tickets (auth required)",
     *     tags={"Event Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="event_id", in="query", description="Filter by event", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="List of purchased tickets"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function myTickets(Request $request)
    {
        $user = Auth::user();

        $query = EventTicketPurchase::whereHas('participant', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with(['ticket.event', 'participant']);

        if ($request->has('event_id')) {
            $query->whereHas('ticket', function($q) use ($request) {
                $q->where('event_id', $request->event_id);
            });
        }

        $purchases = $query->orderBy('purchase_date', 'desc')->paginate(20);

        return response()->json([
            'message' => 'Your tickets retrieved successfully',
            'data' => $purchases
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/events/{eventId}/tickets/{ticketId}",
     *     summary="Update a ticket type (organizer only)",
     *     tags={"Event Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="ticketId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="ticket_type", type="string"),
     *             @OA\Property(property="price", type="number"),
     *             @OA\Property(property="quantity_available", type="integer"),
     *             @OA\Property(property="is_active", type="boolean"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Ticket updated successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(Request $request, $eventId, $ticketId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can update tickets.'
            ], 403);
        }

        $ticket = EventTicket::where('event_id', $eventId)
            ->where('id', $ticketId)
            ->firstOrFail();

        $validated = $request->validate([
            'ticket_type' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'quantity_available' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string|max:1000',
        ]);

        // Prevent reducing quantity below already sold
        if (isset($validated['quantity_available']) && $validated['quantity_available'] < $ticket->quantity_sold) {
            return response()->json([
                'message' => "Cannot set quantity below already sold tickets ({$ticket->quantity_sold})"
            ], 400);
        }

        $ticket->update($validated);

        return response()->json([
            'message' => 'Ticket updated successfully',
            'data' => $ticket->fresh()
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/events/{eventId}/tickets/{ticketId}",
     *     summary="Delete a ticket type (organizer only)",
     *     tags={"Event Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="ticketId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Ticket deleted successfully"),
     *     @OA\Response(response=400, description="Cannot delete ticket with purchases"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy($eventId, $ticketId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can delete tickets.'
            ], 403);
        }

        $ticket = EventTicket::where('event_id', $eventId)
            ->where('id', $ticketId)
            ->firstOrFail();

        if ($ticket->quantity_sold > 0) {
            return response()->json([
                'message' => 'Cannot delete ticket type with existing purchases. You can deactivate it instead.'
            ], 400);
        }

        $ticket->delete();

        return response()->json([
            'message' => 'Ticket deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/tickets/statistics",
     *     summary="Get tickets statistics (organizer only)",
     *     tags={"Event Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Tickets statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="total_tickets_sold", type="integer"),
     *             @OA\Property(property="total_revenue", type="number"),
     *             @OA\Property(property="ticket_types", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function statistics($eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can view statistics.'
            ], 403);
        }

        $tickets = EventTicket::where('event_id', $eventId)->get();

        $ticketStats = $tickets->map(function($ticket) {
            return [
                'id' => $ticket->id,
                'ticket_type' => $ticket->ticket_type,
                'price' => $ticket->price,
                'quantity_available' => $ticket->quantity_available,
                'quantity_sold' => $ticket->quantity_sold,
                'remaining' => $ticket->remainingQuantity(),
                'revenue' => $ticket->price * $ticket->quantity_sold,
                'is_active' => $ticket->is_active,
            ];
        });

        $totalSold = $tickets->sum('quantity_sold');
        $totalRevenue = $tickets->sum(function($ticket) {
            return $ticket->price * $ticket->quantity_sold;
        });

        return response()->json([
            'message' => 'Statistics retrieved successfully',
            'data' => [
                'total_tickets_sold' => $totalSold,
                'total_revenue' => $totalRevenue,
                'ticket_types' => $ticketStats,
                'total_ticket_types' => $tickets->count(),
                'active_ticket_types' => $tickets->where('is_active', 1)->count(),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/tickets/{purchaseId}",
     *     summary="Get ticket purchase details (auth required)",
     *     tags={"Event Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="purchaseId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Ticket purchase details"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function showPurchase($purchaseId)
    {
        $user = Auth::user();

        $purchase = EventTicketPurchase::whereHas('participant', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with(['ticket.event', 'participant.user'])->findOrFail($purchaseId);

        return response()->json([
            'message' => 'Ticket purchase retrieved successfully',
            'data' => $purchase
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/tickets/{purchaseId}/check-in",
     *     summary="Check in ticket (organizer only)",
     *     tags={"Event Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="purchaseId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Ticket checked in successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=400, description="Already checked in")
     * )
     */
    public function checkIn($purchaseId)
    {
        $user = Auth::user();

        $purchase = EventTicketPurchase::with('ticket.event')->findOrFail($purchaseId);
        $event = $purchase->ticket->event;

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can check in tickets.'
            ], 403);
        }

        if ($purchase->checked_in_at) {
            return response()->json([
                'message' => 'Ticket already checked in',
                'checked_in_at' => $purchase->checked_in_at
            ], 400);
        }

        $purchase->update([
            'checked_in_at' => now()
        ]);

        return response()->json([
            'message' => 'Ticket checked in successfully',
            'data' => $purchase->fresh()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/tickets/verify/{qrCode}",
     *     summary="Verify ticket by QR code (organizer only)",
     *     tags={"Event Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="qrCode", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Ticket verification result",
     *         @OA\JsonContent(
     *             @OA\Property(property="valid", type="boolean"),
     *             @OA\Property(property="ticket", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function verifyQRCode($qrCode)
    {
        $user = Auth::user();

        $purchase = EventTicketPurchase::where('qr_code', $qrCode)
            ->with(['ticket.event', 'participant.user'])
            ->first();

        if (!$purchase) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid QR code'
            ], 404);
        }

        $event = $purchase->ticket->event;

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can verify tickets.'
            ], 403);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Valid ticket',
            'data' => [
                'purchase' => $purchase,
                'ticket_type' => $purchase->ticket->ticket_type,
                'quantity' => $purchase->quantity,
                'holder' => $purchase->participant->user->name ?? 'N/A',
                'is_checked_in' => $purchase->checked_in_at !== null,
                'checked_in_at' => $purchase->checked_in_at,
                'event' => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'date' => $event->event_date,
                ]
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/tickets/purchases",
     *     summary="Get all ticket purchases for event (organizer only)",
     *     tags={"Event Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="checked_in", in="query", description="Filter by check-in status", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="List of ticket purchases"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function eventPurchases(Request $request, $eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can view purchases.'
            ], 403);
        }

        $query = EventTicketPurchase::whereHas('ticket', function($q) use ($eventId) {
            $q->where('event_id', $eventId);
        })->with(['ticket', 'participant.user']);

        if ($request->has('checked_in')) {
            if ($request->boolean('checked_in')) {
                $query->whereNotNull('checked_in_at');
            } else {
                $query->whereNull('checked_in_at');
            }
        }

        $purchases = $query->orderBy('purchase_date', 'desc')->paginate(20);

        return response()->json([
            'message' => 'Ticket purchases retrieved successfully',
            'data' => $purchases
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/events/{eventId}/tickets/{ticketId}/toggle-active",
     *     summary="Activate/Deactivate a ticket type (organizer only)",
     *     tags={"Event Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="ticketId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Ticket status toggled"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function toggleActive($eventId, $ticketId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can toggle ticket status.'
            ], 403);
        }

        $ticket = EventTicket::where('event_id', $eventId)
            ->where('id', $ticketId)
            ->firstOrFail();

        $ticket->update([
            'is_active' => !$ticket->is_active
        ]);

        $status = $ticket->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'message' => "Ticket {$status} successfully",
            'data' => $ticket->fresh()
        ]);
    }
}
