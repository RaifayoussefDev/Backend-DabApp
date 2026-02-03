<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventImage;
use App\Models\EventActivity;
use App\Models\EventContact;
use App\Models\EventFaq;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;
// Admin specific needs if any, generally same as EventController

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
     *     summary="Admin: Get all events",
     *     tags={"Admin Events"},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"draft", "published", "cancelled"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="List of events",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Event")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Event::with(['organizer', 'category', 'city', 'country', 'images', 'organizerProfile']) // Added organizerProfile
            ->latest();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $events = $query->paginate($request->get('per_page', 15));

        return response()->json($events);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/events/{id}",
     *     summary="Admin: Get event details",
     *     tags={"Admin Events"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Event details")
     * )
     */
    public function show($id)
    {
        $event = Event::with([
            'category',
            'organizer',
            'city',
            'country',
            'images',
            'sponsors',
            'activities',
            'contacts',
            'faqs',
            'organizerProfile' // Added organizerProfile
        ])->findOrFail($id);

        return response()->json(['data' => $event]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/events",
     *     summary="Admin: Create a new event with all relations",
     *     tags={"Admin Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","description","category_id","event_date","start_time"},
     *             @OA\Property(property="organizer_profile_id", type="integer", example=1, description="ID of the organizer profile (Admin only)"),
     *             @OA\Property(property="title", type="string", maxLength=255, example="Tech Conference 2024"),
     *             @OA\Property(property="title_ar", type="string", maxLength=255, example="مؤتمر التقنية 2024"),
     *             @OA\Property(property="description", type="string", example="Annual technology conference"),
     *             @OA\Property(property="description_ar", type="string", example="مؤتمر تكنولوجي سنوي"),
     *             @OA\Property(property="short_description", type="string", maxLength=500),
     *             @OA\Property(property="short_description_ar", type="string", maxLength=500),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="event_date", type="string", format="date", example="2024-12-25"),
     *             @OA\Property(property="start_time", type="string", format="time", example="09:00:00"),
     *             @OA\Property(property="end_time", type="string", format="time", example="17:00:00"),
     *             @OA\Property(property="venue_name", type="string", example="Grand Convention Center"),
     *             @OA\Property(property="venue_name_ar", type="string", example="مركز المؤتمرات الكبير"),
     *             @OA\Property(property="address", type="string", example="123 Main St"),
     *             @OA\Property(property="address_ar", type="string", example="123 الشارع الرئيسي"),
     *             @OA\Property(property="city_id", type="integer", example=1),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="latitude", type="number", format="double", example=40.7128),
     *             @OA\Property(property="longitude", type="number", format="double", example=-74.0060),
     *             @OA\Property(property="max_participants", type="integer", minimum=1, example=500),
     *             @OA\Property(property="price", type="number", format="double", minimum=0, example=99.99),
     *             @OA\Property(property="is_free", type="boolean", example=false),
     *             @OA\Property(property="featured_image", type="string", format="url", example="https://example.com/image.jpg"),
     *             @OA\Property(property="images", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="image_url", type="string", example="https://example.com/1.jpg"),
     *                 @OA\Property(property="is_primary", type="boolean", example=true)
     *             )),
     *             @OA\Property(property="sponsors", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="sponsor_id", type="integer", example=1),
     *                 @OA\Property(property="sponsorship_level", type="string", example="gold")
     *             )),
     *             @OA\Property(property="activities", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="title", type="string", example="Opening Ceremony"),
     *                 @OA\Property(property="start_time", type="string", example="09:00:00")
     *             )),
     *             @OA\Property(property="contacts", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="contact_type", type="string", example="organizer"),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com")
     *             )),
     *             @OA\Property(property="faqs", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="question", type="string", example="Is parking free?"),
     *                 @OA\Property(property="answer", type="string", example="Yes.")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Event created successfully")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'description' => 'required|string',
            'description_ar' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'short_description_ar' => 'nullable|string|max:500',
            'category_id' => 'required|exists:event_categories,id',
            'event_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'nullable',
            'venue_name' => 'nullable|string|max:255',
            'venue_name_ar' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'address_ar' => 'nullable|string',
            'city_id' => 'nullable|exists:cities,id',
            'country_id' => 'nullable|exists:countries,id',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'max_participants' => 'nullable|integer|min:1',
            'price' => 'nullable|numeric|min:0',
            'is_free' => 'boolean',
            'featured_image' => 'nullable|url',
            'images' => 'nullable|array',
            'images.*.image_url' => 'required|url',
            'images.*.is_primary' => 'boolean',
            'sponsors' => 'nullable|array',
            'sponsors.*.sponsor_id' => 'required|exists:event_sponsors,id',
            'sponsors.*.sponsorship_level' => 'nullable|in:platinum,gold,silver,bronze',
            'activities' => 'nullable|array',
            'activities.*.title' => 'required|string|max:255',
            'activities.*.title_ar' => 'nullable|string|max:255',
            'activities.*.description' => 'nullable|string',
            'activities.*.description_ar' => 'nullable|string',
            'activities.*.start_time' => 'nullable',
            'activities.*.end_time' => 'nullable',
            'activities.*.location' => 'nullable|string|max:255',
            'activities.*.location_ar' => 'nullable|string|max:255',
            'activities.*.day_in_event' => 'nullable|integer|min:1',
            'contacts' => 'nullable|array',
            'contacts.*.contact_type' => 'required|in:organizer,support,emergency',
            'contacts.*.name' => 'nullable|string|max:255',
            'contacts.*.name_ar' => 'nullable|string|max:255',
            'contacts.*.phone' => 'nullable|string|max:50',
            'contacts.*.email' => 'nullable|email|max:255',
            'faqs' => 'nullable|array',
            'faqs.*.question' => 'required|string',
            'faqs.*.question_ar' => 'nullable|string',
            'faqs.*.answer' => 'required|string',
            'faqs.*.answer_ar' => 'nullable|string',
            // Admin specific
            'organizer_profile_id' => 'nullable|exists:organizers,id',
            'organizer_id' => 'nullable|exists:users,id',
        ]);

        DB::beginTransaction();

        try {
            $user = Auth::user();
            $organizerId = $request->organizer_id ?? $user->id; // Use request organizer_id or current admin

            $eventData = array_merge($validated, [
                'organizer_id' => $organizerId,
                'organizer_profile_id' => $request->organizer_profile_id,
                'slug' => Str::slug($validated['title']) . '-' . uniqid(),
                'status' => $request->status ?? 'draft', // Admin might set status directly
                'is_published' => $request->boolean('is_published', false),
                'is_featured' => $request->boolean('is_featured', false),
            ]);

            unset($eventData['images'], $eventData['sponsors'], $eventData['activities'], $eventData['contacts'], $eventData['faqs']);

            $event = Event::create($eventData);

            // Create images
            if (isset($validated['images'])) {
                foreach ($validated['images'] as $imageData) {
                    EventImage::create([
                        'event_id' => $event->id,
                        'image_url' => $imageData['image_url'],
                        'is_primary' => $imageData['is_primary'] ?? false,
                    ]);
                }
            }

            // Attach sponsors
            if (isset($validated['sponsors'])) {
                $sponsorData = [];
                foreach ($validated['sponsors'] as $sponsor) {
                    $sponsorData[$sponsor['sponsor_id']] = [
                        'sponsorship_level' => $sponsor['sponsorship_level'] ?? null
                    ];
                }
                $event->eventSponsors()->attach($sponsorData);
            }

            // Create activities
            if (isset($validated['activities'])) {
                foreach ($validated['activities'] as $index => $activityData) {
                    EventActivity::create([
                        'event_id' => $event->id,
                        'title' => $activityData['title'],
                        'title_ar' => $activityData['title_ar'] ?? null,
                        'description' => $activityData['description'] ?? null,
                        'description_ar' => $activityData['description_ar'] ?? null,
                        'start_time' => $activityData['start_time'] ?? null,
                        'end_time' => $activityData['end_time'] ?? null,
                        'location' => $activityData['location'] ?? null,
                        'location_ar' => $activityData['location_ar'] ?? null,
                        'day_in_event' => $activityData['day_in_event'] ?? null,
                        'order_position' => $index + 1,
                    ]);
                }
            }

            // Create contacts
            if (isset($validated['contacts'])) {
                foreach ($validated['contacts'] as $contactData) {
                    EventContact::create([
                        'event_id' => $event->id,
                        'contact_type' => $contactData['contact_type'],
                        'name' => $contactData['name'] ?? null,
                        'name_ar' => $contactData['name_ar'] ?? null,
                        'phone' => $contactData['phone'] ?? null,
                        'email' => $contactData['email'] ?? null,
                    ]);
                }
            }

            // Create FAQs
            if (isset($validated['faqs'])) {
                foreach ($validated['faqs'] as $index => $faqData) {
                    EventFaq::create([
                        'event_id' => $event->id,
                        'question' => $faqData['question'],
                        'question_ar' => $faqData['question_ar'] ?? null,
                        'answer' => $faqData['answer'],
                        'answer_ar' => $faqData['answer_ar'] ?? null,
                        'order_position' => $index + 1,
                    ]);
                }
            }

            DB::commit();

            // Send Notification (Optional for Admin but good for consistency if they are creating for a user)
            try {
                $event->refresh();
                // We might notify the assignee if organizer_id != admin id, but simplest is to keep logic or skip.
                // Keeping logic from EventController for now as requested "meme chose"
                // $this->notificationService->notifyEventCreated($user, $event); 
            } catch (\Exception $e) {
                \Log::error('Failed to send event created notification: ' . $e->getMessage());
            }

            // Load all relations
            $event->load([
                'category',
                'organizer',
                'city',
                'country',
                'images',
                'eventSponsors',
                'activities',
                'contacts',
                'faqs',
                'organizerProfile'
            ]);

            return response()->json([
                'message' => 'Event created successfully by admin',
                'data' => $event
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/admin/events/{id}",
     *     summary="Admin: Update an event with all relations",
     *     tags={"Admin Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="organizer_profile_id", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Updated Tech Conference"),
     *             @OA\Property(property="description", type="string", example="Updated description"),
     *             @OA\Property(property="event_date", type="string", format="date", example="2024-12-26"),
     *             @OA\Property(property="featured_image", type="string", format="url", example="https://example.com/new-image.jpg"),
     *             @OA\Property(property="images", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="image_url", type="string", example="https://example.com/2.jpg"),
     *                 @OA\Property(property="is_primary", type="boolean", example=true)
     *             )),
     *             @OA\Property(property="status", type="string", enum={"draft", "published", "cancelled"}, example="published"),
     *             @OA\Property(property="is_published", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Event updated successfully")
     * )
     */
    public function update(Request $request, $id)
    {
        $event = Event::findOrFail($id);

        // Full validation as EventController + organizer_profile_id
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'description' => 'sometimes|required|string',
            'description_ar' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'short_description_ar' => 'nullable|string|max:500',
            'category_id' => 'sometimes|required|exists:event_categories,id',
            'event_date' => 'sometimes|required|date',
            'start_time' => 'sometimes|required',
            'end_time' => 'nullable',
            'venue_name' => 'nullable|string|max:255',
            'venue_name_ar' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'address_ar' => 'nullable|string',
            'city_id' => 'nullable|exists:cities,id',
            'country_id' => 'nullable|exists:countries,id',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'max_participants' => 'nullable|integer|min:1',
            'price' => 'nullable|numeric|min:0',
            'is_free' => 'boolean',
            'featured_image' => 'nullable|url',
            'images' => 'nullable|array',
            'images.*.image_url' => 'required|url',
            'images.*.is_primary' => 'boolean',
            'sponsors' => 'nullable|array',
            'sponsors.*.sponsor_id' => 'required|exists:event_sponsors,id',
            'sponsors.*.sponsorship_level' => 'nullable|in:platinum,gold,silver,bronze',
            'activities' => 'nullable|array',
            'activities.*.title' => 'required|string|max:255',
            'activities.*.title_ar' => 'nullable|string|max:255',
            'activities.*.description' => 'nullable|string',
            'activities.*.description_ar' => 'nullable|string',
            'activities.*.start_time' => 'nullable',
            'activities.*.end_time' => 'nullable',
            'activities.*.location' => 'nullable|string|max:255',
            'activities.*.location_ar' => 'nullable|string|max:255',
            'activities.*.day_in_event' => 'nullable|integer|min:1',
            'contacts' => 'nullable|array',
            'contacts.*.contact_type' => 'required|in:organizer,support,emergency',
            'contacts.*.name' => 'nullable|string|max:255',
            'contacts.*.name_ar' => 'nullable|string|max:255',
            'contacts.*.phone' => 'nullable|string|max:50',
            'contacts.*.email' => 'nullable|email|max:255',
            'faqs' => 'nullable|array',
            'faqs.*.question' => 'required|string',
            'faqs.*.question_ar' => 'nullable|string',
            'faqs.*.answer' => 'required|string',
            'faqs.*.answer_ar' => 'nullable|string',
            // Admin specific
            'organizer_profile_id' => 'nullable|exists:organizers,id',
            // Admin might update organizer_id
            'organizer_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:draft,published,cancelled,pending',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
        ]);

        DB::beginTransaction();

        try {
            $eventData = $validated;
            unset($eventData['images'], $eventData['sponsors'], $eventData['activities'], $eventData['contacts'], $eventData['faqs']);

            if (isset($validated['title']) && $validated['title'] !== $event->title) {
                $eventData['slug'] = Str::slug($validated['title']) . '-' . uniqid();
            }

            $event->update($eventData);

            // Update Images
            if (isset($validated['images'])) {
                $event->images()->delete();
                foreach ($validated['images'] as $imageData) {
                    EventImage::create([
                        'event_id' => $event->id,
                        'image_url' => $imageData['image_url'],
                        'is_primary' => $imageData['is_primary'] ?? false,
                    ]);
                }
            }

            // Update Sponsors
            if (isset($validated['sponsors'])) {
                $event->eventSponsors()->detach();
                $sponsorData = [];
                foreach ($validated['sponsors'] as $sponsor) {
                    $sponsorData[$sponsor['sponsor_id']] = [
                        'sponsorship_level' => $sponsor['sponsorship_level'] ?? null
                    ];
                }
                $event->eventSponsors()->attach($sponsorData);
            }

            // Update Activities
            if (isset($validated['activities'])) {
                $event->activities()->delete();
                foreach ($validated['activities'] as $index => $activityData) {
                    EventActivity::create([
                        'event_id' => $event->id,
                        'title' => $activityData['title'],
                        'title_ar' => $activityData['title_ar'] ?? null,
                        'description' => $activityData['description'] ?? null,
                        'description_ar' => $activityData['description_ar'] ?? null,
                        'start_time' => $activityData['start_time'] ?? null,
                        'end_time' => $activityData['end_time'] ?? null,
                        'location' => $activityData['location'] ?? null,
                        'location_ar' => $activityData['location_ar'] ?? null,
                        'day_in_event' => $activityData['day_in_event'] ?? null,
                        'order_position' => $index + 1,
                    ]);
                }
            }

            // Update Contacts
            if (isset($validated['contacts'])) {
                $event->contacts()->delete();
                foreach ($validated['contacts'] as $contactData) {
                    EventContact::create([
                        'event_id' => $event->id,
                        'contact_type' => $contactData['contact_type'],
                        'name' => $contactData['name'] ?? null,
                        'name_ar' => $contactData['name_ar'] ?? null,
                        'phone' => $contactData['phone'] ?? null,
                        'email' => $contactData['email'] ?? null,
                    ]);
                }
            }

            // Update FAQs
            if (isset($validated['faqs'])) {
                $event->faqs()->delete();
                foreach ($validated['faqs'] as $index => $faqData) {
                    EventFaq::create([
                        'event_id' => $event->id,
                        'question' => $faqData['question'],
                        'question_ar' => $faqData['question_ar'] ?? null,
                        'answer' => $faqData['answer'],
                        'answer_ar' => $faqData['answer_ar'] ?? null,
                        'order_position' => $index + 1,
                    ]);
                }
            }

            DB::commit();

            // Load all relations
            $event->load([
                'category',
                'organizer',
                'city',
                'country',
                'images',
                'eventSponsors',
                'activities',
                'contacts',
                'faqs',
                'organizerProfile'
            ]);

            return response()->json([
                'message' => 'Event updated successfully with all relations by admin',
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
     *     @OA\Response(response=200, description="Status updated")
     * )
     */
    public function togglePublish($id)
    {
        $event = Event::findOrFail($id);
        $event->is_published = !$event->is_published;
        $event->status = $event->is_published ? 'published' : 'draft';
        $event->save();

        if ($event->is_published) {
            try {
                // $this->notificationService->notifyEventPublished($event->organizer, $event);
            } catch (\Exception $e) {
            }
        }

        return response()->json(['message' => 'Event publish status updated', 'data' => $event]);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/events/{id}/approve",
     *     summary="Admin: Approve an event",
     *     tags={"Admin Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Event approved")
     * )
     */
    public function approve($id)
    {
        $event = Event::findOrFail($id);
        $event->status = 'published'; // or 'approved' if you have approved status
        $event->is_published = true;
        $event->save();

        return response()->json(['message' => 'Event approved and published', 'data' => $event]);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/events/{id}/reject",
     *     summary="Admin: Reject an event",
     *     tags={"Admin Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="rejection_reason", type="string"))),
     *     @OA\Response(response=200, description="Event rejected")
     * )
     */
    public function reject(Request $request, $id)
    {
        $event = Event::findOrFail($id);
        $event->status = 'rejected';
        $event->is_published = false;
        // $event->rejection_reason = $request->rejection_reason; // If you have this column
        $event->save();

        return response()->json(['message' => 'Event rejected', 'data' => $event]);
    }
}
