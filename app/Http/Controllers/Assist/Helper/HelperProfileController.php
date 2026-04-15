<?php

namespace App\Http\Controllers\Assist\Helper;

use App\Http\Controllers\Assist\AssistBaseController;
use App\Http\Requests\Assist\UpdateHelperProfileRequest;
use App\Http\Requests\Assist\UpdateLocationRequest;
use App\Models\Assist\HelperProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Assist - Helper Profile",
 *     description="Helper profile management, GPS location, and availability toggle"
 * )
 */
class HelperProfileController extends AssistBaseController
{
    /**
     * @OA\Get(
     *     path="/api/assist/helper/profile",
     *     summary="Get the authenticated helper's profile",
     *     description="Returns the helper's profile including expertise types, rating, and service radius. Returns 404 if no profile has been created yet — call POST first.",
     *     tags={"Assist - Helper Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Helper profile",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",                type="integer", example=1),
     *                 @OA\Property(property="user_id",           type="integer", example=65),
     *                 @OA\Property(property="is_available",      type="boolean", example=true),
     *                 @OA\Property(property="is_verified",       type="boolean", example=true),
     *                 @OA\Property(property="rating",            type="number",  format="float", example=4.80),
     *                 @OA\Property(property="total_assists",     type="integer", example=23),
     *                 @OA\Property(property="service_radius_km", type="integer", example=20),
     *                 @OA\Property(property="level",             type="string",  enum={"standard","elite","vanguard"}, example="elite"),
     *                 @OA\Property(property="latitude",          type="number",  format="float", nullable=true, example=24.7250),
     *                 @OA\Property(property="longitude",         type="number",  format="float", nullable=true, example=46.6800),
     *                 @OA\Property(property="expertise_types", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",   type="integer", example=1),
     *                         @OA\Property(property="name", type="string",  example="tire_repair"),
     *                         @OA\Property(property="icon", type="string",  example="tire_repair")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Profile not found — create one first via POST /api/assist/helper/profile")
     * )
     */
    public function show(): JsonResponse
    {
        $profile = HelperProfile::with('expertiseTypes')
            ->where('user_id', Auth::id())
            ->first();

        if (!$profile) {
            return $this->error('Helper profile not found. Create one first.', 404);
        }

        return $this->success($profile);
    }

    /**
     * @OA\Post(
     *     path="/api/assist/helper/profile",
     *     summary="Create or update helper profile",
     *     description="Upserts the helper profile for the authenticated user. Can be called multiple times to update fields. Expertise IDs come from GET /api/assist/expertise-types.",
     *     tags={"Assist - Helper Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="service_radius_km", type="integer", minimum=1, maximum=100, example=20,
     *                 description="Max km radius to receive requests"),
     *             @OA\Property(property="level", type="string",
     *                 enum={"standard","elite","vanguard"}, example="standard",
     *                 description="standard = basic helper | elite = experienced | vanguard = top-rated expert"),
     *             @OA\Property(property="expertise_ids", type="array",
     *                 description="Expertise type IDs from GET /api/assist/expertise-types",
     *                 @OA\Items(type="integer"),
     *                 example={1, 3}
     *             ),
     *             @OA\Property(property="country_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="city_id",    type="integer", nullable=true, example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile saved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Profile updated successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",                type="integer", example=1),
     *                 @OA\Property(property="user_id",           type="integer", example=65),
     *                 @OA\Property(property="is_available",      type="boolean", example=false),
     *                 @OA\Property(property="is_verified",       type="boolean", example=false,
     *                     description="Verification is set by admin only"),
     *                 @OA\Property(property="service_radius_km", type="integer", example=20),
     *                 @OA\Property(property="level",             type="string",  example="standard"),
     *                 @OA\Property(property="expertise_types", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",   type="integer", example=1),
     *                         @OA\Property(property="name", type="string",  example="tire_repair"),
     *                         @OA\Property(property="icon", type="string",  example="tire_repair")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function upsert(UpdateHelperProfileRequest $request): JsonResponse
    {
        $profile = HelperProfile::firstOrCreate(
            ['user_id' => Auth::id()],
            ['is_available' => false, 'is_verified' => false]
        );

        $profile->update($request->only(['service_radius_km', 'level', 'country_id', 'city_id']));

        if ($request->has('expertise_ids')) {
            $profile->expertiseTypes()->sync($request->expertise_ids);
        }

        $profile->load('expertiseTypes');

        return $this->success($profile, 'Profile updated successfully.');
    }

    /**
     * @OA\Patch(
     *     path="/api/assist/helper/profile/location",
     *     summary="Update helper GPS location",
     *     description="Call this endpoint periodically from the mobile app to keep the helper's GPS position current. Required before appearing in the feed.",
     *     tags={"Assist - Helper Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"latitude","longitude"},
     *             @OA\Property(property="latitude",  type="number", format="float", example=24.7250,
     *                 description="Current latitude (-90 to 90)"),
     *             @OA\Property(property="longitude", type="number", format="float", example=46.6800,
     *                 description="Current longitude (-180 to 180)")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Location updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Location updated."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="latitude",  type="number", format="float", example=24.7250),
     *                 @OA\Property(property="longitude", type="number", format="float", example=46.6800)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Helper profile not found"),
     *     @OA\Response(response=422, description="Validation error — invalid coordinates")
     * )
     */
    public function updateLocation(UpdateLocationRequest $request): JsonResponse
    {
        $profile = HelperProfile::where('user_id', Auth::id())->first();

        if (!$profile) {
            return $this->error('Helper profile not found.', 404);
        }

        $profile->update([
            'latitude'  => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        return $this->success([
            'latitude'  => $request->latitude,
            'longitude' => $request->longitude,
        ], 'Location updated.');
    }

    /**
     * @OA\Patch(
     *     path="/api/assist/helper/profile/availability",
     *     summary="Toggle helper online/offline availability",
     *     description="Toggles between available and offline. Helper must be verified by an admin before they can go online. When offline, the helper won't appear in seeker searches.",
     *     tags={"Assist - Helper Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Availability toggled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success",  type="boolean", example=true),
     *             @OA\Property(property="message",  type="string",  example="You are now available."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="is_available", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Profile not verified by admin yet"),
     *     @OA\Response(response=404, description="Helper profile not found")
     * )
     */
    public function toggleAvailability(): JsonResponse
    {
        $profile = HelperProfile::where('user_id', Auth::id())->first();

        if (!$profile) {
            return $this->error('Helper profile not found.', 404);
        }

        if (!$profile->is_verified) {
            return $this->error('Your profile must be verified before going online.', 403);
        }

        $profile->update(['is_available' => !$profile->is_available]);

        $msg = $profile->is_available ? 'You are now available.' : 'You are now offline.';

        return $this->success(['is_available' => $profile->is_available], $msg);
    }
}
