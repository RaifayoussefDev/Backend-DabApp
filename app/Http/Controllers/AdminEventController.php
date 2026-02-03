<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventImage;
use App\Models\EventActivity;
use App\Models\EventContact;
use App\Models\EventFaq;
use App\Models\EventInterest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;

class AdminEventController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * @OA\Get(
     *     path="/api/admin/events",
     *     summary="Admin: Get all events with filters",
     *     tags={"Admin Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="category_id", in="query", description="Filter by category ID", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", description="Filter by status", required=false, @OA\Schema(type="string", enum={"draft", "published", "cancelled"})),
     *     @OA\Parameter(name="is_featured", in="query", description="Filter by featured status", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="organizer_id", in="query", description="Filter by organizer ID", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", description="Search in title, description, venue", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="page", in="query", description="Page number", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Response(
     *         response=200,
     *         description="List of events retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Events retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Event::with(['category', 'city', 'country', 'organizer', 'organizerProfile', 'images']);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        if ($request->has('organizer_id')) {
            $query->where('organizer_id', $request->organizer_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('venue_name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $events = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'message' => 'Events retrieved successfully',
            'data' => $events
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/events/{id}",
     *     summary="Admin: Get event details",
     *     tags={"Admin Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", description="Event ID", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Event details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Event not found")
     * )
     */
    public function show($id)
    {
        $event = Event::with([
            'category',
            'organizer',
            'organizerProfile',
            'city',
            'city',
            'country',
            'images',
            'activities',
            'tickets',
            'sponsors',
            'contacts',
            'faqs',
            'reviews.user',
            'updates'
        ])->withCount('interests')->findOrFail($id);

        return response()->json([
            'message' => 'Event retrieved successfully',
            'data' => $event
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/events",
     *     summary="Admin: Create a new event",
     *     tags={"Admin Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="object")),
     *     @OA\Response(response=201, description="Event created successfully")
     * )
     */
    public function store(Request $request)
    {
        // Similar validation to EventController but flexible for admin
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:event_categories,id',
            'event_date' => 'required|date',
            'start_time' => 'required',
            // ... add other necessary fields validation or keep minimal for admin flexibility
            'organizer_id' => 'nullable|exists:users,id', // Admin can assign organizer user
            'organizer_profile_id' => 'nullable|exists:organizers,id' // Admin can assign organizer profile
        ]);

        DB::beginTransaction();

        try {
            $admin = Auth::user();
            $organizerId = $request->organizer_id ?? $admin->id;

            $eventData = array_merge($request->except(['images', 'sponsors', 'activities', 'contacts', 'faqs']), [
                'organizer_id' => $organizerId,
                'organizer_profile_id' => $request->organizer_profile_id,
                'slug' => Str::slug($request->title) . '-' . uniqid(),
                'status' => $request->status ?? 'draft',
                'is_published' => $request->boolean('is_published', false),
            ]);

            $event = Event::create($eventData);

            // Handle relations (simplified for brevity, can be expanded like EventController)
            // ...

            DB::commit();

            return response()->json([
                'message' => 'Event created successfully by admin',
                'data' => $event
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create event', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/admin/events/{id}",
     *     summary="Admin: Update an event",
     *     tags={"Admin Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", description="Event ID", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="object")),
     *     @OA\Response(response=200, description="Event updated successfully")
     * )
     */
    public function update(Request $request, $id)
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'organizer_profile_id' => 'nullable|exists:organizers,id'
            // Add other validations as needed
        ]);

        DB::beginTransaction();

        try {
            $event->update($request->except(['images', 'sponsors', 'activities', 'contacts', 'faqs']));

            // Update relations logic here if needed...

            DB::commit();

            return response()->json([
                'message' => 'Event updated successfully',
                'data' => $event
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update event', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/events/{id}",
     *     summary="Admin: Delete an event",
     *     tags={"Admin Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", description="Event ID", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Event deleted successfully")
     * )
     */
    public function destroy($id)
    {
        $event = Event::findOrFail($id);
        $event->delete();

        return response()->json(['message' => 'Event deleted successfully']);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/events/{id}/toggle-publish",
     *     summary="Admin: Toggle event publish status",
     *     tags={"Admin Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", description="Event ID", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="is_published", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Status updated successfully")
     * )
     */
    public function togglePublish(Request $request, $id)
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'is_published' => 'required|boolean'
        ]);

        $event->update([
            'is_published' => $validated['is_published'],
            'status' => $validated['is_published'] ? 'published' : 'draft'
        ]);

        if ($validated['is_published']) {
            try {
                // Ensure event_date is handled safely as per previous fixes
                $this->notificationService->notifyEventPublished($event->organizer, $event);
            } catch (\Exception $e) {
                \Log::error('Admin: Failed to send event published notification: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Event publish status updated',
            'data' => $event
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/events/{id}/approve",
     *     summary="Admin: Approve an event",
     *     tags={"Admin Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", description="Event ID", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Event approved")
     * )
     */
    public function approve($id)
    {
        $event = Event::findOrFail($id);
        $event->update(['status' => 'published', 'is_published' => true]);

        // Send Notification
        try {
            $this->notificationService->notifyEventPublished($event->organizer, $event);
        } catch (\Exception $e) {
            \Log::error('Admin: Failed to send approval notification: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Event approved and published', 'data' => $event]);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/events/{id}/reject",
     *     summary="Admin: Reject an event",
     *     tags={"Admin Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", description="Event ID", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="rejection_reason", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Event rejected")
     * )
     */
    public function reject(Request $request, $id)
    {
        $event = Event::findOrFail($id);
        $event->update(['status' => 'rejected', 'is_published' => false]);

        // Logic to save rejection reason if applicable

        return response()->json(['message' => 'Event rejected', 'data' => $event]);
    }
}
