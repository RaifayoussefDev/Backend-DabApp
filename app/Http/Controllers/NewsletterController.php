<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Models\NewsletterPreference;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Newsletter",
 *     description="API Endpoints for Newsletter Subscription"
 * )
 */
class NewsletterController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/newsletter/subscribe",
     *     summary="Subscribe to newsletter",
     *     tags={"Newsletter"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Subscribed successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $subscriber = NewsletterSubscriber::where('email', $request->email)->first();

        if ($subscriber && $subscriber->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Email already subscribed',
            ], 422);
        }

        if ($subscriber) {
            $subscriber->update([
                'is_active' => true,
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ]);
        } else {
            $subscriber = NewsletterSubscriber::create([
                'email' => $request->email,
                'user_id' => auth()->id() ?? null,
                'subscribed_at' => now(),
                'verification_token' => Str::random(60),
            ]);

            // Create default preferences
            NewsletterPreference::create([
                'subscriber_id' => $subscriber->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully subscribed to newsletter',
            'data' => $subscriber,
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/newsletter/unsubscribe",
     *     summary="Unsubscribe from newsletter",
     *     tags={"Newsletter"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Unsubscribed successfully"),
     *     @OA\Response(response=404, description="Email not found")
     * )
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $subscriber = NewsletterSubscriber::where('email', $request->email)->first();

        if (!$subscriber) {
            return response()->json([
                'success' => false,
                'message' => 'Email not found',
            ], 404);
        }

        $subscriber->update([
            'is_active' => false,
            'unsubscribed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Successfully unsubscribed from newsletter',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/newsletter/preferences",
     *     summary="Get newsletter preferences",
     *     tags={"Newsletter"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Subscriber not found")
     * )
     */
    public function getPreferences(): JsonResponse
    {
        $user = auth()->user();
        $subscriber = NewsletterSubscriber::where('user_id', $user->id)->first();

        if (!$subscriber) {
            return response()->json([
                'success' => false,
                'message' => 'No subscription found',
            ], 404);
        }

        $preferences = $subscriber->preferences;

        return response()->json([
            'success' => true,
            'data' => $preferences,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/newsletter/preferences",
     *     summary="Update newsletter preferences",
     *     tags={"Newsletter"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="receive_new_articles", type="boolean"),
     *             @OA\Property(property="receive_new_listings", type="boolean"),
     *             @OA\Property(property="receive_promotions", type="boolean"),
     *             @OA\Property(property="receive_weekly_digest", type="boolean"),
     *             @OA\Property(property="frequency", type="string", enum={"immediate","daily","weekly"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Preferences updated successfully"),
     *     @OA\Response(response=404, description="Subscriber not found")
     * )
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = auth()->user();
        $subscriber = NewsletterSubscriber::where('user_id', $user->id)->first();

        if (!$subscriber) {
            return response()->json([
                'success' => false,
                'message' => 'No subscription found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'receive_new_articles' => 'sometimes|boolean',
            'receive_new_listings' => 'sometimes|boolean',
            'receive_promotions' => 'sometimes|boolean',
            'receive_weekly_digest' => 'sometimes|boolean',
            'frequency' => 'sometimes|in:immediate,daily,weekly',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $preferences = $subscriber->preferences;

        if (!$preferences) {
            $preferences = NewsletterPreference::create([
                'subscriber_id' => $subscriber->id,
                ...$validator->validated()
            ]);
        } else {
            $preferences->update($validator->validated());
        }

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'data' => $preferences,
        ]);
    }
}
