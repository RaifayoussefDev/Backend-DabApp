<?php

namespace App\Http\Controllers\Assist\Helper;

use App\Http\Controllers\Assist\AssistBaseController;
use App\Models\Assist\AssistanceRequest;
use App\Models\Assist\HelperProfile;
use App\Services\Assist\AssistNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Assist - Helper Feed",
 *     description="Browse nearby open requests and accept missions"
 * )
 */
class HelperFeedController extends AssistBaseController
{
    public function __construct(
        private readonly AssistNotificationService $notificationService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/assist/helper/feed",
     *     summary="List pending assistance requests near the helper",
     *     tags={"Assist - Helper Feed"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of nearby pending requests",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     allOf={@OA\Schema(ref="#/components/schemas/AssistanceRequest")},
     *                     @OA\Property(property="distance_km", type="number", format="float", example=2.3)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Profile not verified or available"),
     *     @OA\Response(response=404, description="Helper profile not found")
     * )
     */
    public function index(): JsonResponse
    {
        $profile = HelperProfile::with('expertiseTypes')
            ->where('user_id', Auth::id())
            ->first();

        if (!$profile) {
            return $this->error('Helper profile not found.', 404);
        }

        if (!$profile->is_verified || !$profile->is_available) {
            return $this->error('You must be verified and available to view the feed.', 403);
        }

        if (!$profile->latitude || !$profile->longitude) {
            return $this->error('Please update your GPS location first.', 422);
        }

        $lat      = (float) $profile->latitude;
        $lng      = (float) $profile->longitude;
        $haversine = HelperProfile::haversineExpression();

        $expertiseIds = $profile->expertiseTypes->pluck('id')->all();

        $requests = AssistanceRequest::selectRaw("*, ({$haversine}) AS distance_km", [$lat, $lng, $lat])
            ->where('status', 'pending')
            ->whereHas('expertiseTypes', fn($q) => $q->whereIn('expertise_types.id', $expertiseIds))
            ->having('distance_km', '<=', $profile->service_radius_km)
            ->orderBy('distance_km')
            ->with(['expertiseTypes', 'seeker:id,first_name,last_name', 'photos', 'motorcycle.brand', 'motorcycle.model', 'motorcycle.year'])
            ->get()
            ->each(fn($r) => $r->seeker?->setVisible(['id', 'first_name', 'last_name']));

        return $this->success($requests);
    }

    /**
     * @OA\Post(
     *     path="/api/assist/helper/feed/{id}/accept",
     *     summary="Accept a pending assistance request",
     *     tags={"Assist - Helper Feed"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="Assistance request ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Request accepted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Request accepted. Head to the rider's location."),
     *             @OA\Property(property="data", ref="#/components/schemas/AssistanceRequest")
     *         )
     *     ),
     *     @OA\Response(response=409, description="Request already taken"),
     *     @OA\Response(response=403, description="Not verified or available")
     * )
     */
    public function accept(string $id): JsonResponse
    {
        $profile = HelperProfile::where('user_id', Auth::id())->first();

        if (!$profile || !$profile->is_verified || !$profile->is_available) {
            return $this->error('You must be verified and available to accept requests.', 403);
        }

        $assistRequest = AssistanceRequest::lockForUpdate()->find($id);

        if (!$assistRequest) {
            return $this->error('Assistance request not found.', 404);
        }

        if ($assistRequest->status !== 'pending') {
            return $this->error('This request is no longer available.', 409);
        }

        $assistRequest->update([
            'helper_id'   => Auth::id(),
            'status'      => 'accepted',
            'accepted_at' => now(),
        ]);

        $this->notificationService->notify($assistRequest->seeker, 'accepted', $assistRequest);

        $assistRequest->load('expertiseTypes', 'seeker:id,first_name,last_name,phone');

        return $this->success($assistRequest, "Request accepted. Head to the rider's location.");
    }
}
