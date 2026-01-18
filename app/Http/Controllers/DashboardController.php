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
     *             @OA\Property(property="data", type="object")
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
            'prospects' => $this->getProspectsStats(),
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/admin/dashboard/prospect-stats",
     *     tags={"Admin - Dashboard"},
     *     summary="Get prospect statistics",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Prospect stats data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function prospectStats()
    {
        $prospects = User::where('first_name', 'Prospect')
            ->withCount('listings')
            ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'total_prospects' => $prospects->count(),
                'prospects' => $prospects
            ]
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
                'published' => Listing::where('status', 'published')->count(),
                'draft' => Listing::where('status', 'draft')->count(),
                'sold' => Listing::where('status', 'sold')->count(),
                'closed' => Listing::where('status', 'closed')->count(),
            ],
            'revenue' => [
                'today' => Payment::whereDate('created_at', $today)
                    ->completed()
                    ->sum('amount'),
                'this_week' => Payment::where('created_at', '>=', $thisWeek)
                    ->completed()
                    ->sum('amount'),
                'this_month' => Payment::where('created_at', '>=', $thisMonth)
                    ->completed()
                    ->sum('amount'),
                'total' => Payment::completed()->sum('amount'),
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
                'draft' => Listing::where('status', 'draft')->count(),
                'published' => Listing::where('status', 'published')->count(),
                'sold' => Listing::where('status', 'sold')->count(),
                'closed' => Listing::where('status', 'closed')->count(),
            ],
            'payment_pending' => Listing::where('payment_pending', true)
                ->where('status', 'draft')
                ->count(),
            'draft_unpaid' => Listing::where('status', 'draft')
                ->where('payment_pending', true)
                ->count(),
            'new' => [
                'today' => Listing::whereDate('created_at', $today)->count(),
                'this_week' => Listing::where('created_at', '>=', $thisWeek)->count(),
                'this_month' => Listing::where('created_at', '>=', $thisMonth)->count(),
            ],
            'by_category' => Listing::join('categories', 'listings.category_id', '=', 'categories.id')
                ->select('categories.name', DB::raw('count(*) as count'))
                ->groupBy('categories.id', 'categories.name')
                ->get(),
            'with_auction' => Listing::where('auction_enabled', true)
                ->where('status', 'published')
                ->count(),
            'recent_published' => Listing::where('status', 'published')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'title', 'price', 'created_at']),
        ];
    }

    /**
     * Statistiques des motos
     */
    private function getMotorcyclesStats()
    {
        return [
            'total' => Motorcycle::count(),
            'published' => Motorcycle::whereHas('listing', function($q) {
                $q->where('status', 'published');
            })->count(),
            'sold' => Motorcycle::whereHas('listing', function($q) {
                $q->where('status', 'sold');
            })->count(),
            'by_brand' => Motorcycle::join('motorcycle_brands', 'motorcycles.brand_id', '=', 'motorcycle_brands.id')
                ->join('listings', 'motorcycles.listing_id', '=', 'listings.id')
                ->select('motorcycle_brands.name', DB::raw('count(*) as count'))
                ->where('listings.status', 'published')
                ->groupBy('motorcycle_brands.id', 'motorcycle_brands.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'by_type' => Motorcycle::join('motorcycle_types', 'motorcycles.type_id', '=', 'motorcycle_types.id')
                ->join('listings', 'motorcycles.listing_id', '=', 'listings.id')
                ->select('motorcycle_types.name', DB::raw('count(*) as count'))
                ->where('listings.status', 'published')
                ->groupBy('motorcycle_types.id', 'motorcycle_types.name')
                ->orderByDesc('count')
                ->get(),
            'by_city' => Motorcycle::join('listings', 'motorcycles.listing_id', '=', 'listings.id')
                ->join('cities', 'listings.city_id', '=', 'cities.id')
                ->select('cities.name', DB::raw('count(*) as count'))
                ->whereNotNull('listings.city_id')
                ->where('listings.status', 'published')
                ->groupBy('cities.id', 'cities.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'by_condition' => Motorcycle::join('listings', 'motorcycles.listing_id', '=', 'listings.id')
                ->select('motorcycles.general_condition', DB::raw('count(*) as count'))
                ->whereNotNull('motorcycles.general_condition')
                ->where('listings.status', 'published')
                ->groupBy('motorcycles.general_condition')
                ->get(),
            'by_transmission' => Motorcycle::join('listings', 'motorcycles.listing_id', '=', 'listings.id')
                ->select('motorcycles.transmission', DB::raw('count(*) as count'))
                ->whereNotNull('motorcycles.transmission')
                ->where('listings.status', 'published')
                ->groupBy('motorcycles.transmission')
                ->get(),
            'average_price' => Motorcycle::join('listings', 'motorcycles.listing_id', '=', 'listings.id')
                ->where('listings.status', 'published')
                ->avg('listings.price'),
            'average_mileage' => Motorcycle::join('listings', 'motorcycles.listing_id', '=', 'listings.id')
                ->where('listings.status', 'published')
                ->whereNotNull('motorcycles.mileage')
                ->avg('motorcycles.mileage'),
            'most_recent' => Motorcycle::join('listings', 'motorcycles.listing_id', '=', 'listings.id')
                ->join('motorcycle_brands', 'motorcycles.brand_id', '=', 'motorcycle_brands.id')
                ->join('motorcycle_models', 'motorcycles.model_id', '=', 'motorcycle_models.id')
                ->select(
                    'listings.id',
                    'listings.title',
                    'motorcycle_brands.name as brand',
                    'motorcycle_models.name as model',
                    'listings.price',
                    'motorcycles.mileage',
                    'listings.created_at'
                )
                ->where('listings.status', 'published')
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
            'published' => SparePart::whereHas('listing', function($q) {
                $q->where('status', 'published');
            })->count(),
            'sold' => SparePart::whereHas('listing', function($q) {
                $q->where('status', 'sold');
            })->count(),
            'by_category' => SparePart::join('bike_part_categories', 'spare_parts.bike_part_category_id', '=', 'bike_part_categories.id')
                ->join('listings', 'spare_parts.listing_id', '=', 'listings.id')
                ->select('bike_part_categories.name', DB::raw('count(*) as count'))
                ->where('listings.status', 'published')
                ->groupBy('bike_part_categories.id', 'bike_part_categories.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'by_brand' => SparePart::join('bike_part_brands', 'spare_parts.bike_part_brand_id', '=', 'bike_part_brands.id')
                ->join('listings', 'spare_parts.listing_id', '=', 'listings.id')
                ->select('bike_part_brands.name', DB::raw('count(*) as count'))
                ->where('listings.status', 'published')
                ->groupBy('bike_part_brands.id', 'bike_part_brands.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'by_condition' => SparePart::join('listings', 'spare_parts.listing_id', '=', 'listings.id')
                ->select('spare_parts.condition', DB::raw('count(*) as count'))
                ->whereNotNull('spare_parts.condition')
                ->where('listings.status', 'published')
                ->groupBy('spare_parts.condition')
                ->get(),
            'average_price' => SparePart::join('listings', 'spare_parts.listing_id', '=', 'listings.id')
                ->where('listings.status', 'published')
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
            'published' => LicensePlate::whereHas('listing', function($q) {
                $q->where('status', 'published');
            })->count(),
            'sold' => LicensePlate::whereHas('listing', function($q) {
                $q->where('status', 'sold');
            })->count(),
            'by_country' => LicensePlate::join('countries', 'license_plates.country_id', '=', 'countries.id')
                ->join('listings', 'license_plates.listing_id', '=', 'listings.id')
                ->select('countries.name', DB::raw('count(*) as count'))
                ->where('listings.status', 'published')
                ->groupBy('countries.id', 'countries.name')
                ->orderByDesc('count')
                ->get(),
            'by_city' => LicensePlate::join('cities', 'license_plates.city_id', '=', 'cities.id')
                ->join('listings', 'license_plates.listing_id', '=', 'listings.id')
                ->select('cities.name', DB::raw('count(*) as count'))
                ->whereNotNull('license_plates.city_id')
                ->where('listings.status', 'published')
                ->groupBy('cities.id', 'cities.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'by_format' => LicensePlate::join('plate_formats', 'license_plates.plate_format_id', '=', 'plate_formats.id')
                ->join('listings', 'license_plates.listing_id', '=', 'listings.id')
                ->select('plate_formats.name', DB::raw('count(*) as count'))
                ->whereNotNull('license_plates.plate_format_id')
                ->where('listings.status', 'published')
                ->groupBy('plate_formats.id', 'plate_formats.name')
                ->get(),
            'average_price' => LicensePlate::join('listings', 'license_plates.listing_id', '=', 'listings.id')
                ->where('listings.status', 'published')
                ->avg('listings.price'),
        ];
    }

    /**
     * Statistiques des enchères (SOOM)
     */
    private function getAuctionsStats()
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        return [
            'active_listings' => Listing::where('auction_enabled', true)
                ->where('status', 'published')
                ->count(),
            'total_submissions' => Submission::count(),
            'by_status' => [
                'pending' => Submission::pending()->count(),
                'accepted' => Submission::accepted()->count(),
                'rejected' => Submission::rejected()->count(),
            ],
            'validated_sales' => Submission::validated()->count(),
            'pending_validation' => Submission::pendingValidation()->count(),
            'expired_validations' => Submission::accepted()
                ->where('sale_validated', false)
                ->whereNotNull('acceptance_date')
                ->where('acceptance_date', '<=', Carbon::now()->subDays(5))
                ->count(),
            'new_submissions' => [
                'today' => Submission::whereDate('submission_date', $today)->count(),
                'this_week' => Submission::where('submission_date', '>=', $thisWeek)->count(),
                'this_month' => Submission::where('submission_date', '>=', $thisMonth)->count(),
            ],
            'total_bid_value' => Submission::accepted()->sum('amount'),
            'validated_sales_value' => Submission::validated()->sum('amount'),
            'average_bid' => Submission::avg('amount'),
            'average_accepted_bid' => Submission::accepted()->avg('amount'),
            'conversion_rate' => $this->calculateConversionRate(),
            'validation_rate' => $this->calculateValidationRate(),
            'top_bidders' => Submission::join('users', 'submissions.user_id', '=', 'users.id')
                ->select(
                    'users.id',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    DB::raw('count(*) as total_bids'),
                    DB::raw('sum(case when submissions.status = "accepted" then 1 else 0 end) as accepted_bids'),
                    DB::raw('sum(case when submissions.sale_validated = 1 then 1 else 0 end) as validated_sales'),
                    DB::raw('sum(case when submissions.status = "accepted" then submissions.amount else 0 end) as total_accepted_value')
                )
                ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->orderByDesc('total_bids')
                ->limit(10)
                ->get(),
            'recent_submissions' => Submission::with([
                    'user:id,first_name,last_name,email',
                    'listing:id,title,price'
                ])
                ->orderByDesc('submission_date')
                ->limit(10)
                ->get(['id', 'user_id', 'listing_id', 'amount', 'status', 'submission_date']),
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
            'total_revenue' => Payment::completed()->sum('amount'),
            'this_month_revenue' => Payment::where('created_at', '>=', $thisMonth)
                ->completed()
                ->sum('amount'),
            'this_year_revenue' => Payment::where('created_at', '>=', $thisYear)
                ->completed()
                ->sum('amount'),
            'total_transactions' => Payment::count(),
            'by_status' => [
                'completed' => Payment::completed()->count(),
                'pending' => Payment::pending()->count(),
                'failed' => Payment::failed()->count(),
                'initiated' => Payment::where('payment_status', 'initiated')->count(),
            ],
            'average_transaction' => Payment::completed()->avg('amount'),
            'successful_rate' => $this->calculatePaymentSuccessRate(),
            'promo_codes_used' => Payment::whereNotNull('promo_code_id')
                ->completed()
                ->count(),
            'total_discounts' => Payment::completed()
                ->whereNotNull('discounted_amount')
                ->whereNotNull('total_amount')
                ->sum(DB::raw('total_amount - discounted_amount')),
            'by_payment_method' => Payment::join('card_types', 'payments.payment_method_id', '=', 'card_types.id')
                ->select('card_types.name', DB::raw('count(*) as count'), DB::raw('sum(payments.amount) as total'))
                ->whereNotNull('payments.payment_method_id')
                ->where('payments.payment_status', 'completed')
                ->groupBy('card_types.id', 'card_types.name')
                ->get(),
            'recent_completed' => Payment::with([
                    'user:id,first_name,last_name,email',
                    'listing:id,title'
                ])
                ->completed()
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'user_id', 'listing_id', 'amount', 'created_at']),
        ];
    }

    /**
     * Statistiques d'engagement
     */
    private function getEngagementStats()
    {
        return [
            'total_wishlists' => Wishlist::count(),
            'users_with_wishlist' => Wishlist::distinct('user_id')->count('user_id'),
            'users_with_garage' => DB::table('my_garage')
                ->distinct('user_id')
                ->count('user_id'),
            'average_wishlist_per_user' => round(
                Wishlist::count() / max(Wishlist::distinct('user_id')->count('user_id'), 1),
                2
            ),
            'top_sellers' => Listing::join('users', 'listings.seller_id', '=', 'users.id')
                ->select(
                    'users.id',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    DB::raw('count(*) as total_listings'),
                    DB::raw('sum(case when listings.status = "sold" then 1 else 0 end) as sold_listings'),
                    DB::raw('sum(case when listings.status = "published" then 1 else 0 end) as active_listings'),
                    DB::raw('sum(case when listings.status = "sold" then listings.price else 0 end) as total_revenue')
                )
                ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->orderByDesc('total_listings')
                ->limit(10)
                ->get(),
            'most_wishlisted' => Listing::join('wishlists', 'listings.id', '=', 'wishlists.listing_id')
                ->select(
                    'listings.id',
                    'listings.title',
                    'listings.price',
                    'listings.status',
                    DB::raw('count(*) as wishlist_count')
                )
                ->groupBy('listings.id', 'listings.title', 'listings.price', 'listings.status')
                ->orderByDesc('wishlist_count')
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
                'archived' => Guide::where('status', 'archived')->count(),
                'total_views' => Guide::sum('views_count'),
                'most_viewed' => Guide::where('status', 'published')
                    ->orderByDesc('views_count')
                    ->limit(5)
                    ->get(['id', 'title', 'views_count', 'created_at']),
            ],
            'events' => [
                'total' => Event::count(),
                'upcoming' => Event::where('event_date', '>=', Carbon::today())
                    ->where('status', 'upcoming')
                    ->count(),
                'completed' => Event::where('status', 'completed')->count(),
                'cancelled' => Event::where('status', 'cancelled')->count(),
                'total_participants' => DB::table('event_participants')
                    ->where('status', 'registered')
                    ->count(),
            ],
            'routes' => [
                'total' => Route::count(),
                'verified' => Route::where('is_verified', true)->count(),
                'featured' => Route::where('is_featured', true)->count(),
                'total_completions' => DB::table('route_completions')->count(),
                'total_likes' => DB::table('route_likes')->count(),
            ],
            'poi' => [
                'total' => PointOfInterest::count(),
                'verified' => PointOfInterest::where('is_verified', true)->count(),
                'active' => PointOfInterest::where('is_active', true)->count(),
                'by_type' => PointOfInterest::join('poi_types', 'points_of_interest.type_id', '=', 'poi_types.id')
                    ->select('poi_types.name', DB::raw('count(*) as count'))
                    ->groupBy('poi_types.id', 'poi_types.name')
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
            'draft_listings' => Listing::where('status', 'draft')->count(),
            'payment_pending_listings' => Listing::where('payment_pending', true)
                ->where('status', 'draft')
                ->count(),
            'pending_comments' => DB::table('guide_comments')
                ->where('is_approved', false)
                ->count(),
            'poi_reports_pending' => DB::table('poi_reports')
                ->where('status', 'pending')
                ->count(),
            'expired_validation_submissions' => Submission::accepted()
                ->where('sale_validated', false)
                ->whereNotNull('acceptance_date')
                ->where('acceptance_date', '<=', Carbon::now()->subDays(5))
                ->count(),
            'recent_draft_listings' => Listing::where('status', 'draft')
                ->with([
                    'seller:id,first_name,last_name,email',
                    'category:id,name',
                    'city:id,name'
                ])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'title', 'price', 'payment_pending', 'created_at', 'seller_id', 'category_id', 'city_id']),
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
            'motorcycles_by_type' => $this->getMotorcyclesByTypeChart(),
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
            ->completed()
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
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('count')
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
            ->join('listings', 'motorcycles.listing_id', '=', 'listings.id')
            ->select('motorcycle_brands.name', DB::raw('count(*) as count'))
            ->where('listings.status', 'published')
            ->groupBy('motorcycle_brands.id', 'motorcycle_brands.name')
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
     * Graphique: Motos par type
     */
    private function getMotorcyclesByTypeChart()
    {
        $data = Motorcycle::join('motorcycle_types', 'motorcycles.type_id', '=', 'motorcycle_types.id')
            ->join('listings', 'motorcycles.listing_id', '=', 'listings.id')
            ->select('motorcycle_types.name', DB::raw('count(*) as count'))
            ->where('listings.status', 'published')
            ->groupBy('motorcycle_types.id', 'motorcycle_types.name')
            ->orderByDesc('count')
            ->get();

        return [
            'type' => 'doughnut',
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
            ->groupBy('cities.id', 'cities.name')
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
            ->join('listings', 'spare_parts.listing_id', '=', 'listings.id')
            ->select('bike_part_categories.name', DB::raw('count(*) as count'))
            ->where('listings.status', 'published')
            ->groupBy('bike_part_categories.id', 'bike_part_categories.name')
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
            ->orderByDesc('count')
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
            ->orderByDesc('count')
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
            ->orderByDesc('count')
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

        $accepted = Submission::accepted()->count();
        return round(($accepted / $total) * 100, 2);
    }

    /**
     * Calcul du taux de validation des ventes
     */
    private function calculateValidationRate()
    {
        $accepted = Submission::accepted()->count();
        if ($accepted == 0) return 0;

        $validated = Submission::validated()->count();
        return round(($validated / $accepted) * 100, 2);
    }

    /**
     * Calcul du taux de succès des paiements
     */
    private function calculatePaymentSuccessRate()
    {
        $total = Payment::count();
        if ($total == 0) return 0;

        $completed = Payment::completed()->count();
        return round(($completed / $total) * 100, 2);
    }

    /**
     * Statistiques des prospects
     */
    private function getProspectsStats()
    {
        $prospects = User::where('first_name', 'Prospect')
            ->withCount('listings')
            ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'created_at']);

        return [
            'total_prospects' => $prospects->count(),
            'prospects' => $prospects
        ];
    }
}
