<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\NotificationService;

class ListingFollowUpController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * The single listing (if any) the mobile app should show the follow-up
     * bottom sheet for right now. Lets the app show it in-session when the
     * seller opens the app before the day-7 push has fired, instead of
     * making them wait for the notification. At most one result — if a
     * seller has several listings due, the oldest one is shown first.
     */
    public function pendingFollowUp(Request $request)
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'message' => 'Unauthorized. User must be logged in.',
            ], 401);
        }

        // Same predicate as the recurring push (Listing::scopeDueForFollowUp) so the
        // in-app bottom sheet and the notification stay in lock-step.
        $listing = Listing::where('seller_id', $userId)
            ->dueForFollowUp()
            ->orderBy('published_at', 'asc')
            ->first();

        return response()->json([
            'data' => $listing,
        ]);
    }

    /**
     * Seller's answer to the one-time "did it sell?" follow-up bottom sheet.
     */
    public function respond(Request $request, $listingId)
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'message' => 'Unauthorized. User must be logged in.',
            ], 401);
        }

        $listing = Listing::find($listingId);

        if (!$listing) {
            return response()->json([
                'message' => 'Listing not found.',
            ], 404);
        }

        if ($listing->seller_id != $userId) {
            return response()->json([
                'message' => 'Only the seller can answer the follow-up for this listing.',
            ], 403);
        }

        if ($listing->status !== 'published') {
            return response()->json([
                'message' => 'This listing is no longer active.',
                'current_status' => $listing->status,
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'response' => 'required|in:sold,not_sold',
            'sale_channel' => 'required_if:response,sold|in:dabapp,other_platform,off_platform',
            'reason_not_sold' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->response === 'sold') {
            return $this->handleSold($listing, $request->sale_channel);
        }

        $listing->update([
            'follow_up_response' => 'not_sold',
            'follow_up_responded_at' => now(),
            'reason_not_sold' => $request->reason_not_sold,
            'follow_up_source' => 'seller',
            'follow_up_set_by' => $userId,
            // "not sold" is not terminal — re-arm the 30-day cycle from the answer.
            'follow_up_count' => $listing->follow_up_count + 1,
            'next_follow_up_at' => now()->addDays(30),
        ]);

        return response()->json([
            'message' => 'Thanks for letting us know. Your listing stays active.',
            'data' => $listing->fresh(),
        ]);
    }

    /**
     * "تراجع — ما زال متاحًا" on the success screen — lets the seller undo a
     * mistaken tap right after answering "sold" via the follow-up. Only
     * reverses a sale made through THIS flow (follow_up_response === 'sold'),
     * not a sale made via the normal mark-as-sold action.
     */
    public function undoSold(Request $request, $listingId)
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'message' => 'Unauthorized. User must be logged in.',
            ], 401);
        }

        $listing = Listing::find($listingId);

        if (!$listing) {
            return response()->json([
                'message' => 'Listing not found.',
            ], 404);
        }

        if ($listing->seller_id != $userId) {
            return response()->json([
                'message' => 'Only the seller can undo this listing.',
            ], 403);
        }

        if ($listing->status !== 'sold' || $listing->follow_up_response !== 'sold') {
            return response()->json([
                'message' => 'This listing was not sold via the follow-up and cannot be undone here.',
                'current_status' => $listing->status,
            ], 422);
        }

        $listing->update([
            'status' => 'published',
            'allow_submission' => true,
            'sale_channel' => null,
            'follow_up_response' => null,
            'follow_up_responded_at' => null,
            'follow_up_source' => null,
            'follow_up_set_by' => null,
            // Back on the market → resume the recurring check-in.
            'next_follow_up_at' => now()->addDays(30),
        ]);

        return response()->json([
            'message' => 'Undone — your listing is active again.',
            'data' => $listing->fresh(),
        ]);
    }

    /**
     * Temporary QA endpoint: re-send the "did it sell?" push for one of the
     * caller's own listings, on demand, as many times as needed. Whitelisted
     * to specific test accounts (currently the mobile dev, sayedalaa447@gmail.com,
     * user id 83) — remove TEST_USER_IDS / this endpoint once mobile QA is done.
     *
     * Unlike the real recurring flow, this NEVER touches follow_up_count /
     * follow_up_sent_at / next_follow_up_at — so it can't desync the schedule
     * and is safe to spam-test.
     */
    private const TEST_USER_IDS = [83];

    public function testFollowUpNotification(Request $request, $listingId)
    {
        $userId = Auth::id();

        if (!$userId || !in_array($userId, self::TEST_USER_IDS, true)) {
            return response()->json([
                'message' => 'Not authorized for test notifications.',
            ], 403);
        }

        $listing = Listing::where('id', $listingId)
            ->where('seller_id', $userId)
            ->with('seller')
            ->first();

        if (!$listing) {
            return response()->json([
                'message' => 'Listing not found, or it does not belong to you.',
            ], 404);
        }

        $result = $this->notificationService->notifyListingFollowUp(
            $listing->seller,
            $listing,
            $listing->follow_up_count + 1 // preview number only — not persisted
        );

        return response()->json([
            'message' => 'Test follow-up notification sent (schedule untouched — call again anytime).',
            'listing_id' => $listing->id,
            'listing_title' => $listing->title,
            'push_results' => $result['push_results'] ?? null,
        ]);
    }

    private function handleSold(Listing $listing, string $saleChannel)
    {
        DB::beginTransaction();

        try {
            $listing->update([
                'status' => 'sold',
                'allow_submission' => false,
                'sale_channel' => $saleChannel,
                'follow_up_response' => 'sold',
                'follow_up_responded_at' => now(),
                'follow_up_source' => 'seller',
                'follow_up_set_by' => $listing->seller_id,
                'next_follow_up_at' => null, // sold → recurring reminders stop
            ]);

            $rejectedSoomsCount = Submission::where('listing_id', $listing->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'rejection_reason' => 'Listing marked as sold by seller',
                ]);

            $pendingSoomUsers = Submission::where('listing_id', $listing->id)
                ->where('status', 'rejected')
                ->where('rejection_reason', 'Listing marked as sold by seller')
                ->with('user')
                ->get();

            foreach ($pendingSoomUsers as $submission) {
                try {
                    $this->notificationService->notifyListingSold($submission->user, $listing);
                } catch (\Exception $e) {
                    \Log::error('Failed to send listing sold notification: ' . $e->getMessage());
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Congratulations on the sale!',
                'data' => $listing->fresh(),
                'rejected_sooms_count' => $rejectedSoomsCount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Failed to mark listing as sold',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
