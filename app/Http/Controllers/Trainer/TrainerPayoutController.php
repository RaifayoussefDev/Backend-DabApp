<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\PaymentSplit;
use App\Models\Trainer;
use App\Models\TrainerPayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Trainer Payouts",
 *     description="Trainer-facing: check available balance and request a monthly payout"
 * )
 */
class TrainerPayoutController extends Controller
{
    private function getTrainer(): ?Trainer
    {
        return Trainer::where('user_id', JWTAuth::parseToken()->authenticate()->id)->first();
    }

    /**
     * The current calendar-month window used for the one-request-per-month rule.
     * A rejected ('failed') request doesn't count — its splits are released back to
     * unassigned on rejection, so blocking a resubmission (e.g. after fixing a typo'd
     * IBAN) until next month would strand that money for no reason.
     */
    private function currentMonthRequest(Trainer $trainer): ?TrainerPayout
    {
        return TrainerPayout::where('trainer_id', $trainer->id)
            ->where('status', '!=', 'failed')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->first();
    }

    /**
     * Unassigned pending splits, restricted to a single currency so a payout never silently
     * sums e.g. SAR and AED earnings together under one label. Whichever currency has the
     * larger outstanding balance is treated as the trainer's active one for this request;
     * splits in any other currency are left unassigned for a future payout.
     */
    private function unassignedSplitsForPrimaryCurrency(Trainer $trainer): Collection
    {
        $splits = PaymentSplit::where('trainer_id', $trainer->id)
            ->unassigned()
            ->where('status', 'pending')
            ->get();

        if ($splits->isEmpty()) {
            return $splits;
        }

        $primaryCurrency = $splits->groupBy('currency')
            ->sortByDesc(fn ($group) => $group->sum('trainer_amount'))
            ->keys()
            ->first();

        return $splits->where('currency', $primaryCurrency)->values();
    }

