<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Listing;
use App\Models\Motorcycle;
use App\Models\SparePart;
use App\Models\LicensePlate;
use App\Models\Submission;
use App\Models\Payment;
use App\Models\Guide;
use App\Models\Event;
use App\Models\Route;
use App\Models\PointOfInterest;
use App\Models\Wishlist;
use App\Models\MotorcycleBrand;
use App\Models\City;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/dashboard",
     *     tags={"Admin - Dashboard"},
     *     summary="Get complete dashboard statistics",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Complete dashboard data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="overview", type="object"),
     *                 @OA\Property(property="listings", type="object"),
     *                 @OA\Property(property="motorcycles", type="object"),
     *                 @OA\Property(property="spare_parts", type="object"),
     *                 @OA\Property(property="license_plates", type="object"),
     *                 @OA\Property(property="auctions", type="object"),
     *                 @OA\Property(property="payments", type="object"),
     *                 @OA\Property(property="engagement", type="object"),
     *                 @OA\Property(property="content", type="object"),
     *                 @OA\Property(property="moderation", type="object"),
     *                 @OA\Property(property="charts", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $data = [
            'overview' => $this->getOverviewStats(),
            'listings' => $this->getListingsStats(),
            'motorcycles' => $this->getMotorcyclesStats(),
            'spare_parts' => $this->getSparePartsStats(),
            'license_plates' => $this->getLicensePlatesStats(),
            'auctions' => $this->getAuctionsStats(),
            'payments' => $this->getPaymentsStats(),
            'engagement' => $this->getEngagementStats(),
            'content' => $this->getContentStats(),
            'moderation' => $this->getModerationStats(),
            'charts' => $this->getChartsData(),
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Vue d'ensemble générale
     */
    private function getOverviewStats()
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        return [
            'users' => [
                'total' => User::count(),
                'verified' => User::where('verified', true)->count(),
                'active_30_days' => User::where('last_login', '>=', Carbon::now()->subDays(30))->count(),
                'new_today' => User::whereDate('created_at', $today)->count(),
                'new_this_week' => User::where('created_at', '>=', $thisWeek)->count(),
                'new_this_month' => User::where('created_at', '>=', $thisMonth)->count(),
            ],
            'listings' => [
                'total' => Listing::count(),
                'active' => Listing::where('status', 'active')->count(),
                'pending' => Listing::where('status', 'pending')->count(),
                'sold' => Listing::where('status', 'sold')->count(),
            ],
            'revenue' => [
                'today' => Payment::whereDate('created_at', $today)
                    ->where('payment_status', 'completed')
                    ->sum('amount'),
                'this_week' => Payment::where('created_at', '>=', $thisWeek)
                    ->where('payment_status', 'completed')
                    ->sum('amount'),
                'this_month' => Payment::where('created_at', '>=', $thisMonth)
                    ->where('payment_status', 'completed')
                    ->sum('amount'),
                'total' => Payment::where('payment_status', 'completed')->sum('amount'),
            ]
        ];
    }

    /**
     * Statistiques des annonces
     */
    private function getListingsStats()
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        return [
            'total' => Listing::count(),
            'by_status' => [
                'active' => Listing::where('status', 'active')->count(),
                'pending' => Listing::where('status', 'pending')->count(),
                'rejected' => Listing::where('status', 'rejected')->count(),
                'sold' => Listing::where('status', 'sold')->count(),
                'expired' => Listing::where('status', 'expired')->count(),
            ],
            'new' => [
                'today' => Listing::whereDate('created_at', $today)->count(),
                'this_week' => Listing::where('created_at', '>=', $thisWeek)->count(),
                'this_month' => Listing::where('created_at', '>=', $thisMonth)->count(),
            ],
            'by_category' => Listing::join('categories', 'listings.category_id', '=', 'categories.id')
                ->select('categories.name', DB::raw('count(*) as count'))
                ->groupBy('categories.name')
                ->get(),
            'pending_moderation' => Listing::where('status', 'pending')->count(),
            'with_auction' => Listing::where('auction_enabled', true)->count(),
        ];
    }

    /**
     * Statistiques des motos
     */
    private function getMotorcyclesStats()
    {
        return [
            'total' => Motorcycle::count(),
            'active' => Motorcycle::whereHas('listing', function($q) {
                $q->where('status', 'active');
            })->count(),
            'by_brand' => Motorcycle::join('motorcycle_brands', 'motorcycles.brand_id', '=', 'motorcycle_brands.id')
                ->select('motorcycle_brands.name', DB::raw('count(*) as count'))
                ->groupBy('motorcycle_brands.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'by_type' => Motorcycle::join('motorcycle_types', 'motorcycles.type_id', '=', 'motorcycle_types.id')
                ->select('motorcycle_types.name', DB::raw('count(*) as count'))
                ->groupBy('motorcycle_types.name')
                ->get(),
            'by_city' => Motorcycle::join('listings', 'motorcycles.listing_id', '=', 'listings.id')
                ->join('cities', 'listings.city_id', '=', 'cities.id')
                ->select('cities.name', DB::raw('count(*) as count'))
                ->whereNotNull('listings.city_id')
                ->groupBy('cities.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'average_price' => Motorcycle::join('listings', 'motorcycles.listing_id', '=', 'listings.id')
                ->where('listings.status', 'active')
                ->avg('listings.price'),
            'most_recent' => Motorcycle::join('listings', 'motorcycles.listing_id', '=', 'listings.id')
                ->join('motorcycle_brands', 'motorcycles.brand_id', '=', 'motorcycle_brands.id')
                ->join('motorcycle_models', 'motorcycles.model_id', '=', 'motorcycle_models.id')
                ->select(
                    'listings.id',
                    'listings.title',
                    'motorcycle_brands.name as brand',
                    'motorcycle_models.name as model',
                    'listings.price',
                    'listings.created_at'
                )
                ->where('listings.status', 'active')
                ->orderByDesc('listings.created_at')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * Statistiques des pièces détachées
     */
    private function getSparePartsStats()
    {
        return [
            'total' => SparePart::count(),
            'active' => SparePart::whereHas('listing', function($q) {
                $q->where('status', 'active');
            })->count(),
            'by_category' => SparePart::join('bike_part_categories', 'spare_parts.bike_part_category_id', '=', 'bike_part_categories.id')
                ->select('bike_part_categories.name', DB::raw('count(*) as count'))
                ->groupBy('bike_part_categories.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'by_brand' => SparePart::join('bike_part_brands', 'spare_parts.bike_part_brand_id', '=', 'bike_part_brands.id')
                ->select('bike_part_brands.name', DB::raw('count(*) as count'))
                ->groupBy('bike_part_brands.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'by_condition' => SparePart::select('condition', DB::raw('count(*) as count'))
                ->whereNotNull('condition')
                ->groupBy('condition')
                ->get(),
            'average_price' => SparePart::join('listings', 'spare_parts.listing_id', '=', 'listings.id')
                ->where('listings.status', 'active')
                ->avg('listings.price'),
        ];
    }

    /**
     * Statistiques des plaques d'immatriculation
     */
    private function getLicensePlatesStats()
    {
        return [
            'total' => LicensePlate::count(),
            'active' => LicensePlate::whereHas('listing', function($q) {
                $q->where('status', 'active');
            })->count(),
            'by_country' => LicensePlate::join('countries', 'license_plates.country_id', '=', 'countries.id')
                ->select('countries.name', DB::raw('count(*) as count'))
                ->groupBy('countries.name')
                ->get(),
            'by_city' => LicensePlate::join('cities', 'license_plates.city_id', '=', 'cities.id')
                ->select('cities.name', DB::raw('count(*) as count'))
                ->whereNotNull('license_plates.city_id')
                ->groupBy('cities.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'average_price' => LicensePlate::join('listings', 'license_plates.listing_id', '=', 'listings.id')
                ->where('listings.status', 'active')
                ->avg('listings.price'),
        ];
    }

    /**
     * Statistiques des enchères (SOOM)
     */
    private function getAuctionsStats()
    {
        return [
            'active_listings' => Listing::where('auction_enabled', true)
                ->where('status', 'active')
                ->count(),
            'total_submissions' => Submission::count(),
            'by_status' => [
                'pending' => Submission::where('status', 'pending')->count(),
                'accepted' => Submission::where('status', 'accepted')->count(),
                'rejected' => Submission::where('status', 'rejected')->count(),
            ],
            'validated_sales' => Submission::where('sale_validated', true)->count(),
            'total_bid_value' => Submission::where('status', 'accepted')->sum('amount'),
            'average_bid' => Submission::avg('amount'),
            'conversion_rate' => $this->calculateConversionRate(),
            'top_bidders' => Submission::join('users', 'submissions.user_id', '=', 'users.id')
                ->select(
                    'users.id',
                    'users.first_name',
                    'users.last_name',
                    DB::raw('count(*) as total_bids'),
                    DB::raw('sum(case when status = "accepted" then 1 else 0 end) as accepted_bids')
                )
                ->groupBy('users.id', 'users.first_name', 'users.last_name')
                ->orderByDesc('total_bids')
                ->limit(10)
                ->get(),
        ];
    }

    /**
     * Statistiques des paiements
     */
    private function getPaymentsStats()
    {
        $thisMonth = Carbon::now()->startOfMonth();
        $thisYear = Carbon::now()->startOfYear();

        return [
            'total_revenue' => Payment::where('payment_status', 'completed')->sum('amount'),
            'this_month_revenue' => Payment::where('created_at', '>=', $thisMonth)
                ->where('payment_status', 'completed')
                ->sum('amount'),
            'this_year_revenue' => Payment::where('created_at', '>=', $thisYear)
                ->where('payment_status', 'completed')
                ->sum('amount'),
            'total_transactions' => Payment::count(),
            'by_status' => [
                'completed' => Payment::where('payment_status', 'completed')->count(),
                'pending' => Payment::where('payment_status', 'pending')->count(),
                'failed' => Payment::where('payment_status', 'failed')->count(),
            ],
            'average_transaction' => Payment::where('payment_status', 'completed')->avg('amount'),
            'promo_codes_used' => Payment::whereNotNull('promo_code_id')
                ->where('payment_status', 'completed')
                ->count(),
            'total_discounts' => Payment::where('payment_status', 'completed')
                ->whereNotNull('discounted_amount')
                ->sum(DB::raw('total_amount - discounted_amount')),
        ];
    }

    /**
     * Statistiques d'engagement
     */
    private function getEngagementStats()
    {
        return [
            'total_wishlists' => Wishlist::count(),
            'users_with_garage' => DB::table('my_garage')
                ->select('user_id')
                ->distinct()
                ->count(),
            'top_sellers' => Listing::join('users', 'listings.seller_id', '=', 'users.id')
                ->select(
                    'users.id',
                    'users.first_name',
                    'users.last_name',
                    DB::raw('count(*) as total_listings'),
                    DB::raw('sum(case when listings.status = "sold" then 1 else 0 end) as sold_listings')
                )
                ->groupBy('users.id', 'users.first_name', 'users.last_name')
                ->orderByDesc('total_listings')
                ->limit(10)
                ->get(),
        ];
    }

    /**
     * Statistiques du contenu communautaire
     */
    private function getContentStats()
    {
        return [
            'guides' => [
                'total' => Guide::count(),
                'published' => Guide::where('status', 'published')->count(),
                'draft' => Guide::where('status', 'draft')->count(),
                'total_views' => Guide::sum('views_count'),
                'most_viewed' => Guide::where('status', 'published')
                    ->orderByDesc('views_count')
                    ->limit(5)
                    ->get(['id', 'title', 'views_count', 'created_at']),
            ],
            'events' => [
                'total' => Event::count(),
                'upcoming' => Event::where('event_date', '>=', Carbon::today())->count(),
                'completed' => Event::where('event_date', '<', Carbon::today())->count(),
                'total_participants' => DB::table('event_participants')->count(),
            ],
            'routes' => [
                'total' => Route::count(),
                'verified' => Route::where('is_verified', true)->count(),
                'total_completions' => DB::table('route_completions')->count(),
            ],
            'poi' => [
                'total' => PointOfInterest::count(),
                'verified' => PointOfInterest::where('is_verified', true)->count(),
                'by_type' => PointOfInterest::join('poi_types', 'points_of_interest.type_id', '=', 'poi_types.id')
                    ->select('poi_types.name', DB::raw('count(*) as count'))
                    ->groupBy('poi_types.name')
                    ->get(),
            ],
        ];
    }

    /**
     * Statistiques de modération
     */
    private function getModerationStats()
    {
        return [
            'pending_listings' => Listing::where('status', 'pending')->count(),
            'pending_comments' => DB::table('guide_comments')
                ->where('is_approved', false)
                ->count(),
            'poi_reports' => DB::table('poi_reports')
                ->where('status', 'pending')
                ->count(),
            'recent_pending_listings' => Listing::where('status', 'pending')
                ->with(['seller:id,first_name,last_name', 'category:id,name'])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'title', 'created_at', 'seller_id', 'category_id']),
        ];
    }

    /**
     * Données pour les graphiques
     */
    private function getChartsData()
    {
        return [
            // Graphiques de croissance (lignes)
            'users_growth' => $this->getUsersGrowthChart(),
            'listings_growth' => $this->getListingsGrowthChart(),
            'revenue_monthly' => $this->getRevenueMonthlyChart(),

            // Graphiques de répartition (camemberts/barres)
            'listings_by_category' => $this->getListingsByCategoryChart(),
            'motorcycles_by_brand' => $this->getMotorcyclesByBrandChart(),
            'listings_by_city' => $this->getListingsByCityChart(),
            'spare_parts_by_category' => $this->getSparePartsByCategoryChart(),
            'listings_by_status' => $this->getListingsByStatusChart(),
            'payments_by_status' => $this->getPaymentsByStatusChart(),
            'auctions_by_status' => $this->getAuctionsByStatusChart(),
        ];
    }

    /**
     * Graphique: Croissance des utilisateurs (30 derniers jours)
     */
    private function getUsersGrowthChart()
    {
        $data = User::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count')
            )
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'type' => 'line',
            'labels' => $data->pluck('date'),
            'values' => $data->pluck('count'),
        ];
    }

    /**
     * Graphique: Croissance des annonces (30 derniers jours)
     */
    private function getListingsGrowthChart()
    {
        $data = Listing::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count')
            )
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'type' => 'line',
            'labels' => $data->pluck('date'),
            'values' => $data->pluck('count'),
        ];
    }

    /**
     * Graphique: Revenus mensuels (12 derniers mois)
     */
    private function getRevenueMonthlyChart()
    {
        $data = Payment::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('sum(amount) as total')
            )
            ->where('payment_status', 'completed')
            ->where('created_at', '>=', Carbon::now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'type' => 'bar',
            'labels' => $data->pluck('month'),
            'values' => $data->pluck('total'),
        ];
    }

    /**
     * Graphique: Annonces par catégorie
     */
    private function getListingsByCategoryChart()
    {
        $data = Listing::join('categories', 'listings.category_id', '=', 'categories.id')
            ->select('categories.name', DB::raw('count(*) as count'))
            ->groupBy('categories.name')
            ->get();

        return [
            'type' => 'pie',
            'labels' => $data->pluck('name'),
            'values' => $data->pluck('count'),
        ];
    }

    /**
     * Graphique: Top 5 marques de motos
     */
    private function getMotorcyclesByBrandChart()
    {
        $data = Motorcycle::join('motorcycle_brands', 'motorcycles.brand_id', '=', 'motorcycle_brands.id')
            ->select('motorcycle_brands.name', DB::raw('count(*) as count'))
            ->groupBy('motorcycle_brands.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return [
            'type' => 'bar',
            'labels' => $data->pluck('name'),
            'values' => $data->pluck('count'),
        ];
    }

    /**
     * Graphique: Top 10 villes par annonces
     */
    private function getListingsByCityChart()
    {
        $data = Listing::join('cities', 'listings.city_id', '=', 'cities.id')
            ->select('cities.name', DB::raw('count(*) as count'))
            ->whereNotNull('listings.city_id')
            ->groupBy('cities.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return [
            'type' => 'bar',
            'labels' => $data->pluck('name'),
            'values' => $data->pluck('count'),
        ];
    }

    /**
     * Graphique: Pièces détachées par catégorie
     */
    private function getSparePartsByCategoryChart()
    {
        $data = SparePart::join('bike_part_categories', 'spare_parts.bike_part_category_id', '=', 'bike_part_categories.id')
            ->select('bike_part_categories.name', DB::raw('count(*) as count'))
            ->groupBy('bike_part_categories.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return [
            'type' => 'bar',
            'labels' => $data->pluck('name'),
            'values' => $data->pluck('count'),
        ];
    }

    /**
     * Graphique: Annonces par statut
     */
    private function getListingsByStatusChart()
    {
        $data = Listing::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        return [
            'type' => 'doughnut',
            'labels' => $data->pluck('status'),
            'values' => $data->pluck('count'),
        ];
    }

    /**
     * Graphique: Paiements par statut
     */
    private function getPaymentsByStatusChart()
    {
        $data = Payment::select('payment_status', DB::raw('count(*) as count'))
            ->groupBy('payment_status')
            ->get();

        return [
            'type' => 'pie',
            'labels' => $data->pluck('payment_status'),
            'values' => $data->pluck('count'),
        ];
    }

    /**
     * Graphique: Enchères par statut
     */
    private function getAuctionsByStatusChart()
    {
        $data = Submission::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        return [
            'type' => 'doughnut',
            'labels' => $data->pluck('status'),
            'values' => $data->pluck('count'),
        ];
    }

    /**
     * Calcul du taux de conversion des enchères
     */
    private function calculateConversionRate()
    {
        $total = Submission::count();
        if ($total == 0) return 0;

        $accepted = Submission::where('status', 'accepted')->count();
        return round(($accepted / $total) * 100, 2);
    }
}
