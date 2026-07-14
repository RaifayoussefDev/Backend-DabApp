<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Models\TrainerBooking;
use App\Models\TrainerCourseBooking;
use App\Models\TrainerPayout;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Trainer - Stats",
 *     description="Self-service dashboard stats for the authenticated trainer — reservation counts and payout amounts across both the flat one-off booking flow and the multi-session course flow"
 * )
 */
class TrainerStatsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/trainer/dashboard",
     *     summary="My dashboard stats (Trainer)",
     *     description="Returns reservation counts (flat bookings + course bookings combined) and payout amounts for the authenticated trainer.",
     *     operationId="myTrainerDashboard",
     *     tags={"Trainer - Stats"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Dashboard stats retrieved"),
     *     @OA\Response(response=403, description="No trainer profile found")
     * )
     */
    public function myDashboard()
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->first();

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $flat   = TrainerBooking::where('trainer_id', $trainer->id);
        $course = TrainerCourseBooking::where('trainer_id', $trainer->id);

        $reservations = [
            'total'       => (clone $flat)->count() + (clone $course)->count(),
            'upcoming'    => (clone $flat)->whereIn('status', ['pending', 'confirmed'])->count()
                            + (clone $course)->whereIn('status', ['pending', 'confirmed'])->count(),
            'in_progress' => (clone $flat)->where('status', 'in_progress')->count()
                            + (clone $course)->where('status', 'in_progress')->count(),
            'completed'   => (clone $flat)->where('status', 'completed')->count()
                            + (clone $course)->where('status', 'completed')->count(),
        ];

        $payments = [
            // Money owed to the trainer, not yet paid out — this is the "pending payment" a trainer is waiting on.
            'pending_payout_amount' => (float) TrainerPayout::where('trainer_id', $trainer->id)->where('status', 'pending')->sum('amount'),
            'paid_payout_amount'    => (float) TrainerPayout::where('trainer_id', $trainer->id)->where('status', 'paid')->sum('amount'),
            // Bookings/course-bookings whose trainee hasn't completed payment yet.
            'awaiting_trainee_payment' => (clone $flat)->where('payment_status', 'pending')->count()
                                        + (clone $course)->where('payment_status', 'pending')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'reservations' => $reservations,
                'payments'     => $payments,
            ],
            'message' => 'Trainer dashboard stats retrieved',
        ]);
    }
}
