<?php

namespace App\Http\Controllers\Assist\Seeker;

use App\Http\Controllers\Assist\AssistBaseController;
use App\Models\Assist\AssistanceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Assist - Seeker Tracking",
 *     description="Live tracking of helper location and mission timeline"
 * )
 */
class SeekerTrackingController extends AssistBaseController
{
    /**
     * @OA\Get(
     *     path="/api/assist/seeker/request/{id}/track",
     *     summary="Get live helper location and mission timeline",
     *     description="Poll this endpoint to track the helper's real-time GPS position and monitor mission progress. Returns null for helper if no helper has accepted the request yet.",
     *     tags={"Assist - Seeker Tracking"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="Assistance request ID",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Live tracking data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="status", type="string",
     *                     enum={"pending","accepted","en_route","arrived","completed","cancelled"},
     *                     example="en_route"
     *                 ),
     *                 @OA\Property(property="status_label", type="object",
     *                     @OA\Property(property="en", type="string", example="On the Way"),
     *                     @OA\Property(property="ar", type="string", example="في الطريق")
     *                 ),
     *                 @OA\Property(property="helper", type="object", nullable=true,
     *                     description="null if no helper assigned yet",
     *                     @OA\Property(property="id",        type="integer", example=2),
     *                     @OA\Property(property="name",      type="string",  example="Ahmed Al-Rashid"),
     *                     @OA\Property(property="phone",     type="string",  example="+966501234567"),
     *                     @OA\Property(property="rating",    type="number",  format="float", nullable=true, example=4.80),
     *                     @OA\Property(property="latitude",  type="number",  format="float", nullable=true, example=24.7200,
     *                         description="Helper's current GPS latitude — updates as helper moves"),
     *                     @OA\Property(property="longitude", type="number",  format="float", nullable=true, example=46.6800,
     *                         description="Helper's current GPS longitude — updates as helper moves")
     *                 ),
     *                 @OA\Property(property="timeline", type="object",
     *                     @OA\Property(property="accepted_at",  type="string", format="date-time", nullable=true, example="2026-04-15T10:05:00.000000Z"),
     *                     @OA\Property(property="arrived_at",   type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true, example=null)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="This request does not belong to you"),
     *     @OA\Response(response=404, description="Request not found")
     * )
     */
    public function track(string $id): JsonResponse
    {
        $assistRequest = AssistanceRequest::with([
            'helper:id,first_name,last_name,phone,profile_picture',
            'helper.helperProfile:user_id,latitude,longitude,rating,level',
        ])->find($id);

        if (!$assistRequest) {
            return $this->error('Assistance request not found.', 404);
        }

        if ($assistRequest->seeker_id !== Auth::id()) {
            return $this->error('Forbidden.', 403);
        }

        $helperData = null;
        if ($assistRequest->helper) {
            $profile    = $assistRequest->helper->helperProfile;
            $helperData = [
                'id'        => $assistRequest->helper->id,
                'name'      => trim($assistRequest->helper->first_name . ' ' . $assistRequest->helper->last_name),
                'phone'     => $assistRequest->helper->phone,
                'rating'    => $profile?->rating,
                'latitude'  => $profile?->latitude,
                'longitude' => $profile?->longitude,
            ];
        }

        return $this->success([
            'status'       => $assistRequest->status,
            'status_label' => $assistRequest->status_label,
            'helper'       => $helperData,
            'timeline'     => [
                'accepted_at'  => $assistRequest->accepted_at,
                'arrived_at'   => $assistRequest->arrived_at,
                'completed_at' => $assistRequest->completed_at,
            ],
        ]);
    }
}
