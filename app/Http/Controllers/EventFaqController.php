<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventFaq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EventFaqController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/faqs",
     *     summary="Get event FAQs",
     *     tags={"Event FAQs"},
     *     @OA\Parameter(
     *         name="eventId",
     *         in="path",
     *         required=true,
     *         description="Event ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of FAQs",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="FAQs retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="event_id", type="integer", example=5),
     *                     @OA\Property(property="question", type="string", example="Where can I park?"),
     *                     @OA\Property(property="answer", type="string", example="Free parking available at main entrance"),
     *                     @OA\Property(property="order_position", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Event not found")
     * )
     */
    public function index($eventId)
    {
        $event = Event::findOrFail($eventId);

        $faqs = EventFaq::where('event_id', $eventId)
            ->orderBy('order_position', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'message' => 'FAQs retrieved successfully',
            'event' => [
                'id' => $event->id,
                'title' => $event->title
            ],
            'data' => $faqs,
            'total' => $faqs->count()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/faqs/{faqId}",
     *     summary="Get single FAQ details",
     *     tags={"Event FAQs"},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="faqId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="FAQ details",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="FAQ not found")
     * )
     */
    public function show($eventId, $faqId)
    {
        $faq = EventFaq::where('event_id', $eventId)
            ->where('id', $faqId)
            ->firstOrFail();

        return response()->json([
            'message' => 'FAQ retrieved successfully',
            'data' => $faq
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/events/{eventId}/faqs",
     *     summary="Add FAQ to event (organizer only)",
     *     tags={"Event FAQs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"question","answer"},
     *             @OA\Property(property="question", type="string", example="Where can I park?", maxLength=500),
     *             @OA\Property(property="answer", type="string", example="Free parking available at main entrance", maxLength=2000),
     *             @OA\Property(property="order_position", type="integer", example=1, description="Display order (optional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="FAQ created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="FAQ created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized - Not the event organizer"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, $eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can add FAQs.'
            ], 403);
        }

        $validated = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string|max:2000',
            'order_position' => 'nullable|integer|min:0',
        ]);

        // Auto-increment order_position if not provided
        if (!isset($validated['order_position'])) {
            $maxOrder = EventFaq::where('event_id', $eventId)->max('order_position');
            $validated['order_position'] = ($maxOrder ?? 0) + 1;
        }

        $faq = EventFaq::create([
            'event_id' => $eventId,
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'order_position' => $validated['order_position'],
        ]);

        return response()->json([
            'message' => 'FAQ created successfully',
            'data' => $faq
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/events/{eventId}/faqs/{faqId}",
     *     summary="Update FAQ (organizer only)",
     *     tags={"Event FAQs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="faqId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="question", type="string", maxLength=500),
     *             @OA\Property(property="answer", type="string", maxLength=2000),
     *             @OA\Property(property="order_position", type="integer", minimum=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="FAQ updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="FAQ not found")
     * )
     */
    public function update(Request $request, $eventId, $faqId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can update FAQs.'
            ], 403);
        }

        $faq = EventFaq::where('event_id', $eventId)
            ->where('id', $faqId)
            ->firstOrFail();

        $validated = $request->validate([
            'question' => 'sometimes|string|max:500',
            'answer' => 'sometimes|string|max:2000',
            'order_position' => 'nullable|integer|min:0',
        ]);

        $faq->update($validated);

        return response()->json([
            'message' => 'FAQ updated successfully',
            'data' => $faq->fresh()
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/events/{eventId}/faqs/{faqId}",
     *     summary="Delete FAQ (organizer only)",
     *     tags={"Event FAQs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="faqId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="FAQ deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="FAQ deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="FAQ not found")
     * )
     */
    public function destroy($eventId, $faqId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can delete FAQs.'
            ], 403);
        }

        $faq = EventFaq::where('event_id', $eventId)
            ->where('id', $faqId)
            ->firstOrFail();

        $faq->delete();

        return response()->json([
            'message' => 'FAQ deleted successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/events/{eventId}/faqs/reorder",
     *     summary="Reorder FAQs (organizer only)",
     *     tags={"Event FAQs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"faqs"},
     *             @OA\Property(
     *                 property="faqs",
     *                 type="array",
     *                 description="Array of FAQ IDs in desired order",
     *                 @OA\Items(type="integer"),
     *                 example={3, 1, 2, 5, 4}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="FAQs reordered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="FAQs reordered successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function reorder(Request $request, $eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can reorder FAQs.'
            ], 403);
        }

        $validated = $request->validate([
            'faqs' => 'required|array',
            'faqs.*' => 'required|integer|exists:event_faqs,id',
        ]);

        DB::beginTransaction();
        try {
            foreach ($validated['faqs'] as $position => $faqId) {
                EventFaq::where('event_id', $eventId)
                    ->where('id', $faqId)
                    ->update(['order_position' => $position + 1]);
            }

            DB::commit();

            return response()->json([
                'message' => 'FAQs reordered successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to reorder FAQs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/events/{eventId}/faqs/bulk-delete",
     *     summary="Delete multiple FAQs (organizer only)",
     *     tags={"Event FAQs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"faq_ids"},
     *             @OA\Property(
     *                 property="faq_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 3}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="FAQs deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="deleted_count", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function bulkDelete(Request $request, $eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        if ($event->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. Only event organizer can delete FAQs.'
            ], 403);
        }

        $validated = $request->validate([
            'faq_ids' => 'required|array',
            'faq_ids.*' => 'required|integer|exists:event_faqs,id',
        ]);

        $deletedCount = EventFaq::where('event_id', $eventId)
            ->whereIn('id', $validated['faq_ids'])
            ->delete();

        return response()->json([
            'message' => 'FAQs deleted successfully',
            'deleted_count' => $deletedCount
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/faqs/search",
     *     summary="Search FAQs",
     *     tags={"Event FAQs"},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         required=true,
     *         description="Search query",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function search(Request $request, $eventId)
    {
        $event = Event::findOrFail($eventId);

        $validated = $request->validate([
            'q' => 'required|string|min:2'
        ]);

        $searchQuery = $validated['q'];

        $faqs = EventFaq::where('event_id', $eventId)
            ->where(function($query) use ($searchQuery) {
                $query->where('question', 'like', "%{$searchQuery}%")
                      ->orWhere('answer', 'like', "%{$searchQuery}%");
            })
            ->orderBy('order_position')
            ->get();

        return response()->json([
            'message' => 'Search completed',
            'query' => $searchQuery,
            'data' => $faqs,
            'total' => $faqs->count()
        ]);
    }
}
