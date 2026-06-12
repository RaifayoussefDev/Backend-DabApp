<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\CommissionSetting;
use App\Models\PaymentSplit;
use App\Models\Trainer;
use App\Models\TrainerBooking;
use App\Models\TrainerComment;
use App\Models\TrainerPayout;
use App\Models\TrainerReview;

/**
 * @OA\Tag(
 *     name="Admin - Trainer Stats",
 *     description="Global dashboard statistics for the Trainer module — counts, revenue, trends, moderation queues"
 * )
 */
class AdminTrainerStatsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/trainer-stats/dashboard",
     *     summary="Trainer module dashboard (Admin)",
     *     description="Returns a full overview of the Trainer module for the admin panel: trainer counts by status, booking counts by status, revenue / commission breakdown, moderation queues (pending reviews, comments, payouts), top 5 trainers by sessions, and a 7-day booking trend.",
     *     operationId="adminTrainerDashboard",
     *     tags={"Admin - Trainer Stats"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard data retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="trainers", type="object",
     *                     @OA\Property(property="total",     type="integer", example=52),
     *                     @OA\Property(property="pending",   type="integer", example=6),
     *                     @OA\Property(property="approved",  type="integer", example=40),
     *                     @OA\Property(property="rejected",  type="integer", example=4),
     *                     @OA\Property(property="suspended", type="integer", example=2)
     *                 ),
     *                 @OA\Property(property="bookings", type="object",
     *                     @OA\Property(property="total",       type="integer", example=320),
     *                     @OA\Property(property="pending",     type="integer", example=12),
     *                     @OA\Property(property="confirmed",   type="integer", example=28),
     *                     @OA\Property(property="in_progress", type="integer", example=3),
     *                     @OA\Property(property="completed",   type="integer", example=260),
     *                     @OA\Property(property="cancelled",   type="integer", example=14),
     *                     @OA\Property(property="rejected",    type="integer", example=3),
     *                     @OA\Property(property="this_month",  type="integer", example=34)
     *                 ),
     *                 @OA\Property(property="revenue", type="object",
     *                     @OA\Property(property="total_revenue",          type="number", format="float", example=48000.00),
     *                     @OA\Property(property="total_commission",       type="number", format="float", example=9600.00),
     *                     @OA\Property(property="total_trainer_earnings", type="number", format="float", example=38400.00),
     *                     @OA\Property(property="pending_payouts_amount", type="number", format="float", example=4200.00),
     *                     @OA\Property(property="paid_payouts_amount",    type="number", format="float", example=34200.00),
     *                     @OA\Property(property="this_month_revenue",     type="number", format="float", example=5100.00),
     *                     @OA\Property(property="global_commission_pct",  type="number", format="float", example=20.00)
     *                 ),
     *                 @OA\Property(property="moderation", type="object",
     *                     @OA\Property(property="pending_reviews",   type="integer", example=8),
     *                     @OA\Property(property="pending_comments",  type="integer", example=15),
     *                     @OA\Property(property="pending_payouts",   type="integer", example=7),
     *                     @OA\Property(property="approved_payouts",  type="integer", example=3)
     *                 ),
     *                 @OA\Property(property="top_trainers", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",             type="integer", example=1),
     *                         @OA\Property(property="name",           type="string",  example="Khalid Al-Mansouri"),
     *                         @OA\Property(property="specialty",      type="string",  example="coaching"),
     *                         @OA\Property(property="total_sessions", type="integer", example=48),
     *                         @OA\Property(property="rating_average", type="number",  format="float", example=4.9),
     *                         @OA\Property(property="likes_count",    type="integer", example=130)
     *                     )
     *                 ),
     *                 @OA\Property(property="bookings_trend_7d", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="date",  type="string", format="date", example="2026-06-06"),
     *                         @OA\Property(property="count", type="integer", example=7)
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Trainer dashboard stats retrieved")
     *         )
     *     )
     * )
     */
    public function dashboard()
    {
        $trainerStats = [
            'total'     => Trainer::count(),
            'pending'   => Trainer::where('status', 'pending')->count(),
            'approved'  => Trainer::where('status', 'approved')->count(),
            'rejected'  => Trainer::where('status', 'rejected')->count(),
            'suspended' => Trainer::where('status', 'suspended')->count(),
        ];

        $bookingStats = [
            'total'       => TrainerBooking::count(),
            'pending'     => TrainerBooking::where('status', 'pending')->count(),
            'confirmed'   => TrainerBooking::where('status', 'confirmed')->count(),
            'in_progress' => TrainerBooking::where('status', 'in_progress')->count(),
            'completed'   => TrainerBooking::where('status', 'completed')->count(),
            'cancelled'   => TrainerBooking::where('status', 'cancelled')->count(),
            'rejected'    => TrainerBooking::where('status', 'rejected')->count(),
            'this_month'  => TrainerBooking::whereMonth('created_at', now()->month)
                                           ->whereYear('created_at', now()->year)
                                           ->count(),
        ];

        $globalCommission = CommissionSetting::active()->global()->latest()->value('commission_percentage');

        $revenueStats = [
            'total_revenue'          => (float) PaymentSplit::sum('total_amount'),
            'total_commission'       => (float) PaymentSplit::sum('commission_amount'),
            'total_trainer_earnings' => (float) PaymentSplit::sum('trainer_amount'),
            'pending_payouts_amount' => (float) TrainerPayout::where('status', 'pending')->sum('amount'),
            'paid_payouts_amount'    => (float) TrainerPayout::where('status', 'paid')->sum('amount'),
            'this_month_revenue'     => (float) PaymentSplit::whereMonth('created_at', now()->month)
                                                            ->whereYear('created_at', now()->year)
                                                            ->sum('total_amount'),
            'global_commission_pct'  => $globalCommission ? (float) $globalCommission : null,
        ];

        $moderationStats = [
            'pending_reviews'  => TrainerReview::where('is_approved', false)->count(),
            'pending_comments' => TrainerComment::where('is_approved', false)->count(),
            'pending_payouts'  => TrainerPayout::where('status', 'pending')->count(),
            'approved_payouts' => TrainerPayout::where('status', 'approved')->count(),
        ];

        $topTrainers = Trainer::approved()
            ->orderByDesc('total_sessions')
            ->limit(5)
            ->get(['id', 'name', 'specialty', 'total_sessions', 'rating_average', 'likes_count']);

        $trend = collect(range(6, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo)->toDateString();
            return [
                'date'  => $date,
                'count' => TrainerBooking::whereDate('created_at', $date)->count(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'trainers'           => $trainerStats,
                'bookings'           => $bookingStats,
                'revenue'            => $revenueStats,
                'moderation'         => $moderationStats,
                'top_trainers'       => $topTrainers,
                'bookings_trend_7d'  => $trend,
            ],
            'message' => 'Trainer dashboard stats retrieved',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/trainer-stats/revenue",
     *     summary="Revenue breakdown by period (Admin)",
     *     description="Detailed revenue, commission and payout breakdown. Filter by month/year or custom date range.",
     *     operationId="adminTrainerRevenue",
     *     tags={"Admin - Trainer Stats"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="year",       in="query", required=false, @OA\Schema(type="integer", example=2026)),
     *     @OA\Parameter(name="month",      in="query", required=false, @OA\Schema(type="integer", example=6,  description="1–12")),
     *     @OA\Parameter(name="date_from",  in="query", required=false, @OA\Schema(type="string",  format="date", example="2026-01-01")),
     *     @OA\Parameter(name="date_to",    in="query", required=false, @OA\Schema(type="string",  format="date", example="2026-06-30")),
     *     @OA\Response(
     *         response=200,
     *         description="Revenue breakdown",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="period", type="string", example="2026-06"),
     *                 @OA\Property(property="total_revenue",          type="number", format="float", example=5100.00),
     *                 @OA\Property(property="total_commission",       type="number", format="float", example=1020.00),
     *                 @OA\Property(property="total_trainer_earnings", type="number", format="float", example=4080.00),
     *                 @OA\Property(property="total_bookings",         type="integer", example=34),
     *                 @OA\Property(property="completed_bookings",     type="integer", example=28),
     *                 @OA\Property(property="avg_booking_value",      type="number", format="float", example=150.00),
     *                 @OA\Property(property="paid_out",               type="number", format="float", example=3200.00),
     *                 @OA\Property(property="pending_payout",         type="number", format="float", example=880.00),
     *                 @OA\Property(property="per_trainer", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="trainer_id",         type="integer", example=1),
     *                         @OA\Property(property="trainer_name",       type="string",  example="Khalid"),
     *                         @OA\Property(property="sessions",           type="integer", example=12),
     *                         @OA\Property(property="total_earned",       type="number",  format="float", example=1800.00),
     *                         @OA\Property(property="commission_paid",    type="number",  format="float", example=360.00)
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function revenue(\Illuminate\Http\Request $request)
    {
        $query = PaymentSplit::query();

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('year') && !$request->filled('date_from')) {
            $query->whereYear('created_at', $request->year);
            if ($request->filled('month')) {
                $query->whereMonth('created_at', $request->month);
            }
        }

        $splits = $query->get();

        $period = $request->filled('date_from')
            ? "{$request->date_from} → {$request->date_to}"
            : ($request->filled('month')
                ? "{$request->get('year', now()->year)}-" . str_pad($request->month, 2, '0', STR_PAD_LEFT)
                : (string) $request->get('year', now()->year));

        // Per-trainer breakdown
        $perTrainer = $splits->groupBy('trainer_id')->map(function ($trainerSplits) {
            $trainer = $trainerSplits->first()->trainer;
            return [
                'trainer_id'      => $trainerSplits->first()->trainer_id,
                'trainer_name'    => $trainer ? $trainer->name : 'Unknown',
                'sessions'        => $trainerSplits->count(),
                'total_earned'    => round($trainerSplits->sum('trainer_amount'), 2),
                'commission_paid' => round($trainerSplits->sum('commission_amount'), 2),
            ];
        })->sortByDesc('total_earned')->values();

        // Booking stats for the period
        $bookingQuery = TrainerBooking::query();
        if ($request->filled('date_from')) {
            $bookingQuery->whereDate('created_at', '>=', $request->date_from)->whereDate('created_at', '<=', $request->date_to);
        } elseif ($request->filled('year')) {
            $bookingQuery->whereYear('created_at', $request->year);
            if ($request->filled('month')) {
                $bookingQuery->whereMonth('created_at', $request->month);
            }
        }

        $totalBookings     = $bookingQuery->count();
        $completedBookings = $bookingQuery->where('status', 'completed')->count();
        $totalRevenue      = round($splits->sum('total_amount'), 2);

        return response()->json([
            'success' => true,
            'data'    => [
                'period'                 => $period,
                'total_revenue'          => $totalRevenue,
                'total_commission'       => round($splits->sum('commission_amount'), 2),
                'total_trainer_earnings' => round($splits->sum('trainer_amount'), 2),
                'total_bookings'         => $totalBookings,
                'completed_bookings'     => $completedBookings,
                'avg_booking_value'      => $splits->count() ? round($totalRevenue / $splits->count(), 2) : 0,
                'paid_out'               => (float) TrainerPayout::where('status', 'paid')->sum('amount'),
                'pending_payout'         => (float) TrainerPayout::where('status', 'pending')->sum('amount'),
                'per_trainer'            => $perTrainer,
            ],
            'message' => 'Revenue breakdown retrieved',
        ]);
    }
}
