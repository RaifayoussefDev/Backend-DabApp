<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ProviderWorkingHour;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Provider Working Hours",
 *     description="API endpoints for managing service provider working hours"
 * )
 */
class ProviderWorkingHoursController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/my-working-hours",
     *     summary="Get my working hours",
     *     description="Retrieve the working hours for the authenticated service provider",
     *     operationId="getMyWorkingHours",
     *     tags={"Provider Working Hours"},
     *     security={{"bearer":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="day_of_week", type="integer", example=1, description="0=Sunday, 1=Monday..."),
     *                     @OA\Property(property="is_open", type="boolean", example=true),
     *                     @OA\Property(property="open_time", type="string", format="time", example="09:00:00"),
     *                     @OA\Property(property="close_time", type="string", format="time", example="17:00:00")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user->serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a service provider'
            ], 403);
        }

        $hours = ProviderWorkingHour::where('provider_id', $user->serviceProvider->id)
            ->orderBy('day_of_week')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $hours
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/my-working-hours",
     *     summary="Update working hours",
     *     description="Update the working hours for the authenticated service provider",
     *     operationId="updateWorkingHours",
     *     tags={"Provider Working Hours"},
     *     security={{"bearer":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"schedule_type", "hours_type"},
     *             @OA\Property(property="schedule_type", type="string", enum={"all_week", "specific_days"}, example="specific_days"),
     *             @OA\Property(property="specific_days", type="array", @OA\Items(type="integer"), description="Required if schedule_type is specific_days. 0=Sunday, 6=Saturday"),
     *             @OA\Property(property="hours_type", type="string", enum={"24_hours", "specific_time"}, example="specific_time"),
     *             @OA\Property(property="open_time", type="string", format="time", example="09:00"),
     *             @OA\Property(property="close_time", type="string", format="time", example="17:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Working hours updated successfully"
     *     )
     * )
     */
    public function update(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user->serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a service provider'
            ], 403);
        }

        $validated = $request->validate([
            'schedule_type' => 'required|in:all_week,specific_days',
            'specific_days' => 'required_if:schedule_type,specific_days|array',
            'specific_days.*' => 'integer|min:0|max:6',
            'hours_type' => 'required|in:24_hours,specific_time',
            'open_time' => 'required_if:hours_type,specific_time|date_format:H:i',
            'close_time' => 'required_if:hours_type,specific_time|date_format:H:i|after:open_time',
        ]);

        $providerId = $user->serviceProvider->id;
        $workingDays = [];

        // Determine which days are working days
        if ($validated['schedule_type'] === 'all_week') {
            $workingDays = [0, 1, 2, 3, 4, 5, 6];
        } else {
            $workingDays = $validated['specific_days'];
        }

        // Determine hours
        $openTime = null;
        $closeTime = null;
        if ($validated['hours_type'] === 'specific_time') {
            $openTime = $validated['open_time'];
            $closeTime = $validated['close_time'];
        } else {
            // 24 hours: logic depends on how frontend handles it, 
            // usually open 00:00 close 23:59 or null/null with is_open=true
            $openTime = '00:00';
            $closeTime = '23:59';
        }

        DB::beginTransaction();
        try {
            // Remove existing hours
            ProviderWorkingHour::where('provider_id', $providerId)->delete();

            // Insert new hours
            foreach ($workingDays as $day) {
                ProviderWorkingHour::create([
                    'provider_id' => $providerId,
                    'day_of_week' => $day,
                    'is_open' => true,
                    'open_time' => $openTime,
                    'close_time' => $closeTime
                ]);
            }

            // Note: Days not in the list are implicitly closed (not present in DB)
            // Or we could insert them as is_open=false if the business logic requires it.
            // For now, only storing open days is efficient, but let's check if the table expects all days.
            // Usually it's better to verify if the frontend expects all 7 days with status.
            // Let's stick to "delete all, insert active".

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Working hours updated successfully',
                'data' => ProviderWorkingHour::where('provider_id', $providerId)->get()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update working hours',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
