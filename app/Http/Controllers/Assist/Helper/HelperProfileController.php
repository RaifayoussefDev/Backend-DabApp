<?php

namespace App\Http\Controllers\Assist\Helper;

use App\Http\Controllers\Assist\AssistBaseController;
use App\Http\Requests\Assist\UpdateHelperProfileRequest;
use App\Http\Requests\Assist\UpdateLocationRequest;
use App\Models\Assist\HelperProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Assist - Helper Profile",
 *     description="Helper profile management, GPS, and availability toggle"
 * )
 */
class HelperProfileController extends AssistBaseController
{
    /**
     * @OA\Get(
     *     path="/api/assist/helper/profile",
     *     summary="Get authenticated helper's profile",
     *     tags={"Assist - Helper Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Helper profile",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(property="data", ref="#/components/schemas/HelperProfile")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Profile not created yet")
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
     *     tags={"Assist - Helper Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="service_radius_km", type="integer", example=20,
     *                 description="Max km radius to accept requests (1-100)"),
     *             @OA\Property(property="level", type="string", enum={"standard","elite","vanguard"},
     *                 example="standard",
     *                 description="standard = basic helper, elite = experienced, vanguard = top-rated expert"),
     *             @OA\Property(property="expertise_ids", type="array",
     *                 description="List of expertise type IDs to assign",
     *                 @OA\Items(type="integer", example=1)
     *             ),
     *             @OA\Property(property="country_id", type="integer", nullable=true, example=1,
     *                 description="ID from /api/countries-list"),
     *             @OA\Property(property="city_id", type="integer", nullable=true, example=3,
     *                 description="ID from /api/cities?country_id=1")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile saved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profile updated successfully."),
     *             @OA\Property(property="data", ref="#/components/schemas/HelperProfile")
     *         )
     *     ),
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
     *     summary="Update helper GPS location in real time",
     *     tags={"Assist - Helper Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"latitude","longitude"},
     *             @OA\Property(property="latitude", type="number", format="float", example=24.7250,
     *                 description="Current latitude (-90 to 90)"),
     *             @OA\Property(property="longitude", type="number", format="float", example=46.6800,
     *                 description="Current longitude (-180 to 180)")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Location updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Location updated."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="latitude", type="number", example=24.7250),
     *                 @OA\Property(property="longitude", type="number", example=46.6800)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Profile not found")
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
     *     summary="Toggle helper availability on/off",
     *     tags={"Assist - Helper Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Availability toggled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="You are now available."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="is_available", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Profile not verified"),
     *     @OA\Response(response=404, description="Profile not found")
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
