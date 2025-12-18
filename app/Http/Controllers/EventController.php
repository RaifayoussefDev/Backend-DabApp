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

class EventController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/events",
     *     summary="Get all events with filters",
     *     tags={"Events"},
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"draft", "published", "cancelled"})
     *     ),
     *     @OA\Parameter(
     *         name="is_featured",
     *         in="query",
     *         description="Filter by featured status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="is_free",
     *         in="query",
     *         description="Filter by free events",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         description="Filter by country ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="city_id",
     *         in="query",
     *         description="Filter by city ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter events from this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter events until this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in title, description, and venue name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
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
        $user = auth()->user(); // ou auth('sanctum')->user() selon ton auth

        $query = Event::with(['category', 'city', 'country', 'organizer', 'images'])
            ->published();

        // Ajouter le compteur d'intéressés et vérifier si l'utilisateur est intéressé
        if ($user) {
            $query->withCount('interests')
                ->addSelect([
                    'is_interested' => EventInterest::selectRaw('1')
                        ->whereColumn('event_id', 'events.id')
                        ->where('user_id', $user->id)
                        ->limit(1)
                ]);
        } else {
            $query->withCount('interests');
        }

        // Tes filtres existants...
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
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('venue_name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $events = $query->orderBy('event_date', 'asc')->paginate($perPage);

        // Convertir is_interested en boolean
        $events->getCollection()->transform(function ($event) {
            $event->is_interested = (bool) $event->is_interested;
            return $event;
        });

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
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Event ID or slug",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event retrieved successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="is_registration_open", type="boolean"),
     *             @OA\Property(property="is_full", type="boolean"),
     *             @OA\Property(property="available_spots", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Event not found")
     * )
     */
    public function show($id)
    {
        $user = auth()->user();

        $query = Event::with([
            'category',
            'organizer',
            'city',
            'country',
            'images',
            'activities' => function ($q) {
                $q->orderBy('order_position')->orderBy('start_time');
            },
            'tickets' => function ($q) {
                $q->where('is_active', 1);
            },
            'sponsors',
            'contacts',
            'faqs' => function ($q) {
                $q->orderBy('order_position');
            },
            'reviews' => function ($query) {
                $query->approved()->with('user')->latest()->limit(10);
            },
            'updates' => function ($q) {
                $q->latest()->limit(5);
            }
        ])->withCount('interests');

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

        // Vérifier si l'utilisateur est intéressé
        $isInterested = false;
        if ($user) {
            $isInterested = EventInterest::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->exists();
        }

        return response()->json([
            'message' => 'Event retrieved successfully',
            'data' => $event,
            'is_registration_open' => $event->isRegistrationOpen(),
            'is_full' => $event->isFull(),
            'available_spots' => $availableSpots,
            'is_interested' => $isInterested,
            'interests_count' => $event->interests_count
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
     *             @OA\Property(property="title", type="string", example="Tech Conference 2024"),
     *             @OA\Property(property="description", type="string", example="Annual technology conference"),
     *             @OA\Property(property="short_description", type="string", example="Join us for the biggest tech event"),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="event_date", type="string", format="date", example="2024-12-25"),
     *             @OA\Property(property="start_time", type="string", format="time", example="09:00:00"),
     *             @OA\Property(property="end_time", type="string", format="time", example="17:00:00"),
     *             @OA\Property(property="venue_name", type="string", example="Convention Center"),
     *             @OA\Property(property="address", type="string", example="123 Main Street"),
     *             @OA\Property(property="city_id", type="integer", example=1),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="latitude", type="number", format="float", example=40.7128),
     *             @OA\Property(property="longitude", type="number", format="float", example=-74.0060),
     *             @OA\Property(property="max_participants", type="integer", example=500),
     *             @OA\Property(property="price", type="number", format="float", example=99.99),
     *             @OA\Property(property="is_free", type="boolean", example=false),
     *             @OA\Property(property="featured_image", type="string", example="https://example.com/image.jpg"),
     *             @OA\Property(
     *                 property="images",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="image_url", type="string", example="https://example.com/gallery1.jpg"),
     *                     @OA\Property(property="is_primary", type="boolean", example=true)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="sponsors",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="sponsor_id", type="integer", example=1),
     *                     @OA\Property(property="sponsorship_level", type="string", enum={"platinum","gold","silver","bronze"}, example="gold")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="activities",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="title", type="string", example="Opening Keynote"),
     *                     @OA\Property(property="description", type="string", example="Welcome speech and introduction"),
     *                     @OA\Property(property="start_time", type="string", format="time", example="09:00:00"),
     *                     @OA\Property(property="end_time", type="string", format="time", example="10:00:00"),
     *                     @OA\Property(property="location", type="string", example="Main Hall")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="contacts",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="contact_type", type="string", enum={"organizer","support","emergency"}, example="organizer"),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="phone", type="string", example="+1234567890"),
     *                     @OA\Property(property="email", type="string", format="email", example="john@example.com")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="faqs",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="question", type="string", example="What should I bring?"),
     *                     @OA\Property(property="answer", type="string", example="Bring your ID and confirmation email")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Event created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event created successfully with all relations"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Failed to create event")
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
            'sponsors.*.sponsor_id' => 'required|exists:sponsors,id',
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
        ]);

        DB::beginTransaction();

        try {
            $user = Auth::user();

            $eventData = array_merge($validated, [
                'organizer_id' => $user->id,
                'slug' => Str::slug($validated['title']) . '-' . uniqid(),
                'status' => 'draft',
                'is_published' => false,
                'is_featured' => false,
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
                $event->sponsors()->attach($sponsorData);
            }

            // Create activities
            if (isset($validated['activities'])) {
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

            // Create contacts
            if (isset($validated['contacts'])) {
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

            // Create FAQs
            if (isset($validated['faqs'])) {
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
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Event ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
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
     *             @OA\Property(property="images", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="sponsors", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="activities", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="contacts", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="faqs", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event updated successfully with all relations"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Event not found"),
     *     @OA\Response(response=422, description="Validation error")
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
            'sponsors.*.sponsor_id' => 'required|exists:sponsors,id',
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
        ]);

        DB::beginTransaction();

        try {
            // Update basic event data
            $eventData = $validated;
            unset($eventData['images'], $eventData['sponsors'], $eventData['activities'], $eventData['contacts'], $eventData['faqs']);

            if (isset($validated['title']) && $validated['title'] !== $event->title) {
                $eventData['slug'] = Str::slug($validated['title']) . '-' . uniqid();
            }

            $event->update($eventData);

            // Update Images (replace all)
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

    /**
     * @OA\Delete(
     *     path="/api/events/{id}",
     *     summary="Delete an event",
     *     tags={"Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Event ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot delete event with registered participants"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Event not found")
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/events/upcoming",
     *     summary="Get upcoming events",
     *     tags={"Events"},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of events to return",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Upcoming events retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Upcoming events retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/events/featured",
     *     summary="Get featured events",
     *     tags={"Events"},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of events to return",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Featured events retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Featured events retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/events/my-organized",
     *     summary="Get events organized by authenticated user",
     *     tags={"Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by event status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"draft", "published", "cancelled"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Your organized events retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Your organized events retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/events/{id}/statistics",
     *     summary="Get event statistics",
     *     tags={"Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Event ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event statistics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_participants", type="integer"),
     *                 @OA\Property(property="confirmed_participants", type="integer"),
     *                 @OA\Property(property="pending_participants", type="integer"),
     *                 @OA\Property(property="cancelled_participants", type="integer"),
     *                 @OA\Property(property="total_revenue", type="number"),
     *                 @OA\Property(property="tickets_sold", type="integer"),
     *                 @OA\Property(property="average_rating", type="number"),
     *                 @OA\Property(property="total_reviews", type="integer"),
     *                 @OA\Property(property="views_count", type="integer"),
     *                 @OA\Property(property="favorites_count", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Event not found")
     * )
     */
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

    /**
     * @OA\Patch(
     *     path="/api/events/{id}/toggle-publish",
     *     summary="Publish or unpublish an event",
     *     tags={"Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Event ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_published"},
     *             @OA\Property(property="is_published", type="boolean", example=true, description="Set to true to publish, false to unpublish")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event publish status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event published successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Event not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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

    /**
     * @OA\Patch(
     *     path="/api/events/{id}/toggle-featured",
     *     summary="Feature or unfeature an event",
     *     tags={"Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Event ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_featured"},
     *             @OA\Property(property="is_featured", type="boolean", example=true, description="Set to true to feature, false to unfeature")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event featured status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event featured successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Event not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function toggleFeatured(Request $request, $id)
    {
        $user = Auth::user();
        $event = Event::findOrFail($id);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can feature/unfeature this event.'
            ], 403);
        }

        $validated = $request->validate([
            'is_featured' => 'required|boolean'
        ]);

        $event->update(['is_featured' => $validated['is_featured']]);

        $message = $validated['is_featured'] ? 'Event featured successfully' : 'Event unfeatured successfully';

        return response()->json([
            'message' => $message,
            'data' => $event
        ]);
    }
    /**
     * @OA\Post(
     *     path="/api/events/{id}/toggle-interest",
     *     summary="Add or remove user interest in an event",
     *     description="Toggle user's interest status for an event. If interested, removes interest. If not interested, adds interest.",
     *     tags={"Events - Interests"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Event ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Interest toggled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Interest added successfully"),
     *             @OA\Property(property="is_interested", type="boolean", example=true),
     *             @OA\Property(property="interests_count", type="integer", example=25)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Event not found")
     * )
     */
    public function toggleInterest($id)
    {
        $user = Auth::user();
        $event = Event::findOrFail($id);

        $interest = EventInterest::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if ($interest) {
            // Remove interest
            $interest->delete();
            $event->decrement('interests_count');
            $message = 'Interest removed successfully';
            $isInterested = false;
        } else {
            // Add interest
            EventInterest::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
            ]);
            $event->increment('interests_count');
            $message = 'Interest added successfully';
            $isInterested = true;
        }

        return response()->json([
            'message' => $message,
            'is_interested' => $isInterested,
            'interests_count' => $event->fresh()->interests_count
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{id}/interested-users",
     *     summary="Get list of users interested in an event",
     *     description="Retrieve paginated list of all users who marked their interest in this event",
     *     tags={"Events - Interests"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Event ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=20, example=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Interested users retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Interested users retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=20),
     *                 @OA\Property(property="total", type="integer", example=45),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="first_name", type="string", example="John"),
     *                         @OA\Property(property="last_name", type="string", example="Doe"),
     *                         @OA\Property(property="profile_picture", type="string", example="https://example.com/avatar.jpg"),
     *                         @OA\Property(property="interested_at", type="string", format="date-time", example="2024-12-12 10:30:00")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Event not found")
     * )
     */
    public function getInterestedUsers($id, Request $request)
    {
        $event = Event::findOrFail($id);
        $perPage = $request->get('per_page', 20);

        $interestedUsers = $event->interestedUsers()
            ->select('users.*', 'event_interests.created_at as interested_at')
            ->paginate($perPage);

        return response()->json([
            'message' => 'Interested users retrieved successfully',
            'data' => $interestedUsers
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/my-interests",
     *     summary="Get events current user is interested in",
     *     description="Retrieve list of all events that the authenticated user has marked as interested",
     *     tags={"Events - Interests"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User interested events retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Your interested events retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=8),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="Tech Conference 2024"),
     *                         @OA\Property(property="title_ar", type="string", example="مؤتمر التقنية 2024"),
     *                         @OA\Property(property="event_date", type="string", format="date", example="2024-12-25"),
     *                         @OA\Property(property="venue_name", type="string", example="Convention Center"),
     *                         @OA\Property(property="interests_count", type="integer", example=25),
     *                         @OA\Property(property="participants_count", type="integer", example=150)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function myInterests(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->get('per_page', 15);

        $events = Event::whereHas('interests', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->with(['category', 'city', 'country', 'images'])
            ->orderBy('event_date', 'asc')
            ->paginate($perPage);

        return response()->json([
            'message' => 'Your interested events retrieved successfully',
            'data' => $events
        ]);
    }
}
