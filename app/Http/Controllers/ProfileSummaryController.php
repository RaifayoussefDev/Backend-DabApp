<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\MyGarage;
use App\Models\Payment;
use App\Models\PointOfInterest;
use App\Models\Route;
use App\Models\Submission;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Counts for the profile sidebar and home dashboard, in one request instead of one
 * call per section. Each number uses the same filter as the section's own list.
 */
class ProfileSummaryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/profile/summary",
     *     summary="Counts for the signed-in user's profile sections",
     *     tags={"Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Profile counts"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $userId = Auth::id();

        // My listings by status (same base query as /my-ads).
        $listings = Listing::where('seller_id', $userId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Soom offers received on my listings (as /my-listings-sooms) and sent (as /my-sooms).
        $received = Submission::whereHas('listing', fn ($q) => $q->where('seller_id', $userId))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $sent = Submission::where('user_id', $userId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $payments = Payment::where('user_id', $userId)
            ->selectRaw('payment_status, COUNT(*) as total')
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status');

        $routes = Route::where('created_by', $userId);

        return response()->json([
            'listings' => [
                'total' => (int) $listings->sum(),
                'by_status' => $listings->map(fn ($n) => (int) $n),
                'drafts' => (int) ($listings['draft'] ?? 0),
            ],
            'sooms' => [
                'received' => (int) $received->sum(),
                'received_pending' => (int) ($received['pending'] ?? 0),
                'sent' => (int) $sent->sum(),
                'sent_pending' => (int) ($sent['pending'] ?? 0),
            ],
            // The wishlist only lists published listings.
            'wishlist' => Wishlist::where('user_id', $userId)
                ->whereHas('listing', fn ($q) => $q->where('status', 'published'))
                ->count(),
            'payments' => [
                'total' => (int) $payments->sum(),
                'by_status' => $payments->map(fn ($n) => (int) $n),
            ],
            'garage' => MyGarage::where('user_id', $userId)->count(),
            'pois' => PointOfInterest::where('owner_id', $userId)->count(),
            'routes' => [
                'total' => (clone $routes)->count(),
                'views' => (int) (clone $routes)->sum('views_count'),
            ],
        ]);
    }
}
