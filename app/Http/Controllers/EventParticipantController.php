<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EventParticipantController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/events/{eventId}/register",
     *     summary="Register for an event (auth required)",
     *     tags={"Event Participants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string", example="Any special requirements")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Registration successful"),
     *     @OA\Response(response=400, description="Registration closed or already registered"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function register(Request $request, $eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if (!$event->isRegistrationOpen()) {
            return response()->json([
                'message' => 'Registration is closed for this event'
            ], 400);
        }

        if ($event->isFull()) {
            return response()->json([
                'message' => 'Event is full. No more participants can register.'
            ], 400);
        }

        $existingParticipant = EventParticipant::where('event_id', $eventId)
            ->where('user_id', $user->id)
            ->first();

        if ($existingParticipant) {
            if ($existingParticipant->status === 'cancelled') {
                $existingParticipant->update([
                    'status' => 'registered',
                    'registration_date' => now(),
                ]);

                return response()->json([
                    'message' => 'Registration reactivated successfully',
                    'data' => $existingParticipant->load('event', 'user')
                ]);
            }

            return response()->json([
                'message' => 'You are already registered for this event'
            ], 400);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000'
        ]);

        DB::beginTransaction();
        try {
            $participant = EventParticipant::create([
                'event_id' => $eventId,
                'user_id' => $user->id,
                'status' => 'registered',
                'registration_date' => now(),
                'payment_status' => $event->is_free ? null : 'pending',
                'payment_amount' => $event->is_free ? 0 : $event->price,
                'notes' => $validated['notes'] ?? null,
            ]);

            $event->increment('participants_count');

            DB::commit();

            return response()->json([
                'message' => 'Registration successful',
                'data' => $participant->load('event', 'user')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/events/{eventId}/unregister",
     *     summary="Unregister from an event (auth required)",
     *     tags={"Event Participants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Unregistration successful"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Registration not found")
     * )
     */
    public function unregister($eventId)
    {
        $user = Auth::user();

        $participant = EventParticipant::where('event_id', $eventId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($participant->status === 'cancelled') {
            return response()->json([
                'message' => 'Registration already cancelled'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $participant->update([
                'status' => 'cancelled',
                'cancellation_date' => now()
            ]);

            $event = Event::find($eventId);
            $event->decrement('participants_count');

            DB::commit();

            return response()->json([
                'message' => 'Unregistration successful'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Unregistration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/participants",
     *     summary="Get event participants",
     *     tags={"Event Participants"},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"registered", "confirmed", "attended", "cancelled"})),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="List of participants")
     * )
     */
    public function index(Request $request, $eventId)
    {
        $event = Event::findOrFail($eventId);

        $query = EventParticipant::where('event_id', $eventId)
            ->with('user');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', ['registered', 'confirmed', 'attended']);
        }

        $participants = $query->orderBy('registration_date', 'desc')->paginate(20);

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
     * @OA\Get(
     *     path="/api/my-events",
     *     summary="Get my registered events (auth required)",
     *     tags={"Event Participants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="List of my events"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function myEvents(Request $request)
    {
        $user = Auth::user();

        $query = EventParticipant::where('user_id', $user->id)
            ->with(['event.category', 'event.city', 'event.country', 'event.organizer']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', ['registered', 'confirmed', 'attended']);
        }

        $participants = $query->orderBy('registration_date', 'desc')->paginate(15);

        return response()->json([
            'message' => 'Your events retrieved successfully',
            'data' => $participants
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/events/{eventId}/participants/{participantId}/confirm",
     *     summary="Confirm a participant (organizer only)",
     *     tags={"Event Participants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="participantId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Participant confirmed"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function confirm($eventId, $participantId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can confirm participants.'
            ], 403);
        }

        $participant = EventParticipant::where('event_id', $eventId)
            ->where('id', $participantId)
            ->firstOrFail();

        $participant->update([
            'status' => 'confirmed',
            'confirmation_date' => now()
        ]);

        return response()->json([
            'message' => 'Participant confirmed successfully',
            'data' => $participant->fresh()->load('user')
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/events/{eventId}/participants/{participantId}/check-in",
     *     summary="Mark participant as attended (organizer only)",
     *     tags={"Event Participants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="participantId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Participant checked in"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function checkIn($eventId, $participantId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can check in participants.'
            ], 403);
        }

        $participant = EventParticipant::where('event_id', $eventId)
            ->where('id', $participantId)
            ->firstOrFail();

        $participant->update([
            'status' => 'attended',
            'attendance_date' => now()
        ]);

        return response()->json([
            'message' => 'Participant checked in successfully',
            'data' => $participant->fresh()->load('user')
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/participants/statistics",
     *     summary="Get participants statistics (organizer only)",
     *     tags={"Event Participants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Participants statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="registered", type="integer"),
     *             @OA\Property(property="confirmed", type="integer"),
     *             @OA\Property(property="attended", type="integer"),
     *             @OA\Property(property="cancelled", type="integer")
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

        $stats = [
            'total' => EventParticipant::where('event_id', $eventId)->count(),
            'registered' => EventParticipant::where('event_id', $eventId)->where('status', 'registered')->count(),
            'confirmed' => EventParticipant::where('event_id', $eventId)->where('status', 'confirmed')->count(),
            'attended' => EventParticipant::where('event_id', $eventId)->where('status', 'attended')->count(),
            'cancelled' => EventParticipant::where('event_id', $eventId)->where('status', 'cancelled')->count(),
        ];

        return response()->json([
            'message' => 'Statistics retrieved successfully',
            'data' => $stats
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/participants/{participantId}",
     *     summary="Get participant details (organizer only)",
     *     tags={"Event Participants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="participantId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Participant details"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show($eventId, $participantId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can view participant details.'
            ], 403);
        }

        $participant = EventParticipant::where('event_id', $eventId)
            ->where('id', $participantId)
            ->with('user')
            ->firstOrFail();

        return response()->json([
            'message' => 'Participant details retrieved successfully',
            'data' => $participant
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/my-registration",
     *     summary="Get my registration status for event (auth required)",
     *     tags={"Event Participants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Registration status",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_registered", type="boolean"),
     *             @OA\Property(property="registration", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function myRegistration($eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        $participant = EventParticipant::where('event_id', $eventId)
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'is_registered' => $participant !== null && $participant->status !== 'cancelled',
            'registration' => $participant
        ]);
    }
}
