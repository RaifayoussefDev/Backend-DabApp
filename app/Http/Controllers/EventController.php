<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventImage;
use App\Models\EventActivity;
use App\Models\EventContact;
use App\Models\EventFaq;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/events",
     *     summary="Get all events with filters",
     *     tags={"Events"},
     *     @OA\Response(response=200, description="List of events")
     * )
     */
    public function index(Request $request)
    {
        $query = Event::with(['category', 'city', 'country', 'organizer', 'images'])
            ->published();

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        if ($request->has('is_free')) {
            $query->where('is_free', $request->boolean('is_free'));
        }

        if ($request->has('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        if ($request->has('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        if ($request->has('date_from')) {
            $query->where('event_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('event_date', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('venue_name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $events = $query->orderBy('event_date', 'asc')->paginate($perPage);

        return response()->json([
            'message' => 'Events retrieved successfully',
            'data' => $events
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{id}",
     *     summary="Get event details with all relations",
     *     tags={"Events"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Event details with all relations")
     * )
     */
    public function show($id)
    {
        $query = Event::with([
            'category',
            'organizer',
            'city',
            'country',
            'images',
            'activities' => function($q) {
                $q->orderBy('order_position')->orderBy('start_time');
            },
            'tickets' => function($q) {
                $q->where('is_active', 1);
            },
            'sponsors',
            'contacts',
            'faqs' => function($q) {
                $q->orderBy('order_position');
            },
            'reviews' => function($query) {
                $query->approved()->with('user')->latest()->limit(10);
            },
            'updates' => function($q) {
                $q->latest()->limit(5);
            }
        ]);

        if (is_numeric($id)) {
            $event = $query->where('id', $id)->firstOrFail();
        } else {
            $event = $query->where('slug', $id)->firstOrFail();
        }

        $event->increment('views_count');

        $availableSpots = null;
        if ($event->max_participants) {
            $availableSpots = $event->max_participants - $event->participants_count;
        }

        return response()->json([
            'message' => 'Event retrieved successfully',
            'data' => $event,
            'is_registration_open' => $event->isRegistrationOpen(),
            'is_full' => $event->isFull(),
            'available_spots' => $availableSpots
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/events",
     *     summary="Create event with all relations (images, sponsors, activities, contacts, faqs)",
     *     tags={"Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","description","category_id","event_date","start_time"},
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="short_description", type="string"),
     *             @OA\Property(property="category_id", type="integer"),
     *             @OA\Property(property="event_date", type="string", format="date"),
     *             @OA\Property(property="start_time", type="string", format="time"),
     *             @OA\Property(property="end_time", type="string", format="time"),
     *             @OA\Property(property="venue_name", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="city_id", type="integer"),
     *             @OA\Property(property="country_id", type="integer"),
     *             @OA\Property(property="latitude", type="number"),
     *             @OA\Property(property="longitude", type="number"),
     *             @OA\Property(property="max_participants", type="integer"),
     *             @OA\Property(property="price", type="number"),
     *             @OA\Property(property="is_free", type="boolean"),
     *             @OA\Property(property="featured_image", type="string"),
     *             @OA\Property(
     *                 property="images",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="image_url", type="string"),
     *                     @OA\Property(property="is_primary", type="boolean")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="sponsors",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="sponsor_id", type="integer"),
     *                     @OA\Property(property="sponsorship_level", type="string", enum={"platinum","gold","silver","bronze"})
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="activities",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="start_time", type="string"),
     *                     @OA\Property(property="end_time", type="string"),
     *                     @OA\Property(property="location", type="string")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="contacts",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="contact_type", type="string", enum={"organizer","support","emergency"}),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="phone", type="string"),
     *                     @OA\Property(property="email", type="string")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="faqs",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="question", type="string"),
     *                     @OA\Property(property="answer", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Event created with all relations"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'short_description' => 'nullable|string|max:500',
            'category_id' => 'required|exists:event_categories,id',
            'event_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'nullable|date_format:H:i:s',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'venue_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'city_id' => 'nullable|exists:cities,id',
            'country_id' => 'nullable|exists:countries,id',
            'max_participants' => 'nullable|integer|min:1',
            'registration_deadline' => 'nullable|date|before:event_date',
            'price' => 'nullable|numeric|min:0',
            'is_free' => 'boolean',
            'featured_image' => 'nullable|string|max:500',

            // Images
            'images' => 'nullable|array',
            'images.*.image_url' => 'required|string|max:500',
            'images.*.is_primary' => 'nullable|boolean',

            // Sponsors
            'sponsors' => 'nullable|array',
            'sponsors.*.sponsor_id' => 'required|exists:event_sponsors,id',
            'sponsors.*.sponsorship_level' => 'nullable|in:platinum,gold,silver,bronze',

            // Activities
            'activities' => 'nullable|array',
            'activities.*.title' => 'required|string|max:255',
            'activities.*.description' => 'nullable|string',
            'activities.*.start_time' => 'nullable|date_format:H:i:s',
            'activities.*.end_time' => 'nullable|date_format:H:i:s',
            'activities.*.location' => 'nullable|string|max:255',

            // Contacts
            'contacts' => 'nullable|array',
            'contacts.*.contact_type' => 'required|in:organizer,support,emergency',
            'contacts.*.name' => 'nullable|string|max:255',
            'contacts.*.phone' => 'nullable|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',

            // FAQs
            'faqs' => 'nullable|array',
            'faqs.*.question' => 'required|string|max:500',
            'faqs.*.answer' => 'required|string|max:2000',
        ]);

        DB::beginTransaction();
        try {
            // Generate unique slug
            $slug = Str::slug($validated['title']);
            $originalSlug = $slug;
            $counter = 1;
            while (Event::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            // Create Event
            $event = Event::create([
                'title' => $validated['title'],
                'slug' => $slug,
                'description' => $validated['description'],
                'short_description' => $validated['short_description'] ?? null,
                'category_id' => $validated['category_id'],
                'organizer_id' => $user->id,
                'event_date' => $validated['event_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'] ?? null,
                'venue_name' => $validated['venue_name'] ?? null,
                'address' => $validated['address'] ?? null,
                'city_id' => $validated['city_id'] ?? null,
                'country_id' => $validated['country_id'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'max_participants' => $validated['max_participants'] ?? null,
                'registration_deadline' => $validated['registration_deadline'] ?? null,
                'price' => $validated['price'] ?? 0,
                'is_free' => $validated['is_free'] ?? false,
                'featured_image' => $validated['featured_image'] ?? null,
                'status' => 'upcoming',
                'is_published' => 1,
            ]);

            // Add Images
            if (!empty($validated['images'])) {
                foreach ($validated['images'] as $index => $imageData) {
                    EventImage::create([
                        'event_id' => $event->id,
                        'image_url' => $imageData['image_url'],
                        'is_primary' => $imageData['is_primary'] ?? false,
                        'order_position' => $index + 1,
                    ]);
                }
            }

            // Attach Sponsors
            if (!empty($validated['sponsors'])) {
                $sponsorData = [];
                foreach ($validated['sponsors'] as $sponsor) {
                    $sponsorData[$sponsor['sponsor_id']] = [
                        'sponsorship_level' => $sponsor['sponsorship_level'] ?? null
                    ];
                }
                $event->sponsors()->attach($sponsorData);
            }

            // Add Activities
            if (!empty($validated['activities'])) {
                foreach ($validated['activities'] as $index => $activityData) {
                    EventActivity::create([
                        'event_id' => $event->id,
                        'title' => $activityData['title'],
                        'description' => $activityData['description'] ?? null,
                        'start_time' => $activityData['start_time'] ?? null,
                        'end_time' => $activityData['end_time'] ?? null,
                        'location' => $activityData['location'] ?? null,
                        'order_position' => $index + 1,
                    ]);
                }
            }

            // Add Contacts
            if (!empty($validated['contacts'])) {
                foreach ($validated['contacts'] as $contactData) {
                    EventContact::create([
                        'event_id' => $event->id,
                        'contact_type' => $contactData['contact_type'],
                        'name' => $contactData['name'] ?? null,
                        'phone' => $contactData['phone'] ?? null,
                        'email' => $contactData['email'] ?? null,
                    ]);
                }
            }

            // Add FAQs
            if (!empty($validated['faqs'])) {
                foreach ($validated['faqs'] as $index => $faqData) {
                    EventFaq::create([
                        'event_id' => $event->id,
                        'question' => $faqData['question'],
                        'answer' => $faqData['answer'],
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
                'sponsors',
                'activities',
                'contacts',
                'faqs'
            ]);

            return response()->json([
                'message' => 'Event created successfully with all relations',
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
     *     path="/api/events/{id}",
     *     summary="Update event with all relations",
     *     tags={"Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Event updated with all relations"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $event = Event::findOrFail($id);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can update this event.'
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'short_description' => 'nullable|string|max:500',
            'category_id' => 'sometimes|exists:event_categories,id',
            'event_date' => 'sometimes|date',
            'start_time' => 'sometimes|date_format:H:i:s',
            'end_time' => 'nullable|date_format:H:i:s',
            'venue_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'city_id' => 'nullable|exists:cities,id',
            'country_id' => 'nullable|exists:countries,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'max_participants' => 'nullable|integer|min:1',
            'registration_deadline' => 'nullable|date',
            'price' => 'nullable|numeric|min:0',
            'is_free' => 'sometimes|boolean',
            'status' => 'sometimes|in:upcoming,ongoing,completed,cancelled',
            'is_featured' => 'sometimes|boolean',
            'is_published' => 'sometimes|boolean',
            'featured_image' => 'nullable|string|max:500',

            // Images (replace all)
            'images' => 'nullable|array',
            'images.*.image_url' => 'required|string|max:500',
            'images.*.is_primary' => 'nullable|boolean',

            // Sponsors (replace all)
            'sponsors' => 'nullable|array',
            'sponsors.*.sponsor_id' => 'required|exists:event_sponsors,id',
            'sponsors.*.sponsorship_level' => 'nullable|in:platinum,gold,silver,bronze',

            // Activities (replace all)
            'activities' => 'nullable|array',
            'activities.*.title' => 'required|string|max:255',
            'activities.*.description' => 'nullable|string',
            'activities.*.start_time' => 'nullable|date_format:H:i:s',
            'activities.*.end_time' => 'nullable|date_format:H:i:s',
            'activities.*.location' => 'nullable|string|max:255',

            // Contacts (replace all)
            'contacts' => 'nullable|array',
            'contacts.*.contact_type' => 'required|in:organizer,support,emergency',
            'contacts.*.name' => 'nullable|string|max:255',
            'contacts.*.phone' => 'nullable|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',

            // FAQs (replace all)
            'faqs' => 'nullable|array',
            'faqs.*.question' => 'required|string|max:500',
            'faqs.*.answer' => 'required|string|max:2000',
        ]);

        DB::beginTransaction();
        try {
            // Update slug if title changed
            if (isset($validated['title']) && $validated['title'] !== $event->title) {
                $slug = Str::slug($validated['title']);
                $originalSlug = $slug;
                $counter = 1;
                while (Event::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
                $validated['slug'] = $slug;
            }

            // Update Event
            $event->update($validated);

            // Update Images (replace all)
            if (isset($validated['images'])) {
                $event->images()->delete();
                foreach ($validated['images'] as $index => $imageData) {
                    EventImage::create([
                        'event_id' => $event->id,
                        'image_url' => $imageData['image_url'],
                        'is_primary' => $imageData['is_primary'] ?? false,
                        'order_position' => $index + 1,
                    ]);
                }
            }

            // Update Sponsors (replace all)
            if (isset($validated['sponsors'])) {
                $event->sponsors()->detach();
                $sponsorData = [];
                foreach ($validated['sponsors'] as $sponsor) {
                    $sponsorData[$sponsor['sponsor_id']] = [
                        'sponsorship_level' => $sponsor['sponsorship_level'] ?? null
                    ];
                }
                $event->sponsors()->attach($sponsorData);
            }

            // Update Activities (replace all)
            if (isset($validated['activities'])) {
                $event->activities()->delete();
                foreach ($validated['activities'] as $index => $activityData) {
                    EventActivity::create([
                        'event_id' => $event->id,
                        'title' => $activityData['title'],
                        'description' => $activityData['description'] ?? null,
                        'start_time' => $activityData['start_time'] ?? null,
                        'end_time' => $activityData['end_time'] ?? null,
                        'location' => $activityData['location'] ?? null,
                        'order_position' => $index + 1,
                    ]);
                }
            }

            // Update Contacts (replace all)
            if (isset($validated['contacts'])) {
                $event->contacts()->delete();
                foreach ($validated['contacts'] as $contactData) {
                    EventContact::create([
                        'event_id' => $event->id,
                        'contact_type' => $contactData['contact_type'],
                        'name' => $contactData['name'] ?? null,
                        'phone' => $contactData['phone'] ?? null,
                        'email' => $contactData['email'] ?? null,
                    ]);
                }
            }

            // Update FAQs (replace all)
            if (isset($validated['faqs'])) {
                $event->faqs()->delete();
                foreach ($validated['faqs'] as $index => $faqData) {
                    EventFaq::create([
                        'event_id' => $event->id,
                        'question' => $faqData['question'],
                        'answer' => $faqData['answer'],
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
                'sponsors',
                'activities',
                'contacts',
                'faqs'
            ]);

            return response()->json([
                'message' => 'Event updated successfully with all relations',
                'data' => $event
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $event = Event::findOrFail($id);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can delete this event.'
            ], 403);
        }

        if ($event->participants_count > 0) {
            return response()->json([
                'message' => 'Cannot delete event with registered participants. Please cancel the event instead.'
            ], 400);
        }

        $event->delete();

        return response()->json([
            'message' => 'Event deleted successfully'
        ]);
    }

    public function upcoming(Request $request)
    {
        $limit = $request->get('limit', 15);

        $events = Event::with(['category', 'city', 'country', 'organizer', 'images'])
            ->upcoming()
            ->published()
            ->orderBy('event_date', 'asc')
            ->limit($limit)
            ->get();

        return response()->json([
            'message' => 'Upcoming events retrieved successfully',
            'data' => $events
        ]);
    }

    public function featured(Request $request)
    {
        $limit = $request->get('limit', 10);

        $events = Event::with(['category', 'city', 'country', 'organizer', 'images'])
            ->featured()
            ->published()
            ->orderBy('event_date', 'asc')
            ->limit($limit)
            ->get();

        return response()->json([
            'message' => 'Featured events retrieved successfully',
            'data' => $events
        ]);
    }

    public function myOrganizedEvents(Request $request)
    {
        $user = Auth::user();

        $query = Event::with(['category', 'city', 'country', 'images'])
            ->where('organizer_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $events = $query->paginate(15);

        return response()->json([
            'message' => 'Your organized events retrieved successfully',
            'data' => $events
        ]);
    }

    public function statistics($id)
    {
        $user = Auth::user();
        $event = Event::findOrFail($id);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can view statistics.'
            ], 403);
        }

        $stats = [
            'total_participants' => $event->participants()->count(),
            'confirmed_participants' => $event->participants()->where('status', 'confirmed')->count(),
            'pending_participants' => $event->participants()->where('status', 'registered')->count(),
            'cancelled_participants' => $event->participants()->where('status', 'cancelled')->count(),
            'total_revenue' => $event->participants()->sum('payment_amount'),
            'tickets_sold' => $event->tickets()->sum('quantity_sold'),
            'average_rating' => round($event->reviews()->approved()->avg('rating'), 2),
            'total_reviews' => $event->reviews()->approved()->count(),
            'views_count' => $event->views_count,
            'favorites_count' => $event->favorites()->count(),
        ];

        return response()->json([
            'message' => 'Event statistics retrieved successfully',
            'data' => $stats
        ]);
    }

    public function togglePublish(Request $request, $id)
    {
        $user = Auth::user();
        $event = Event::findOrFail($id);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can publish/unpublish this event.'
            ], 403);
        }

        $validated = $request->validate([
            'is_published' => 'required|boolean'
        ]);

        $event->update(['is_published' => $validated['is_published']]);

        $message = $validated['is_published'] ? 'Event published successfully' : 'Event unpublished successfully';

        return response()->json([
            'message' => $message,
            'data' => $event
        ]);
    }
}
