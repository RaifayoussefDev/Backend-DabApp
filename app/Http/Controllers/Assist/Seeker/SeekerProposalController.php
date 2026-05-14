<?php

namespace App\Http\Controllers\Assist\Seeker;

use App\Http\Controllers\Assist\AssistBaseController;
use App\Models\Assist\AssistanceRequest;
use App\Models\Assist\AssistProposal;
use App\Models\Assist\HelperProfile;
use App\Services\Assist\AssistNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Assist - Seeker Proposals",
 *     description="List, accept, or reject helper proposals for a pending request"
 * )
 */
class SeekerProposalController extends AssistBaseController
{
    public function __construct(
        private readonly AssistNotificationService $notificationService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/assist/seeker/request/{id}/proposals",
     *     summary="List all proposals received for a request",
     *     description="Returns all helper proposals for this request (pending, accepted, rejected). Each entry includes the helper's profile picture, full name, rating, and proposed price.",
     *     tags={"Assist - Seeker Proposals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="Assistance request ID",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of proposals",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id",             type="integer", example=7),
     *                     @OA\Property(property="proposed_price", type="integer", example=100),
     *                     @OA\Property(property="status",         type="string",  example="pending",
     *                         description="pending | accepted | rejected"),
     *                     @OA\Property(property="created_at",     type="string",  format="date-time"),
     *                     @OA\Property(property="helper", type="object",
     *                         @OA\Property(property="id",              type="integer", example=2),
     *                         @OA\Property(property="first_name",      type="string",  example="Ahmed"),
     *                         @OA\Property(property="last_name",       type="string",  example="Al-Rashid"),
     *                         @OA\Property(property="profile_picture", type="string",  nullable=true, example="https://cdn.example.com/avatar.jpg"),
     *                         @OA\Property(property="rating",          type="number",  format="float", example=4.8,
     *                             description="Helper's average rating (0-5)"),
     *                         @OA\Property(property="total_assists",   type="integer", example=23),
     *                         @OA\Property(property="latitude",        type="number",  format="float", nullable=true, example=33.5731,
     *                             description="Helper's current GPS latitude — use with longitude to estimate ETA"),
     *                         @OA\Property(property="longitude",       type="number",  format="float", nullable=true, example=-7.5898,
     *                             description="Helper's current GPS longitude")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="This request does not belong to you"),
     *     @OA\Response(response=404, description="Request not found")
     * )
     */
    public function index(string $id): JsonResponse
    {
        $assistRequest = AssistanceRequest::find($id);

        if (!$assistRequest) {
            return $this->error('Assistance request not found.', 404);
        }

        if ($assistRequest->seeker_id !== Auth::id()) {
            return $this->error('Forbidden.', 403);
        }

        $proposals = AssistProposal::where('request_id', $id)
            ->with('helper:id,first_name,last_name,profile_picture')
            ->orderBy('proposed_price')
            ->orderBy('created_at')
            ->get()
            ->map(function ($proposal) {
                $helperProfile = HelperProfile::where('user_id', $proposal->helper_id)
                    ->select('rating', 'total_assists', 'latitude', 'longitude')
                    ->first();

                return [
                    'id'             => $proposal->id,
                    'proposed_price' => $proposal->proposed_price,
                    'status'         => $proposal->status,
                    'created_at'     => $proposal->created_at,
                    'helper'         => [
                        'id'              => $proposal->helper->id,
                        'first_name'      => $proposal->helper->first_name,
                        'last_name'       => $proposal->helper->last_name,
                        'profile_picture' => $proposal->helper->profile_picture,
                        'rating'          => $helperProfile?->rating ?? 0,
                        'total_assists'   => $helperProfile?->total_assists ?? 0,
                        'latitude'        => $helperProfile?->latitude,
                        'longitude'       => $helperProfile?->longitude,
                    ],
                ];
            });

        return $this->success($proposals);
    }

    /**
     * @OA\Post(
     *     path="/api/assist/seeker/request/{id}/proposals/{proposalId}/accept",
     *     summary="Accept a helper's proposal",
     *     description="Accepts one proposal and automatically rejects all others for this request. The request moves to `accepted` status and the chosen helper is assigned. All affected helpers receive a notification.",
     *     tags={"Assist - Seeker Proposals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id",         in="path", required=true, @OA\Schema(type="integer", example=12)),
     *     @OA\Parameter(name="proposalId", in="path", required=true, @OA\Schema(type="integer", example=7)),
     *     @OA\Response(
     *         response=200,
     *         description="Proposal accepted — helper assigned, others auto-rejected",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Proposal accepted. Your helper is on the way."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="request_id",     type="integer", example=12),
     *                 @OA\Property(property="status",         type="string",  example="accepted"),
     *                 @OA\Property(property="accepted_price", type="integer", example=100),
     *                 @OA\Property(property="helper", type="object",
     *                     @OA\Property(property="id",              type="integer", example=2),
     *                     @OA\Property(property="first_name",      type="string",  example="Ahmed"),
     *                     @OA\Property(property="last_name",       type="string",  example="Al-Rashid"),
     *                     @OA\Property(property="profile_picture", type="string",  nullable=true),
     *                     @OA\Property(property="phone",           type="string",  example="+966501234567")
     *                 ),
     *                 @OA\Property(property="rejected_count", type="integer", example=2,
     *                     description="Number of other proposals that were auto-rejected")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Request or proposal not found"),
     *     @OA\Response(response=409, description="Request is no longer in pending status")
     * )
     */
    public function accept(string $id, string $proposalId): JsonResponse
    {
        $assistRequest = AssistanceRequest::find($id);

        if (!$assistRequest) {
            return $this->error('Assistance request not found.', 404);
        }

        if ($assistRequest->seeker_id !== Auth::id()) {
            return $this->error('Forbidden.', 403);
        }

        if ($assistRequest->status !== 'pending') {
            return $this->error(
                "Cannot accept a proposal when request status is '{$assistRequest->status}'.",
                409
            );
        }

        $proposal = AssistProposal::where('id', $proposalId)
            ->where('request_id', $id)
            ->where('status', 'pending')
            ->first();

        if (!$proposal) {
            return $this->error('Proposal not found or already processed.', 404);
        }

        DB::beginTransaction();
        try {
            // Accept chosen proposal
            $proposal->update([
                'status'      => 'accepted',
                'accepted_at' => now(),
            ]);

            // Assign helper and lock in price
            $assistRequest->update([
                'helper_id'      => $proposal->helper_id,
                'status'         => 'accepted',
                'accepted_at'    => now(),
                'accepted_price' => $proposal->proposed_price,
            ]);

            // Auto-reject all other pending proposals
            $rejected = AssistProposal::where('request_id', $id)
                ->where('id', '!=', $proposalId)
                ->where('status', 'pending')
                ->get();

            AssistProposal::where('request_id', $id)
                ->where('id', '!=', $proposalId)
                ->where('status', 'pending')
                ->update([
                    'status'         => 'rejected',
                    'rejection_type' => 'auto',
                    'rejected_at'    => now(),
                ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to accept proposal.', 500);
        }

        // Notify accepted helper
        $acceptedHelper = $proposal->helper;
        $this->notificationService->notify($acceptedHelper, 'proposal_accepted', $assistRequest);

        // Notify each auto-rejected helper
        foreach ($rejected as $rejectedProposal) {
            if ($rejectedHelper = $rejectedProposal->helper) {
                $this->notificationService->notify($rejectedHelper, 'proposal_rejected', $assistRequest);
            }
        }

        $acceptedHelper->load([]);
        $acceptedHelper->setVisible(['id', 'first_name', 'last_name', 'profile_picture', 'phone']);

        return $this->success([
            'request_id'     => $assistRequest->id,
            'status'         => 'accepted',
            'accepted_price' => $proposal->proposed_price,
            'helper'         => $acceptedHelper->only(['id', 'first_name', 'last_name', 'profile_picture', 'phone']),
            'rejected_count' => $rejected->count(),
        ], 'Proposal accepted. Your helper is on the way.');
    }

    /**
     * @OA\Delete(
     *     path="/api/assist/seeker/request/{id}/proposals/{proposalId}",
     *     summary="Manually reject a helper's proposal",
     *     description="Rejects a single pending proposal. The helper is notified. Other proposals remain unaffected.",
     *     tags={"Assist - Seeker Proposals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id",         in="path", required=true, @OA\Schema(type="integer", example=12)),
     *     @OA\Parameter(name="proposalId", in="path", required=true, @OA\Schema(type="integer", example=7)),
     *     @OA\Response(
     *         response=200,
     *         description="Proposal rejected",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Proposal rejected.")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Request or proposal not found")
     * )
     */
    public function reject(string $id, string $proposalId): JsonResponse
    {
        $assistRequest = AssistanceRequest::find($id);

        if (!$assistRequest) {
            return $this->error('Assistance request not found.', 404);
        }

        if ($assistRequest->seeker_id !== Auth::id()) {
            return $this->error('Forbidden.', 403);
        }

        $proposal = AssistProposal::where('id', $proposalId)
            ->where('request_id', $id)
            ->where('status', 'pending')
            ->first();

        if (!$proposal) {
            return $this->error('Proposal not found or already processed.', 404);
        }

        $proposal->update([
            'status'         => 'rejected',
            'rejection_type' => 'manual',
            'rejected_at'    => now(),
        ]);

        // Notify rejected helper
        if ($helper = $proposal->helper) {
            $this->notificationService->notify($helper, 'proposal_rejected', $assistRequest);
        }

        return $this->success(null, 'Proposal rejected.');
    }
}
