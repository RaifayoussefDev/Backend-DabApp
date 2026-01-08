<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Listing;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/dashboard/stats",
     *     summary="Get Admin Dashboard Statistics",
     *     tags={"Admin Statistics"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="total_users", type="integer"),
     *             @OA\Property(property="total_listings", type="integer"),
     *             @OA\Property(property="listings_by_status", type="object"),
     *             @OA\Property(property="listings_by_category", type="object"),
     *             @OA\Property(property="recent_listings", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function stats(Request $request)
    {
        // 1. Total Users
        $totalUsers = User::count();

        // 2. Listing Stats
        $totalListings = Listing::count();
        
        $listingsByStatus = Listing::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $listingsByCategory = Listing::select('category_id', DB::raw('count(*) as count'))
            ->with('category:id,name') // Assuming Category model exists and has name
            ->groupBy('category_id')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->category ? $item->category->name : 'Unknown' => $item->count];
            });

        // 3. Recent Activity (Last 5 listings)
        $recentListings = Listing::with(['seller:id,name', 'category:id,name'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get(['id', 'title', 'price', 'status', 'created_at', 'seller_id', 'category_id']);

        // 4. Revenue (Optional - simple summation of completed payments)
        // $totalRevenue = Payment::where('payment_status', 'completed')->sum('amount');

        return response()->json([
            'total_users' => $totalUsers,
            'total_listings' => $totalListings,
            'listings_by_status' => $listingsByStatus,
            'listings_by_category' => $listingsByCategory,
            'recent_listings' => $recentListings,
            // 'total_revenue' => $totalRevenue
        ]);
    }
}
