<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Models\TrainerSchedule;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Trainer Schedule",
 *     description="Manage weekly working hours for trainers"
 * )
 */
class TrainerScheduleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/trainer/schedule",
     *     summary="Get my weekly schedule",
     *     description="Returns the authenticated trainer's weekly schedule. Days not listed (or is_available=false) are considered closed.",
     *     operationId="getTrainerSchedule",
     *     tags={"Trainer Schedule"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Schedule retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id",           type="integer", example=1),
     *                     @OA\Property(property="day_of_week",  type="integer", example=6,
     *                         description="0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday"),
     *                     @OA\Property(property="day_name",     type="string",  example="Saturday"),
     *                     @OA\Property(property="day_name_ar",  type="string",  example="السبت"),
     *                     @OA\Property(property="start_time",   type="string",  example="08:00"),
     *                     @OA\Property(property="end_time",     type="string",  example="18:00"),
     *                     @OA\Property(property="is_available", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="No trainer profile found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->first();

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $trainer->schedules,
            'message' => 'Schedule retrieved successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/trainer/schedule",
     *     summary="Set weekly schedule",
     *     description="Upserts the trainer's weekly working hours. Send all the days you want to configure. Omitted days are left unchanged. Set is_available=false to close a day without removing the record.",
     *     operationId="setTrainerSchedule",
     *     tags={"Trainer Schedule"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"schedule"},
     *             @OA\Property(property="schedule", type="array",
     *                 @OA\Items(type="object",
     *                     required={"day_of_week","start_time","end_time"},
     *                     @OA\Property(property="day_of_week",  type="integer", minimum=0, maximum=6, example=6,
     *                         description="0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday"),
     *                     @OA\Property(property="start_time",   type="string",  example="08:00", description="24h format HH:MM"),
     *                     @OA\Property(property="end_time",     type="string",  example="18:00", description="24h format HH:MM — must be after start_time"),
     *                     @OA\Property(property="is_available", type="boolean", example=true)
     *                 ),
     *                 example={
     *                     {"day_of_week": 5, "start_time": "08:00", "end_time": "12:00", "is_available": true},
     *                     {"day_of_week": 6, "start_time": "08:00", "end_time": "18:00", "is_available": true},
     *                     {"day_of_week": 0, "start_time": "10:00", "end_time": "14:00", "is_available": false}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Schedule saved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success",      type="boolean", example=true),
     *             @OA\Property(property="message",      type="string",  example="Schedule updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="updated_days", type="integer", example=3),
     *                 @OA\Property(property="schedule", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="day_of_week",  type="integer"),
     *                         @OA\Property(property="day_name",     type="string"),
     *                         @OA\Property(property="start_time",   type="string"),
     *                         @OA\Property(property="end_time",     type="string"),
     *                         @OA\Property(property="is_available", type="boolean")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="No trainer profile found"),
     *     @OA\Response(response=422, description="Validation error — invalid day, time format, or end_time before start_time")
     * )
     */
    public function upsert(Request $request)
    {
        $user    = JWTAuth::parseToken()->authenticate();
        $trainer = Trainer::where('user_id', $user->id)->first();

        if (!$trainer) {
            return response()->json(['success' => false, 'message' => 'No trainer profile found'], 404);
        }

        $validated = $request->validate([
            'schedule'                => 'required|array|min:1',
            'schedule.*.day_of_week'  => 'required|integer|between:0,6',
            'schedule.*.start_time'   => 'required|date_format:H:i',
            'schedule.*.end_time'     => 'required|date_format:H:i|after:schedule.*.start_time',
            'schedule.*.is_available' => 'nullable|boolean',
        ]);

        foreach ($validated['schedule'] as $day) {
            TrainerSchedule::updateOrCreate(
                ['trainer_id' => $trainer->id, 'day_of_week' => $day['day_of_week']],
                [
                    'start_time'   => $day['start_time'],
                    'end_time'     => $day['end_time'],
                    'is_available' => $day['is_available'] ?? true,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'updated_days' => count($validated['schedule']),
                'schedule'     => $trainer->schedules()->orderBy('day_of_week')->get(),
            ],
            'message' => 'Schedule updated successfully',
        ]);
    }
}
