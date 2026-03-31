<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use App\Models\ServiceSubscription;
use App\Models\SubscriptionTransaction;
use App\Models\ServiceBooking;
use App\Models\Service;
use App\Models\ServiceReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Admin Service Stats",
 *     description="API endpoints for global service statistics (Admin)"
 * )
 */
class AdminServiceStatsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/services/stats/overview",
     *     summary="Get global services dashboard statistics (Admin)",
     *     operationId="adminGetServiceDashboardStats",
     *     tags={"Admin Service Stats"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function dashboard()
    {
        // 1. Providers Stats
        $providersCount = ServiceProvider::count();
        $verifiedProviders = ServiceProvider::where('is_verified', true)->count();
        $newProvidersMonth = ServiceProvider::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // 2. Subscriptions & Revenue Stats
        $activeSubscriptions = ServiceSubscription::where('status', 'active')->count();
        $totalRevenue = SubscriptionTransaction::where('status', 'completed')->sum('amount');
        $monthlyRevenue = SubscriptionTransaction::where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        // 3. Bookings Stats
        $totalBookings = ServiceBooking::count();
        $bookingsMonth = ServiceBooking::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $bookingStatusDistribution = ServiceBooking::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get();

        // 4. Services Stats
        $totalServices = Service::count();
        $pendingApprovals = Service::where('is_approved', false)->count();

        // 5. Customer Satisfaction
        $averageRating = ServiceReview::where('is_approved', true)->avg('rating') ?? 0;

        // 6. Growth Chart (Last 6 Months Bookings)
        $growthData = ServiceBooking::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, count(*) as count')
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get()
            ->map(function($item) {
                return [
                    'label' => \Carbon\Carbon::create($item->year, $item->month, 1)->format('M'),
                    'value' => $item->count
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => [
                    'total_providers' => $providersCount,
                    'verified_providers' => $verifiedProviders,
                    'new_providers_this_month' => $newProvidersMonth,
                    'active_subscriptions' => $activeSubscriptions,
                    'total_revenue' => round($totalRevenue, 2),
                    'monthly_revenue' => round($monthlyRevenue, 2),
                    'total_bookings' => $totalBookings,
                    'bookings_this_month' => $bookingsMonth,
                    'total_services' => $totalServices,
                    'pending_approvals' => $pendingApprovals,
                    'average_rating' => round($averageRating, 1)
                ],
                'charts' => [
                    'booking_status' => $bookingStatusDistribution,
                    'booking_growth' => $growthData
                ]
            ],
            'message' => 'Dashboard statistics retrieved successfully'
        ]);
    }
}
