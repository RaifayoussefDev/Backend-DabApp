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
     *     description="Returns pending requests within the helper's service radius that match at least one of their registered expertise types. Helper must be verified and available, and must have a GPS location set.",
     *     tags={"Assist - Helper Feed"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of nearby pending requests sorted by distance",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Success"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id",             type="integer", example=12),
     *                     @OA\Property(property="status",         type="string",  example="pending"),
     *                     @OA\Property(property="description",    type="string",  example="My rear tire is completely flat."),
     *                     @OA\Property(property="location_label", type="string",  example="King Fahd Road, Riyadh – near Exit 7"),
     *                     @OA\Property(property="latitude",       type="number",  format="float", example=24.714),
     *                     @OA\Property(property="longitude",      type="number",  format="float", example=46.675),
     *                     @OA\Property(property="distance_km",    type="number",  format="float", example=2.34,
     *                         description="Distance in km between helper and seeker"),
     *                     @OA\Property(property="created_at",     type="string",  format="date-time"),
     *                     @OA\Property(property="expertise_types", type="array",
     *                         @OA\Items(type="object",
     *                             @OA\Property(property="id",   type="integer", example=1),
     *                             @OA\Property(property="name", type="string",  example="tire_repair"),
     *                             @OA\Property(property="icon", type="string",  example="tire_repair")
     *                         )
     *                     ),
     *                     @OA\Property(property="photos", type="array",
     *                         @OA\Items(type="object",
     *                             @OA\Property(property="id",   type="integer", example=1),
     *                             @OA\Property(property="path", type="string",  example="https://cdn.example.com/uploads/photo1.jpg")
     *                         )
     *                     ),
     *                     @OA\Property(property="seeker", type="object",
     *                         @OA\Property(property="id",         type="integer", example=65),
     *                         @OA\Property(property="first_name", type="string",  example="Raifa"),
     *                         @OA\Property(property="last_name",  type="string",  example="Youssef")
     *                     ),
     *                     @OA\Property(property="motorcycle", type="object", nullable=true,
     *                         @OA\Property(property="id",    type="integer", example=15),
     *                         @OA\Property(property="color", type="string",  example="Red"),
     *                         @OA\Property(property="brand", type="object",
     *                             @OA\Property(property="name", type="string", example="BMW")
     *                         ),
     *                         @OA\Property(property="model", type="object",
     *                             @OA\Property(property="name", type="string", example="F 900 R")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Profile not verified or not set to available"),
     *     @OA\Response(response=404, description="Helper profile not found — create one first"),
     *     @OA\Response(response=422, description="GPS location not set — call PATCH /api/assist/helper/profile/location first")
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

        if (!$profile->is_verified) {
            return $this->error('Your profile is pending admin approval. You cannot view the feed yet.', 403);
        }

        if (!$profile->is_available) {
            return $this->error('You are currently offline. Toggle your availability to go online.', 403);
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
            ->where('seeker_id', '!=', Auth::id())
            ->whereHas('expertiseTypes', fn($q) => $q->whereIn('expertise_types.id', $expertiseIds))
            ->having('distance_km', '<=', $profile->service_radius_km)
            ->orderBy('distance_km')
            ->with(['expertiseTypes', 'seeker:id,first_name,last_name', 'photos', 'motorcycle.brand', 'motorcycle.model', 'motorcycle.year'])
            ->get()
            ->each(function ($r) {
                $r->seeker?->setVisible(['id', 'first_name', 'last_name']);
                if ($m = $r->motorcycle) {
                    $r->setRelation('motorcycle', collect([
                        'id'    => $m->id,
                        'brand' => $m->brand?->name,
                        'model' => $m->model?->name ?? '#' . $m->model_id,
                        'year'  => $m->year?->year ?? $m->year_id,
                    ]));
                }
            });

        return $this->success($requests);
    }

    /**
     * @OA\Get(
     *     path="/api/assist/helper/feed/{id}",
     *     summary="Get details of a single pending request",
     *     description="Returns full details of a pending assistance request before the helper decides to accept it. Returns 404 if the request is no longer pending.",
     *     tags={"Assist - Helper Feed"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="Assistance request ID",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Request details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",             type="integer", example=12),
     *                 @OA\Property(property="status",         type="string",  example="pending"),
     *                 @OA\Property(property="description",    type="string",  example="My rear tire is completely flat."),
     *                 @OA\Property(property="location_label", type="string",  example="Bd Mohammed V, Casablanca"),
     *                 @OA\Property(property="latitude",       type="number",  format="float", example=33.5731),
     *                 @OA\Property(property="longitude",      type="number",  format="float", example=-7.5898),
     *                 @OA\Property(property="distance_km",    type="number",  format="float", example=2.34),
     *                 @OA\Property(property="created_at",     type="string",  format="date-time"),
     *                 @OA\Property(property="expertise_types", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",   type="integer", example=1),
     *                         @OA\Property(property="name", type="string",  example="tire_repair"),
     *                         @OA\Property(property="icon", type="string",  example="tire_repair")
     *                     )
     *                 ),
     *                 @OA\Property(property="photos", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",   type="integer", example=1),
     *                         @OA\Property(property="path", type="string",  example="https://cdn.example.com/photo1.jpg")
     *                     )
     *                 ),
     *                 @OA\Property(property="seeker", type="object",
     *                     @OA\Property(property="id",         type="integer", example=65),
     *                     @OA\Property(property="first_name", type="string",  example="Raifa"),
     *                     @OA\Property(property="last_name",  type="string",  example="Youssef")
     *                 ),
     *                 @OA\Property(property="motorcycle", type="object", nullable=true,
     *                     @OA\Property(property="id",    type="integer", example=15),
     *                     @OA\Property(property="brand", type="string",  example="BMW"),
     *                     @OA\Property(property="model", type="string",  example="F 900 R"),
     *                     @OA\Property(property="year",  type="integer", example=2022)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Profile not verified or offline"),
     *     @OA\Response(response=404, description="Request not found or no longer available")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $profile = HelperProfile::with('expertiseTypes')
            ->where('user_id', Auth::id())
            ->first();

        if (!$profile) {
            return $this->error('Helper profile not found.', 404);
        }

        if (!$profile->is_verified) {
            return $this->error('Your profile is pending admin approval.', 403);
        }

        if (!$profile->is_available) {
            return $this->error('You are currently offline.', 403);
        }

        $request = AssistanceRequest::where('id', $id)
            ->where('status', 'pending')
            ->where('seeker_id', '!=', Auth::id())
            ->with(['expertiseTypes', 'seeker:id,first_name,last_name', 'photos', 'motorcycle.brand', 'motorcycle.model', 'motorcycle.year'])
            ->first();

        if (!$request) {
            return $this->error('Request not found or no longer available.', 404);
        }

        $request->seeker?->setVisible(['id', 'first_name', 'last_name']);

        if ($m = $request->motorcycle) {
            $request->setRelation('motorcycle', collect([
                'id'    => $m->id,
                'brand' => $m->brand?->name,
                'model' => $m->model?->name ?? '#' . $m->model_id,
                'year'  => $m->year?->year ?? $m->year_id,
            ]));
        }

        if ($profile->latitude && $profile->longitude) {
            $lat = (float) $profile->latitude;
            $lng = (float) $profile->longitude;
            $haversine = HelperProfile::haversineExpression();

            $distance = AssistanceRequest::selectRaw("({$haversine}) AS distance_km", [$lat, $lng, $lat])
                ->where('id', $id)
                ->value('distance_km');

            $request->distance_km = round((float) $distance, 2);
        }

        return $this->success($request);
    }

    /**
     * @OA\Post(
     *     path="/api/assist/helper/feed/{id}/accept",
     *     summary="Accept a pending assistance request",
     *     description="Atomically claims the request for the authenticated helper. Returns 409 if another helper already accepted it. Helper must be verified and available.",
     *     tags={"Assist - Helper Feed"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="Assistance request ID",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Request accepted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Request accepted. Head to the rider's location."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",             type="integer", example=12),
     *                 @OA\Property(property="status",         type="string",  example="accepted"),
     *                 @OA\Property(property="accepted_at",    type="string",  format="date-time", example="2026-04-15T10:05:00.000000Z"),
     *                 @OA\Property(property="location_label", type="string",  example="King Fahd Road, Riyadh – near Exit 7"),
     *                 @OA\Property(property="latitude",       type="number",  format="float", example=24.714),
     *                 @OA\Property(property="longitude",      type="number",  format="float", example=46.675),
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
     *     @OA\Response(response=403, description="Not verified or not available"),
     *     @OA\Response(response=404, description="Request not found"),
     *     @OA\Response(response=409, description="Request already taken by another helper")
     * )
     */
    public function accept(string $id): JsonResponse
    {
        $profile = HelperProfile::where('user_id', Auth::id())->first();

        if (!$profile || !$profile->is_verified || !$profile->is_available) {
            return $this->error('You must be verified and available to accept requests.', 403);
        }

        $alreadyActive = AssistanceRequest::where('helper_id', Auth::id())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->exists();

        if ($alreadyActive) {
            return $this->error('You already have an active mission. Complete or cancel it before accepting a new one.', 409);
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
