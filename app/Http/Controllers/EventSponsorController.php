<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSponsor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EventSponsorController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/event-sponsors",
     *     summary="Get all sponsors",
     *     tags={"Event Sponsors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", description="Search by name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page (if not provided, returns all)", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="List of sponsors",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $query = EventSponsor::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        // If per_page is provided, paginate. Otherwise return all
        if ($request->has('per_page')) {
            $perPage = $request->input('per_page');
            $sponsors = $query->orderBy('name')->paginate($perPage);
        } else {
            $sponsors = $query->orderBy('name')->get();
        }

        return response()->json([
            'message' => 'Sponsors retrieved successfully',
            'data' => $sponsors
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/event-sponsors/{id}",
     *     summary="Get sponsor details",
     *     tags={"Event Sponsors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Sponsor details with associated events",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Sponsor not found")
     * )
     */
    public function show($id)
    {
        $sponsor = EventSponsor::with(['events' => function($query) {
            $query->published()->orderBy('event_date', 'desc');
        }])->findOrFail($id);

        return response()->json([
            'message' => 'Sponsor retrieved successfully',
            'data' => $sponsor
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/event-sponsors",
     *     summary="Create a new sponsor (auth required)",
     *     tags={"Event Sponsors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Red Bull"),
     *             @OA\Property(property="logo", type="string", example="https://example.com/logo.png"),
     *             @OA\Property(property="website", type="string", example="https://www.redbull.com"),
     *             @OA\Property(property="description", type="string", maxLength=1000, example="Main event sponsor")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Sponsor created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
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
            'name' => 'required|string|max:255|unique:event_sponsors,name',
            'logo' => 'nullable|string|max:500',
            'website' => 'nullable|url|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $sponsor = EventSponsor::create([
            'name' => $validated['name'],
            'logo' => $validated['logo'] ?? null,
            'website' => $validated['website'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'message' => 'Sponsor created successfully',
            'data' => $sponsor
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/event-sponsors/{id}",
     *     summary="Update a sponsor (auth required)",
     *     tags={"Event Sponsors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="logo", type="string"),
     *             @OA\Property(property="website", type="string"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Sponsor updated successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Sponsor not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $sponsor = EventSponsor::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:event_sponsors,name,' . $id,
            'logo' => 'nullable|string|max:500',
            'website' => 'nullable|url|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $sponsor->update($validated);

        return response()->json([
            'message' => 'Sponsor updated successfully',
            'data' => $sponsor->fresh()
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/event-sponsors/{id}",
     *     summary="Delete a sponsor (auth required)",
     *     tags={"Event Sponsors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Sponsor deleted successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Sponsor not found")
     * )
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $sponsor = EventSponsor::findOrFail($id);

        // Detach from all events before deleting
        $sponsor->events()->detach();
        $sponsor->delete();

        return response()->json([
            'message' => 'Sponsor deleted successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/events/{eventId}/sponsors/{sponsorId}/attach",
     *     summary="Attach sponsor to event (organizer only)",
     *     tags={"Event Sponsors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sponsorId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="sponsorship_level",
     *                 type="string",
     *                 enum={"platinum", "gold", "silver", "bronze"},
     *                 example="gold"
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Sponsor attached to event successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function attachToEvent(Request $request, $eventId, $sponsorId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);
        $sponsor = EventSponsor::findOrFail($sponsorId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can manage sponsors.'
            ], 403);
        }

        $validated = $request->validate([
            'sponsorship_level' => 'nullable|in:platinum,gold,silver,bronze',
        ]);

        // Check if already attached
        if ($event->sponsors()->where('event_sponsor_id', $sponsorId)->exists()) {
            return response()->json([
                'message' => 'Sponsor is already attached to this event'
            ], 400);
        }

        $event->sponsors()->attach($sponsorId, [
            'sponsorship_level' => $validated['sponsorship_level'] ?? null,
        ]);

        return response()->json([
            'message' => 'Sponsor attached to event successfully',
            'sponsor' => $sponsor
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/events/{eventId}/sponsors/{sponsorId}/detach",
     *     summary="Remove sponsor from event (organizer only)",
     *     tags={"Event Sponsors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sponsorId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Sponsor removed from event successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function detachFromEvent($eventId, $sponsorId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can manage sponsors.'
            ], 403);
        }

        $event->sponsors()->detach($sponsorId);

        return response()->json([
            'message' => 'Sponsor removed from event successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/sponsors",
     *     summary="Get event sponsors",
     *     tags={"Event Sponsors"},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="level", in="query", description="Filter by sponsorship level", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="List of event sponsors",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function eventSponsors(Request $request, $eventId)
    {
        $event = Event::findOrFail($eventId);

        $query = $event->sponsors();

        if ($request->has('level')) {
            $query->wherePivot('sponsorship_level', $request->level);
        }

        $sponsors = $query->get();

        return response()->json([
            'message' => 'Event sponsors retrieved successfully',
            'event' => [
                'id' => $event->id,
                'title' => $event->title
            ],
            'data' => $sponsors
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/events/{eventId}/sponsors/{sponsorId}/update-level",
     *     summary="Update sponsor level (organizer only)",
     *     tags={"Event Sponsors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sponsorId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"sponsorship_level"},
     *             @OA\Property(
     *                 property="sponsorship_level",
     *                 type="string",
     *                 enum={"platinum", "gold", "silver", "bronze"}
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Sponsorship level updated"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function updateSponsorLevel(Request $request, $eventId, $sponsorId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can update sponsor level.'
            ], 403);
        }

        $validated = $request->validate([
            'sponsorship_level' => 'required|in:platinum,gold,silver,bronze',
        ]);

        $event->sponsors()->updateExistingPivot($sponsorId, [
            'sponsorship_level' => $validated['sponsorship_level']
        ]);

        return response()->json([
            'message' => 'Sponsorship level updated successfully'
        ]);
    }
}
