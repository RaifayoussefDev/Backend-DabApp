<?php

namespace App\Http\Controllers\Assist\Helper;

use App\Http\Controllers\Assist\AssistBaseController;
use App\Models\Assist\AssistanceRequest;
use App\Models\Assist\AssistPriceConfig;
use App\Models\Assist\AssistProposal;
use App\Models\Assist\HelperProfile;
use App\Services\Assist\AssistNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Assist - Helper Proposal",
 *     description="Submit a price proposal for a pending assistance request"
 * )
 */
class HelperProposalController extends AssistBaseController
{
    public function __construct(
        private readonly AssistNotificationService $notificationService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/assist/helper/feed/{id}/propose",
     *     summary="Submit a price proposal for a pending request",
     *     description="The helper submits their proposed price for a pending assistance request. The price must be a multiple of the configured step and fall within [price_min, price_max]. The seeker is notified immediately. A helper can only submit one proposal per request.",
     *     tags={"Assist - Helper Proposal"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="Assistance request ID",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"proposed_price"},
     *             example={"proposed_price": 100},
     *             @OA\Property(property="proposed_price", type="integer", example=100,
     *                 description="Proposed price. Must be a multiple of price_step and within [price_min, price_max]. Example valid values when step=50, max=150: 0, 50, 100, 150")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Proposal submitted — seeker notified",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Proposal submitted. Waiting for the seeker to accept."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",             type="integer", example=7),
     *                 @OA\Property(property="request_id",     type="integer", example=12),
     *                 @OA\Property(property="proposed_price", type="integer", example=100),
     *                 @OA\Property(property="status",         type="string",  example="pending"),
     *                 @OA\Property(property="created_at",     type="string",  format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid price — not a valid step multiple or out of range"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Profile not verified or not available"),
     *     @OA\Response(response=404, description="Request not found or no longer pending"),
     *     @OA\Response(response=409, description="Already proposed on this request or has an active mission")
     * )
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $profile = HelperProfile::where('user_id', Auth::id())->first();

        if (!$profile || !$profile->is_verified || !$profile->is_available) {
            return $this->error('You must be verified and available to submit proposals.', 403);
        }

        $alreadyActive = AssistanceRequest::where('helper_id', Auth::id())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->exists();

        if ($alreadyActive) {
            return $this->error('You already have an active mission. Complete or cancel it first.', 409);
        }

        $assistRequest = AssistanceRequest::where('id', $id)
            ->where('status', 'pending')
            ->where('seeker_id', '!=', Auth::id())
            ->first();

        if (!$assistRequest) {
            return $this->error('Request not found or no longer available.', 404);
        }

        $alreadyProposed = AssistProposal::where('request_id', $id)
            ->where('helper_id', Auth::id())
            ->exists();

        if ($alreadyProposed) {
            return $this->error('You have already submitted a proposal for this request.', 409);
        }

        $data = $request->validate([
            'proposed_price' => 'required|integer|min:0',
        ]);

        $config = AssistPriceConfig::current();

        if (!$config->isValidPrice($data['proposed_price'])) {
            return $this->error(
                "Invalid price. Must be a multiple of {$config->price_step} between {$config->price_min} and {$config->price_max}. "
                . 'Valid options: ' . implode(', ', $config->validPrices()) . '.',
                400
            );
        }

        $proposal = AssistProposal::create([
            'request_id'     => $assistRequest->id,
            'helper_id'      => Auth::id(),
            'proposed_price' => $data['proposed_price'],
            'status'         => 'pending',
        ]);

        // Notify seeker: a new proposal arrived
        $this->notificationService->notify($assistRequest->seeker, 'proposal_received', $assistRequest);

        return $this->success($proposal, 'Proposal submitted. Waiting for the seeker to accept.', 201);
    }
}
