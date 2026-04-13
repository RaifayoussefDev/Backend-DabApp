<?php

namespace App\Http\Controllers\Assist\Seeker;

use App\Http\Controllers\Assist\AssistBaseController;
use App\Models\Assist\AssistanceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Assist - Seeker Tracking",
 *     description="Live tracking of helper location and mission status"
 * )
 */
class SeekerTrackingController extends AssistBaseController
{
    /**
     * @OA\Get(
     *     path="/api/assist/seeker/request/{id}/track",
     *     summary="Get live helper location and mission timeline",
     *     tags={"Assist - Seeker Tracking"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="Assistance request UUID",
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440010")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Live tracking data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="status", type="string", example="en_route"),
     *                 @OA\Property(property="status_label", type="object",
     *                     @OA\Property(property="en", type="string", example="On the Way"),
     *                     @OA\Property(property="ar", type="string", example="في الطريق")
     *                 ),
     *                 @OA\Property(property="helper", type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Ahmed Al-Rashid"),
     *                     @OA\Property(property="phone", type="string", example="+966501234567"),
     *                     @OA\Property(property="rating", type="number", example=4.8),
     *                     @OA\Property(property="latitude", type="number", example=24.7200),
     *                     @OA\Property(property="longitude", type="number", example=46.6800)
     *                 ),
     *                 @OA\Property(property="timeline", type="object",
     *                     @OA\Property(property="accepted_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="arrived_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
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
