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
 *     description="Update mission status as the helper progresses to the seeker"
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
     *     description="Push the mission through its lifecycle. Allowed transitions: `accepted` → `en_route` → `arrived` → `completed`. Each transition notifies the seeker.",
     *     tags={"Assist - Helper Mission"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="Assistance request ID",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string",
     *                 enum={"en_route","arrived","completed"},
     *                 example="en_route",
     *                 description="New status. Must follow the allowed transition chain: accepted → en_route → arrived → completed"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Status updated to en_route."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",             type="integer", example=12),
     *                 @OA\Property(property="status",         type="string",  example="en_route"),
     *                 @OA\Property(property="accepted_at",    type="string",  format="date-time", example="2026-04-15T10:05:00.000000Z"),
     *                 @OA\Property(property="arrived_at",     type="string",  format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="completed_at",   type="string",  format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="location_label", type="string",  example="King Fahd Road, Riyadh – near Exit 7"),
     *                 @OA\Property(property="expertise_types", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",   type="integer", example=1),
     *                         @OA\Property(property="name", type="string",  example="tire_repair")
     *                     )
     *                 ),
     *                 @OA\Property(property="seeker", type="object",
     *                     @OA\Property(property="id",         type="integer", example=65),
     *                     @OA\Property(property="first_name", type="string",  example="Raifa"),
     *                     @OA\Property(property="last_name",  type="string",  example="Youssef"),
     *                     @OA\Property(property="phone",      type="string",  example="+966501234567")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="You are not the assigned helper for this request"),
     *     @OA\Response(response=404, description="Request not found"),
     *     @OA\Response(response=422, description="Invalid status transition",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string",  example="Invalid transition: cannot go from 'pending' to 'en_route'.")
     *         )
     *     )
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
