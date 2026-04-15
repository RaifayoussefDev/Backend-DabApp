<?php

namespace App\Http\Controllers\Assist\Helper;

use App\Http\Controllers\Assist\AssistBaseController;
use App\Http\Requests\Assist\UpdateMissionStatusRequest;
use App\Models\Assist\AssistanceRequest;
use App\Models\Assist\HelperProfile;
use App\Services\Assist\AssistNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Assist - Helper Mission",
 *     description="Update mission status (en_route → arrived → completed)"
 * )
 */
class HelperMissionController extends AssistBaseController
{
    public function __construct(
        private readonly AssistNotificationService $notificationService
    ) {}

    /**
     * @OA\Patch(
     *     path="/api/assist/helper/mission/{id}/status",
     *     summary="Update mission status",
     *     description="Allowed transitions: accepted → en_route → arrived → completed",
     *     tags={"Assist - Helper Mission"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="Assistance request ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string",
     *                 enum={"en_route","arrived","completed"},
     *                 example="en_route",
     *                 description="New status to set (must follow allowed transitions)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Status updated to en_route."),
     *             @OA\Property(property="data", ref="#/components/schemas/AssistanceRequest")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not the assigned helper"),
     *     @OA\Response(response=422, description="Invalid status transition")
     * )
     */
    public function updateStatus(UpdateMissionStatusRequest $request, string $id): JsonResponse
    {
        $assistRequest = AssistanceRequest::find($id);

        if (!$assistRequest) {
            return $this->error('Assistance request not found.', 404);
        }

        if ($assistRequest->helper_id !== Auth::id()) {
            return $this->error('You are not the assigned helper for this request.', 403);
        }

        $newStatus = $request->status;

        // Validate allowed transitions
        $allowed = [
            'accepted'  => ['en_route'],
            'en_route'  => ['arrived'],
            'arrived'   => ['completed'],
        ];

        $current = $assistRequest->status;
        if (!isset($allowed[$current]) || !in_array($newStatus, $allowed[$current])) {
            return $this->error(
                "Invalid transition: cannot go from '{$current}' to '{$newStatus}'.",
                422
            );
        }

        $timestamps = [
            'en_route'  => ['accepted_at'  => $assistRequest->accepted_at ?? now()],
            'arrived'   => ['arrived_at'   => now()],
            'completed' => ['completed_at' => now()],
        ];

        $updates = array_merge(['status' => $newStatus], $timestamps[$newStatus] ?? []);
        $assistRequest->update($updates);

        // Update helper total_assists counter on completion
        if ($newStatus === 'completed') {
            $profile = HelperProfile::where('user_id', Auth::id())->first();
            $profile?->increment('total_assists');
        }

        // Notify seeker of status change
        $this->notificationService->notify($assistRequest->seeker, $newStatus, $assistRequest);

        $assistRequest->load('expertiseTypes', 'seeker:id,first_name,last_name,phone');

        return $this->success($assistRequest, "Status updated to {$newStatus}.");
    }
}
