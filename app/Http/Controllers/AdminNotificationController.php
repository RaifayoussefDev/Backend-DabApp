<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\MassNotificationJob;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin Notifications",
 *     description="API Endpoints for Admin Mass Notifications"
 * )
 */
class AdminNotificationController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/admin/notifications/mass-send",
     *     summary="Send mass notifications to filtered users",
     *     tags={"Admin Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"content"},
     *             @OA\Property(
     *                 property="filters",
     *                 type="object",
     *                 @OA\Property(property="city_id", type="integer"),
     *                 @OA\Property(property="category_id", type="integer"),
     *                 @OA\Property(property="date_from", type="string", format="date"),
     *                 @OA\Property(property="date_to", type="string", format="date")
     *             ),
     *             @OA\Property(
     *                 property="content",
     *                 type="object",
     *                 required={"title_en", "body_en"},
     *                 @OA\Property(property="title_en", type="string"),
     *                 @OA\Property(property="title_ar", type="string"),
     *                 @OA\Property(property="body_en", type="string"),
     *                 @OA\Property(property="body_ar", type="string"),
     *                 @OA\Property(property="type", type="string", enum={"promo", "news", "info"}, default="info")
     *             ),
     *             @OA\Property(
     *                 property="channels",
     *                 type="array",
     *                 @OA\Items(type="string", enum={"push", "email"})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification job dispatched",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="filters", type="object")
     *         )
     *     )
     * )
     */
    public function sendMassNotification(Request $request)
    {
        // 1. Validation
        $validator = Validator::make($request->all(), [
            'content.title_en' => 'required|string|max:255',
            'content.body_en' => 'required|string',
            'content.title_ar' => 'nullable|string|max:255',
            'content.body_ar' => 'nullable|string',
            'content.type' => 'nullable|string|in:promo,news,info',
            'filters.city_id' => 'nullable|exists:cities,id',
            'filters.category_id' => 'nullable|exists:categories,id',
            'filters.date_from' => 'nullable|date',
            'filters.date_to' => 'nullable|date',
            'channels' => 'nullable|array',
            'channels.*' => 'in:push,email'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $filters = $request->input('filters', []);
        $content = $request->input('content');
        $channels = $request->input('channels', ['push']); // Default to push if not specified

        // 2. Dispatch Job
        MassNotificationJob::dispatch($filters, $content, $channels);

        return response()->json([
            'message' => 'Mass notification job dispatched successfully',
            'filters' => $filters,
            'recipients_estimate' => 'Processing in background'
        ]);
    }
}
