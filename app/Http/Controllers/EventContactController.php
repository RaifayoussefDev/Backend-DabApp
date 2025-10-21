<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventContactController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/contacts",
     *     summary="Get event contacts",
     *     tags={"Event Contacts"},
     *     @OA\Parameter(
     *         name="eventId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by contact type",
     *         @OA\Schema(type="string", enum={"organizer", "support", "emergency"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of event contacts",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Event not found")
     * )
     */
    public function index(Request $request, $eventId)
    {
        $event = Event::findOrFail($eventId);

        $query = EventContact::where('event_id', $eventId);

        if ($request->has('type')) {
            $query->where('contact_type', $request->type);
        }

        $contacts = $query->orderBy('contact_type')->get();

        return response()->json([
            'message' => 'Contacts retrieved successfully',
            'event' => [
                'id' => $event->id,
                'title' => $event->title
            ],
            'data' => $contacts
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/contacts/{contactId}",
     *     summary="Get single contact details",
     *     tags={"Event Contacts"},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="contactId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Contact details"),
     *     @OA\Response(response=404, description="Contact not found")
     * )
     */
    public function show($eventId, $contactId)
    {
        $contact = EventContact::where('event_id', $eventId)
            ->where('id', $contactId)
            ->firstOrFail();

        return response()->json([
            'message' => 'Contact retrieved successfully',
            'data' => $contact
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/events/{eventId}/contacts",
     *     summary="Add contact to event (organizer only)",
     *     tags={"Event Contacts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"contact_type"},
     *             @OA\Property(property="contact_type", type="string", enum={"organizer", "support", "emergency"}),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="email", type="string", example="contact@example.com")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Contact created successfully"),
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
                'message' => 'Unauthorized. Only event organizer can add contacts.'
            ], 403);
        }

        $validated = $request->validate([
            'contact_type' => 'required|in:organizer,support,emergency',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $contact = EventContact::create([
            'event_id' => $eventId,
            'contact_type' => $validated['contact_type'],
            'name' => $validated['name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
        ]);

        return response()->json([
            'message' => 'Contact created successfully',
            'data' => $contact
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/events/{eventId}/contacts/{contactId}",
     *     summary="Update event contact (organizer only)",
     *     tags={"Event Contacts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="contactId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Contact updated successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(Request $request, $eventId, $contactId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can update contacts.'
            ], 403);
        }

        $contact = EventContact::where('event_id', $eventId)
            ->where('id', $contactId)
            ->firstOrFail();

        $validated = $request->validate([
            'contact_type' => 'sometimes|in:organizer,support,emergency',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $contact->update($validated);

        return response()->json([
            'message' => 'Contact updated successfully',
            'data' => $contact->fresh()
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/events/{eventId}/contacts/{contactId}",
     *     summary="Delete event contact (organizer only)",
     *     tags={"Event Contacts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="contactId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Contact deleted successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy($eventId, $contactId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can delete contacts.'
            ], 403);
        }

        $contact = EventContact::where('event_id', $eventId)
            ->where('id', $contactId)
            ->firstOrFail();

        $contact->delete();

        return response()->json([
            'message' => 'Contact deleted successfully'
        ]);
    }
}
