<?php

namespace App\Http\Controllers\Assist\Helper;

use App\Http\Controllers\Assist\AssistBaseController;
use App\Http\Requests\Assist\UpdateMissionStatusRequest;
use App\Models\Assist\AssistanceRequest;
use App\Models\Assist\HelperProfile;
use App\Services\Assist\AssistNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * @OA\Get(
     *     path="/api/assist/helper/missions",
     *     summary="List the authenticated helper's missions",
     *     description="Returns a paginated history of all missions assigned to the helper. Supports filtering by status.",
     *     tags={"Assist - Helper Mission"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", required=false,
     *         description="Filter by mission status",
     *         @OA\Schema(type="string", enum={"accepted","en_route","arrived","completed","cancelled"}, example="completed")
     *     ),
     *     @OA\Parameter(name="page", in="query", required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of missions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page",     type="integer", example=15),
     *                 @OA\Property(property="total",        type="integer", example=12),
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",             type="integer", example=12),
     *                         @OA\Property(property="status",         type="string",  example="completed"),
     *                         @OA\Property(property="status_label",   type="object",
     *                             @OA\Property(property="en", type="string", example="Completed"),
     *                             @OA\Property(property="ar", type="string", example="مكتمل")
     *                         ),
     *                         @OA\Property(property="location_label", type="string",  example="King Fahd Road, Riyadh – near Exit 7"),
     *                         @OA\Property(property="latitude",       type="number",  format="float", example=24.714),
     *                         @OA\Property(property="longitude",      type="number",  format="float", example=46.675),
     *                         @OA\Property(property="accepted_at",    type="string",  format="date-time", nullable=true),
     *                         @OA\Property(property="arrived_at",     type="string",  format="date-time", nullable=true),
     *                         @OA\Property(property="completed_at",   type="string",  format="date-time", nullable=true),
     *                         @OA\Property(property="cancelled_at",   type="string",  format="date-time", nullable=true),
     *                         @OA\Property(property="expertise_types", type="array",
     *                             @OA\Items(type="object",
     *                                 @OA\Property(property="id",   type="integer", example=1),
     *                                 @OA\Property(property="name", type="string",  example="tire_repair"),
     *                                 @OA\Property(property="icon", type="string",  example="tire_repair")
     *                             )
     *                         ),
     *                         @OA\Property(property="seeker", type="object",
     *                             @OA\Property(property="id",         type="integer", example=65),
     *                             @OA\Property(property="first_name", type="string",  example="Raifa"),
     *                             @OA\Property(property="last_name",  type="string",  example="Youssef")
     *                         ),
     *                         @OA\Property(property="rating", type="object", nullable=true,
     *                             @OA\Property(property="stars",   type="integer", example=5),
     *                             @OA\Property(property="comment", type="string",  example="Super fast!")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Helper profile not found")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $profile = HelperProfile::where('user_id', Auth::id())->first();

        if (!$profile) {
            return $this->error('Helper profile not found.', 404);
        }

        $query = AssistanceRequest::with([
            'expertiseTypes:id,name,name_ar,name_en,icon',
            'seeker:id,first_name,last_name',
            'rating:id,request_id,stars,comment',
        ])
            ->where('helper_id', Auth::id())
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->success($query->paginate(15));
    }

    /**
     * @OA\Get(
     *     path="/api/assist/helper/mission/active",
     *     summary="Get the helper's current active mission",
     *     description="Returns the single mission currently in progress (status: accepted, en_route, or arrived). Returns 404 if the helper has no active mission.",
     *     tags={"Assist - Helper Mission"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Active mission",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",             type="integer", example=12),
     *                 @OA\Property(property="status",         type="string",  example="en_route"),
     *                 @OA\Property(property="status_label",   type="object",
     *                     @OA\Property(property="en", type="string", example="En route"),
     *                     @OA\Property(property="ar", type="string", example="في الطريق")
     *                 ),
     *                 @OA\Property(property="location_label", type="string",  example="King Fahd Road, Riyadh – near Exit 7"),
     *                 @OA\Property(property="latitude",       type="number",  format="float", example=24.714),
     *                 @OA\Property(property="longitude",      type="number",  format="float", example=46.675),
     *                 @OA\Property(property="accepted_at",    type="string",  format="date-time"),
     *                 @OA\Property(property="arrived_at",     type="string",  format="date-time", nullable=true),
     *                 @OA\Property(property="expertise_types", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",   type="integer", example=1),
     *                         @OA\Property(property="name", type="string",  example="tire_repair"),
     *                         @OA\Property(property="icon", type="string",  example="tire_repair")
     *                     )
     *                 ),
     *                 @OA\Property(property="seeker", type="object",
     *                     @OA\Property(property="id",         type="integer", example=65),
     *                     @OA\Property(property="first_name", type="string",  example="Raifa"),
     *                     @OA\Property(property="last_name",  type="string",  example="Youssef"),
     *                     @OA\Property(property="phone",      type="string",  example="+966501234567")
     *                 ),
     *                 @OA\Property(property="photos", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",   type="integer", example=1),
     *                         @OA\Property(property="path", type="string",  example="https://cdn.example.com/uploads/photo1.jpg")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="No active mission found")
     * )
     */
    public function active(): JsonResponse
    {
        $mission = AssistanceRequest::with([
            'expertiseTypes:id,name,name_ar,name_en,icon',
            'seeker:id,first_name,last_name,phone',
            'photos',
        ])
            ->where('helper_id', Auth::id())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->latest('accepted_at')
            ->first();

        if (!$mission) {
            return $this->error('No active mission found.', 404);
        }

        return $this->success($mission);
    }

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
     *             example={"status": "en_route"},
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
     *                 @OA\Property(property="id",               type="integer", example=12),
     *                 @OA\Property(property="status",           type="string",  example="en_route"),
     *                 @OA\Property(property="accepted_at",      type="string",  format="date-time", example="2026-04-15T10:05:00.000000Z"),
     *                 @OA\Property(property="arrived_at",       type="string",  format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="completed_at",     type="string",  format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="completion_token", type="string",  nullable=true, example="a3f2b1c4d5e6f7a8b9c0d1e2f3a4b5c6", description="QR token generated when status becomes `arrived`. Show as QR code to the seeker for validation."),
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
    /**
     * @OA\Post(
     *     path="/api/assist/helper/mission/{id}/cancel",
     *     summary="Cancel an accepted mission",
     *     description="Allows the helper to cancel a mission they accepted, as long as it has not yet been completed or already cancelled. The request is reset to `pending` so another helper can pick it up. The seeker is notified.",
     *     tags={"Assist - Helper Mission"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="Assistance request ID",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mission cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Mission cancelled. The request is back in the feed.")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="You are not the assigned helper for this request"),
     *     @OA\Response(response=404, description="Request not found"),
     *     @OA\Response(response=422, description="Mission cannot be cancelled at this stage")
     * )
     */
    public function cancel(string $id): JsonResponse
    {
        $assistRequest = AssistanceRequest::find($id);

        if (!$assistRequest) {
            return $this->error('Assistance request not found.', 404);
        }

        if ($assistRequest->helper_id !== Auth::id()) {
            return $this->error('You are not the assigned helper for this request.', 403);
        }

        $cancellable = ['accepted', 'en_route', 'arrived'];

        if (!in_array($assistRequest->status, $cancellable)) {
            return $this->error(
                "Mission cannot be cancelled at this stage (current status: {$assistRequest->status}).",
                422
            );
        }

        $seeker = $assistRequest->seeker;

        $assistRequest->update([
            'status'    => 'pending',
            'helper_id' => null,
            'accepted_at' => null,
            'arrived_at'  => null,
        ]);

        $this->notificationService->notify($seeker, 'helper_cancelled', $assistRequest);

        return $this->success(null, 'Mission cancelled. The request is back in the feed.');
    }

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
            'arrived'   => ['arrived_at'   => now(), 'completion_token' => bin2hex(random_bytes(16))],
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

        $responseData = $assistRequest->toArray();
        if ($newStatus === 'arrived') {
            $responseData['completion_token'] = $assistRequest->completion_token;
        }

        return $this->success($responseData, "Status updated to {$newStatus}.");
    }
}
