<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingContactReveal;
use App\Models\View;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Admin Guest Activity",
 *     description="Traceability for non-authenticated (guest) marketplace traffic: anonymous listing views and seller-contact reveals."
 * )
 */
class GuestActivityController extends Controller
{
    /**
     * Anonymous listing views (views.user_id IS NULL), deduped at write time by
     * IP + user-agent per listing / 24h.
     *
     * @OA\Get(
     *     path="/api/admin/guest-activity/views",
     *     tags={"Admin Guest Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="listing_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", description="Matches IP or listing title", @OA\Schema(type="string")),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=25)),
     *     @OA\Response(response=200, description="Guest views retrieved")
     * )
     */
    public function views(Request $request)
    {
        $query = View::query()
            ->whereNull('user_id')
            ->where('viewable_type', Listing::class)
            ->with(['viewable' => fn ($q) => $q->select('id', 'title', 'seller_id', 'category_id')]);

        if ($request->filled('listing_id')) {
            $query->where('viewable_id', $request->listing_id);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('ip_address', 'like', "%{$term}%")
                    ->orWhereHasMorph('viewable', [Listing::class], function ($lq) use ($term) {
                        $lq->where('title', 'like', "%{$term}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $views = $query->latest()->paginate($request->get('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $views,
        ]);
    }

    /**
     * Seller-contact reveals (the "show phone" / "contact seller" action). The
     * endpoint that records these is auth-only, so `user` is normally set — this
     * shows which registered users are chasing which listings.
     *
     * @OA\Get(
     *     path="/api/admin/guest-activity/contact-reveals",
     *     tags={"Admin Guest Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="listing_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="seller_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", description="Matches IP, listing title or buyer/seller name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=25)),
     *     @OA\Response(response=200, description="Contact reveals retrieved")
     * )
     */
    public function contactReveals(Request $request)
    {
        $query = ListingContactReveal::query()
            ->with([
                'listing:id,title,category_id',
                'user:id,first_name,last_name,email,phone',
                'seller:id,first_name,last_name,email,phone',
            ]);

        foreach (['listing_id', 'seller_id', 'user_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('ip_address', 'like', "%{$term}%")
                    ->orWhereHas('listing', fn ($lq) => $lq->where('title', 'like', "%{$term}%"))
                    ->orWhereHas('user', fn ($uq) => $uq->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                    ->orWhereHas('seller', fn ($sq) => $sq->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $reveals = $query->latest()->paginate($request->get('per_page', 25));

        $reveals->getCollection()->each(function ($row) {
            $row->user?->makeHidden(['is_dealer', 'dealer_title', 'dealer_address', 'dealer_phone', 'points_of_interest']);
            $row->seller?->makeHidden(['is_dealer', 'dealer_title', 'dealer_address', 'dealer_phone', 'points_of_interest']);
        });

        return response()->json([
            'success' => true,
            'data' => $reveals,
        ]);
    }

    /**
     * Headline counts for the last 30 days.
     *
     * @OA\Get(
     *     path="/api/admin/guest-activity/summary",
     *     tags={"Admin Guest Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Summary retrieved")
     * )
     */
    public function summary()
    {
        $since = now()->subDays(30);

        $guestViews = View::whereNull('user_id')->where('viewable_type', Listing::class);
        $reveals = ListingContactReveal::query();

        return response()->json([
            'success' => true,
            'since' => $since->toDateString(),
            'data' => [
                'guest_views_total' => (clone $guestViews)->count(),
                'guest_views_30d' => (clone $guestViews)->where('created_at', '>=', $since)->count(),
                'guest_views_today' => (clone $guestViews)->whereDate('created_at', today())->count(),
                'contact_reveals_total' => (clone $reveals)->count(),
                'contact_reveals_30d' => (clone $reveals)->where('created_at', '>=', $since)->count(),
                'contact_reveals_today' => (clone $reveals)->whereDate('created_at', today())->count(),
            ],
        ]);
    }
}
