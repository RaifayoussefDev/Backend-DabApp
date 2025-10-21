<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventFavorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventFavoriteController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/events/{eventId}/favorite",
     *     summary="Add event to favorites (auth required)",
     *     tags={"Event Favorites"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="eventId",
     *         in="path",
     *         required=true,
     *         description="Event ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Event added to favorites",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Event added to favorites"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Event already in favorites"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Event not found")
     * )
     */
    public function store($eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        $favorite = EventFavorite::firstOrCreate([
            'event_id' => $eventId,
            'user_id' => $user->id,
        ]);

        $wasRecentlyCreated = $favorite->wasRecentlyCreated;

        return response()->json([
            'message' => $wasRecentlyCreated ? 'Event added to favorites' : 'Event already in favorites',
            'data' => $favorite->load('event')
        ], $wasRecentlyCreated ? 201 : 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/events/{eventId}/unfavorite",
     *     summary="Remove event from favorites (auth required)",
     *     tags={"Event Favorites"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Event removed from favorites",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy($eventId)
    {
        $user = Auth::user();

        $deleted = EventFavorite::where('event_id', $eventId)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json([
            'message' => $deleted ? 'Event removed from favorites' : 'Event was not in favorites'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/my-favorite-events",
     *     summary="Get my favorite events (auth required)",
     *     tags={"Event Favorites"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"upcoming", "ongoing", "completed"})),
     *     @OA\Response(
     *         response=200,
     *         description="List of favorite events",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function myFavorites(Request $request)
    {
        $user = Auth::user();

        $query = EventFavorite::where('user_id', $user->id)
            ->with(['event.category', 'event.city', 'event.country', 'event.organizer']);

        // Filter by event status
        if ($request->has('status')) {
            $query->whereHas('event', function($q) use ($request) {
                $q->where('status', $request->status);
            });
        }

        $perPage = $request->get('per_page', 15);
        $favorites = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'message' => 'Favorite events retrieved successfully',
            'data' => $favorites
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/events/{eventId}/is-favorite",
     *     summary="Check if event is in favorites (auth required)",
     *     tags={"Event Favorites"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Favorite status",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_favorite", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function isFavorite($eventId)
    {
        $user = Auth::user();

        $isFavorite = EventFavorite::where('event_id', $eventId)
            ->where('user_id', $user->id)
            ->exists();

        return response()->json([
            'is_favorite' => $isFavorite
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/events/{eventId}/toggle-favorite",
     *     summary="Toggle favorite status (auth required)",
     *     tags={"Event Favorites"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="eventId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Favorite toggled",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="is_favorite", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function toggle($eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        $favorite = EventFavorite::where('event_id', $eventId)
            ->where('user_id', $user->id)
            ->first();

        if ($favorite) {
            $favorite->delete();
            $message = 'Event removed from favorites';
            $isFavorite = false;
        } else {
            EventFavorite::create([
                'event_id' => $eventId,
                'user_id' => $user->id,
            ]);
            $message = 'Event added to favorites';
            $isFavorite = true;
        }

        return response()->json([
            'message' => $message,
            'is_favorite' => $isFavorite
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/my-favorite-events/clear",
     *     summary="Clear all favorites (auth required)",
     *     tags={"Event Favorites"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="All favorites cleared",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="deleted_count", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function clearAll()
    {
        $user = Auth::user();

        $deletedCount = EventFavorite::where('user_id', $user->id)->delete();

        return response()->json([
            'message' => 'All favorites cleared successfully',
            'deleted_count' => $deletedCount
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/my-favorite-events/count",
     *     summary="Get favorites count (auth required)",
     *     tags={"Event Favorites"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Favorites count",
     *         @OA\JsonContent(
     *             @OA\Property(property="count", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function count()
    {
        $user = Auth::user();

        $count = EventFavorite::where('user_id', $user->id)->count();

        return response()->json([
            'count' => $count
        ]);
    }
}