    /**
     * @OA\Get(
     *     path="/api/trainer/payouts/balance",
     *     summary="Available balance for payout",
     *     description="Sums the trainer's unpaid, unassigned booking earnings (both direct sessions and course bookings) — this is what a payout request would cover. Also reports whether a request is allowed this calendar month.",
     *     operationId="getTrainerPayoutBalance",
     *     tags={"Trainer Payouts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Balance retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="balance",          type="number", format="float", example=540.00),
     *                 @OA\Property(property="currency",         type="string", example="SAR"),
     *                 @OA\Property(property="bookings_count",   type="integer", example=4),
     *                 @OA\Property(property="can_request",      type="boolean", example=true),
     *                 @OA\Property(property="reason",           type="string", nullable=true, example="A payout was already requested this month"),
     *                 @OA\Property(property="next_request_available_at", type="string", format="date", nullable=true, example="2026-08-01")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="No trainer profile found")
     * )
     */
    public function balance()
    {
        $trainer = $this->getTrainer();
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $splits = $this->unassignedSplitsForPrimaryCurrency($trainer);

        $existingRequest = $this->currentMonthRequest($trainer);

        $canRequest = !$existingRequest && $splits->sum('trainer_amount') > 0;
        $reason = null;
        if ($existingRequest) {
            $reason = 'A payout was already requested this month';
        } elseif ($splits->isEmpty()) {
            $reason = 'No available balance to request';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'balance'                    => (string) $splits->sum('trainer_amount'),
                'currency'                   => $splits->first()->currency ?? 'SAR',
                'bookings_count'             => $splits->count(),
                'can_request'                => $canRequest,
                'reason'                     => $reason,
                'next_request_available_at'  => $existingRequest ? now()->addMonthNoOverflow()->startOfMonth()->toDateString() : null,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/trainer/payouts",
     *     summary="My payout request history",
     *     operationId="getMyTrainerPayouts",
     *     tags={"Trainer Payouts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Payout history retrieved"),
     *     @OA\Response(response=403, description="No trainer profile found")
     * )
     */
    public function index(Request $request)
    {
        $trainer = $this->getTrainer();
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $payouts = TrainerPayout::where('trainer_id', $trainer->id)
            ->withCount('splits')
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $payouts,
            'message' => 'Payout history retrieved successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/trainer/payouts/request",
     *     summary="Request the monthly payout",
     *     description="Consolidates every unpaid, unassigned booking/course-booking earning into one payout request, submitted with the trainer's bank details for the transfer. Limited to one request per calendar month.",
     *     operationId="requestTrainerPayout",
     *     tags={"Trainer Payouts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"bank_name","iban"},
     *             @OA\Property(property="bank_name", type="string", example="Al Rajhi Bank"),
     *             @OA\Property(property="iban",      type="string", example="SA0380000000608010167519")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Payout requested",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Payout requested successfully"),
     *             @OA\Property(property="data",    type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Already requested this month, or no available balance"),
     *     @OA\Response(response=403, description="No trainer profile found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $trainer = $this->getTrainer();
        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 403);
        }

        $validated = $request->validate([
            'bank_name' => 'required|string|max:255',
            'iban'      => 'required|string|max:34',
        ]);

        try {
            $payout = DB::transaction(function () use ($trainer, $validated) {
                // Lock this trainer's rows for the duration of the transaction so two
                // concurrent/double-clicked requests can't both pass the "already
                // requested this month" check before either has committed a row.
                $existing = TrainerPayout::where('trainer_id', $trainer->id)
                    ->where('status', '!=', 'failed')
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    throw new \RuntimeException('ALREADY_REQUESTED');
                }

                $splitIds = PaymentSplit::where('trainer_id', $trainer->id)
                    ->unassigned()
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->pluck('id');

                // Re-fetch (still within the lock) to compute the primary-currency subset now
                // that concurrent access is blocked, using the same grouping rule as balance().
                $splits = PaymentSplit::whereIn('id', $splitIds)->get();

                if ($splits->isEmpty()) {
                    throw new \RuntimeException('NO_BALANCE');
                }

                $primaryCurrency = $splits->groupBy('currency')
                    ->sortByDesc(fn ($group) => $group->sum('trainer_amount'))
                    ->keys()
                    ->first();

                $splits = $splits->where('currency', $primaryCurrency)->values();

                if ($splits->sum('trainer_amount') <= 0) {
                    throw new \RuntimeException('NO_BALANCE');
                }

                $payout = TrainerPayout::create([
                    'trainer_id'      => $trainer->id,
                    'amount'          => $splits->sum('trainer_amount'),
                    // Preserved independent of the live payout_id FK, which moves on if this
                    // request is later rejected and the splits get reassigned to a future payout.
                    'splits_snapshot' => $splits->map(fn ($s) => [
                        'id' => $s->id,
                        'booking_id' => $s->booking_id,
                        'course_booking_id' => $s->course_booking_id,
                        'total_amount' => $s->total_amount,
                        'commission_percentage' => $s->commission_percentage,
                        'commission_amount' => $s->commission_amount,
                        'trainer_amount' => $s->trainer_amount,
                        'currency' => $s->currency,
                        'status' => $s->status,
                    ])->values()->all(),
                    'currency'   => $primaryCurrency ?? 'SAR',
                    'status'     => 'pending',
                    'bank_name'  => $validated['bank_name'],
                    'iban'       => $validated['iban'],
                ]);

                PaymentSplit::whereIn('id', $splits->pluck('id'))->update(['payout_id' => $payout->id]);

                return $payout;
            });
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'ALREADY_REQUESTED' => response()->json(['success' => false, 'message' => 'A payout was already requested this month'], 400),
                'NO_BALANCE' => response()->json(['success' => false, 'message' => 'No available balance to request'], 400),
                default => throw $e,
            };
        }

        return response()->json([
            'success' => true,
            'message' => 'Payout requested successfully',
            'data'    => $payout->fresh(),
        ], 201);
    }
}
