<?php

namespace App\Http\Controllers\Assist\Seeker;

use App\Http\Controllers\Assist\AssistBaseController;
use App\Http\Requests\Assist\CreateAssistanceRequestRequest;
use App\Http\Requests\Assist\RateHelperRequest;
use App\Models\Assist\AssistanceRequest;
use App\Models\Assist\Rating;
use App\Services\Assist\HelperMatchingService;
use App\Services\Assist\AssistNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Assist - Seeker",
 *     description="Endpoints for riders seeking roadside assistance"
 * )
 */
class AssistanceRequestController extends AssistBaseController
{
    public function __construct(
        private readonly HelperMatchingService   $matchingService,
        private readonly AssistNotificationService $notificationService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/assist/seeker/request",
     *     summary="Create a new assistance request",
     *     tags={"Assist - Seeker"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"expertise_type_id","latitude","longitude","location_label"},
     *             @OA\Property(property="expertise_type_id", type="string", format="uuid",
     *                 example="550e8400-e29b-41d4-a716-446655440001",
     *                 description="ID of the required expertise type"),
     *             @OA\Property(property="latitude", type="number", format="float", example=24.7136,
     *                 description="Rider's current latitude"),
     *             @OA\Property(property="longitude", type="number", format="float", example=46.6753,
     *                 description="Rider's current longitude"),
     *             @OA\Property(property="location_label", type="string", example="King Fahd Road, Riyadh",
     *                 description="Human-readable location name"),
     *             @OA\Property(property="description", type="string", nullable=true,
     *                 example="My rear tire is flat, near the gas station"),
     *             @OA\Property(property="motorcycle_id", type="string", format="uuid", nullable=true,
     *                 example=null, description="Optional: linked motorcycle UUID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Request created and helpers notified",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Assistance request created. Looking for helpers."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="request", ref="#/components/schemas/AssistanceRequest"),
     *                 @OA\Property(property="helpers_notified", type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(CreateAssistanceRequestRequest $request): JsonResponse
    {
        $user = Auth::user();

        DB::beginTransaction();
        try {
            $assistRequest = AssistanceRequest::create([
                'seeker_id'         => $user->id,
                'expertise_type_id' => $request->expertise_type_id,
                'latitude'          => $request->latitude,
                'longitude'         => $request->longitude,
                'location_label'    => $request->location_label,
                'description'       => $request->description,
                'motorcycle_id'     => $request->motorcycle_id,
                'status'            => 'pending',
            ]);

            // Handle photo uploads
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('assist/requests/' . $assistRequest->id, 'public');
                    $assistRequest->photos()->create(['path' => $path]);
                }
            }

            $helpers = $this->matchingService->findNearby($assistRequest);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to create assistance request.', 500);
        }

        $assistRequest->load(['expertiseType', 'photos', 'motorcycle']);

        return $this->success([
            'request'          => $assistRequest,
            'helpers_notified' => $helpers->count(),
        ], 'Assistance request created. Looking for helpers.', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/assist/seeker/request/{id}",
     *     summary="Get assistance request details and live status",
     *     tags={"Assist - Seeker"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="Assistance request UUID",
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440010")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Request details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(property="data", ref="#/components/schemas/AssistanceRequest")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $request = AssistanceRequest::with([
            'expertiseType', 'photos', 'motorcycle.brand', 'motorcycle.model',
            'helper:id,first_name,last_name,phone,profile_picture',
            'rating',
        ])->find($id);

        if (!$request) {
            return $this->error('Assistance request not found.', 404);
        }

        if ($request->seeker_id !== Auth::id()) {
            return $this->error('Forbidden.', 403);
        }

        return $this->success($request);
    }

    /**
     * @OA\Delete(
     *     path="/api/assist/seeker/request/{id}",
     *     summary="Cancel an assistance request",
     *     tags={"Assist - Seeker"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440010")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="cancel_reason", type="string", nullable=true,
     *                 example="I found help from a passing driver")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Request cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Request cancelled.")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Cannot cancel a completed request")
     * )
     */
    public function cancel(string $id): JsonResponse
    {
        $assistRequest = AssistanceRequest::find($id);

        if (!$assistRequest) {
            return $this->error('Assistance request not found.', 404);
        }

        if ($assistRequest->seeker_id !== Auth::id()) {
            return $this->error('Forbidden.', 403);
        }

        if (in_array($assistRequest->status, ['completed', 'cancelled'])) {
            return $this->error('Cannot cancel a request that is already ' . $assistRequest->status . '.', 422);
        }

        $assistRequest->update([
            'status'        => 'cancelled',
            'cancelled_at'  => now(),
            'cancel_reason' => request('cancel_reason'),
        ]);

        // Notify helper if one was assigned
        if ($assistRequest->helper_id) {
            $this->notificationService->notify(
                $assistRequest->helper,
                'cancelled',
                $assistRequest
            );
        }

        return $this->success([], 'Request cancelled.');
    }

    /**
     * @OA\Post(
     *     path="/api/assist/seeker/request/{id}/rate",
     *     summary="Rate the helper after a completed mission",
     *     tags={"Assist - Seeker"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440010")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"stars"},
     *             @OA\Property(property="stars", type="integer", minimum=1, maximum=5, example=5,
     *                 description="Rating from 1 (poor) to 5 (excellent)"),
     *             @OA\Property(property="comment", type="string", nullable=true,
     *                 example="Super fast and professional, saved my day!")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Rating submitted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Rating submitted successfully."),
     *             @OA\Property(property="data", ref="#/components/schemas/AssistRating")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Request not completed or already rated")
     * )
     */
    public function rate(RateHelperRequest $request, string $id): JsonResponse
    {
        $assistRequest = AssistanceRequest::find($id);

        if (!$assistRequest) {
            return $this->error('Assistance request not found.', 404);
        }

        if ($assistRequest->seeker_id !== Auth::id()) {
            return $this->error('Forbidden.', 403);
        }

        if ($assistRequest->status !== 'completed') {
            return $this->error('You can only rate a completed request.', 422);
        }

        if ($assistRequest->rating()->exists()) {
            return $this->error('This request has already been rated.', 422);
        }

        $rating = Rating::create([
            'request_id' => $assistRequest->id,
            'rater_id'   => Auth::id(),
            'rated_id'   => $assistRequest->helper_id,
            'stars'      => $request->stars,
            'comment'    => $request->comment,
        ]);

        // Recalculate helper average rating
        $helperProfile = $assistRequest->helper?->helperProfile;
        if ($helperProfile) {
            $avg = Rating::where('rated_id', $assistRequest->helper_id)->avg('stars');
            $helperProfile->update(['rating' => round($avg, 2)]);
        }

        // Notify helper
        $this->notificationService->notify($assistRequest->helper, 'rated', $assistRequest);

        return $this->success($rating, 'Rating submitted successfully.', 201);
    }
}
