<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\NewsletterSend;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Newsletter Campaigns",
 *     description="API Endpoints for managing Newsletter Campaigns (Admin only)"
 * )
 */
class NewsletterCampaignController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/newsletter/campaigns",
     *     summary="Get all newsletter campaigns",
     *     tags={"Newsletter Campaigns"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by campaign status",
     *         @OA\Schema(type="string", enum={"draft","scheduled","sending","sent"})
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=403, description="Forbidden - Admin only")
     * )
     */
    public function index(Request $request): JsonResponse
    {


        $query = NewsletterCampaign::with('creator');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $campaigns = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $campaigns,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/newsletter/campaigns",
     *     summary="Create a new newsletter campaign",
     *     tags={"Newsletter Campaigns"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","subject","content"},
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="subject", type="string"),
     *             @OA\Property(property="content", type="string"),
     *             @OA\Property(property="template_id", type="integer"),
     *             @OA\Property(property="scheduled_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Campaign created successfully"),
     *     @OA\Response(response=403, description="Forbidden - Admin only"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {


        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'template_id' => 'nullable|integer',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['created_by'] = Auth::id();
        $data['status'] = $request->has('scheduled_at') ? 'scheduled' : 'draft';

        // Count active subscribers
        $data['recipients_count'] = NewsletterSubscriber::where('is_active', true)->count();

        $campaign = NewsletterCampaign::create($data);
        $campaign->load('creator');

        return response()->json([
            'success' => true,
            'message' => 'Newsletter campaign created successfully',
            'data' => $campaign,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/newsletter/campaigns/{id}",
     *     summary="Get a specific campaign",
     *     tags={"Newsletter Campaigns"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=403, description="Forbidden - Admin only"),
     *     @OA\Response(response=404, description="Campaign not found")
     * )
     */
    public function show(int $id): JsonResponse
    {


        $campaign = NewsletterCampaign::with('creator')->find($id);

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found',
            ], 404);
        }

        // Calculate engagement rate
        $engagementRate = $campaign->recipients_count > 0
            ? round(($campaign->opened_count / $campaign->recipients_count) * 100, 2)
            : 0;

        $clickRate = $campaign->opened_count > 0
            ? round(($campaign->clicked_count / $campaign->opened_count) * 100, 2)
            : 0;

        $campaignData = $campaign->toArray();
        $campaignData['engagement_rate'] = $engagementRate;
        $campaignData['click_rate'] = $clickRate;

        return response()->json([
            'success' => true,
            'data' => $campaignData,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/newsletter/campaigns/{id}",
     *     summary="Update a campaign",
     *     tags={"Newsletter Campaigns"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Campaign updated successfully"),
     *     @OA\Response(response=403, description="Forbidden - Admin only"),
     *     @OA\Response(response=404, description="Campaign not found"),
     *     @OA\Response(response=422, description="Cannot edit sent campaign")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {


        $campaign = NewsletterCampaign::find($id);

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found',
            ], 404);
        }

        // Cannot edit sent or sending campaigns
        if (in_array($campaign->status, ['sent', 'sending'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit a campaign that has been sent or is being sent',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'subject' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'template_id' => 'nullable|integer',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Update status if scheduling
        if ($request->has('scheduled_at')) {
            $data['status'] = 'scheduled';
        }

        $campaign->update($data);
        $campaign->load('creator');

        return response()->json([
            'success' => true,
            'message' => 'Campaign updated successfully',
            'data' => $campaign,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/newsletter/campaigns/{id}",
     *     summary="Delete a campaign",
     *     tags={"Newsletter Campaigns"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Campaign deleted successfully"),
     *     @OA\Response(response=403, description="Forbidden - Admin only"),
     *     @OA\Response(response=404, description="Campaign not found"),
     *     @OA\Response(response=422, description="Cannot delete sent campaign")
     * )
     */
    public function destroy(int $id): JsonResponse
    {


        $campaign = NewsletterCampaign::find($id);

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found',
            ], 404);
        }

        // Cannot delete sent campaigns
        if ($campaign->status === 'sent') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a sent campaign',
            ], 422);
        }

        $campaign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Campaign deleted successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/newsletter/campaigns/{id}/send",
     *     summary="Send a campaign immediately",
     *     tags={"Newsletter Campaigns"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Campaign queued for sending"),
     *     @OA\Response(response=403, description="Forbidden - Admin only"),
     *     @OA\Response(response=404, description="Campaign not found"),
     *     @OA\Response(response=422, description="Campaign cannot be sent")
     * )
     */
    public function send(int $id): JsonResponse
    {


        $campaign = NewsletterCampaign::find($id);

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found',
            ], 404);
        }

        // Check if campaign can be sent
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign has already been sent or is being sent',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Update campaign status
            $campaign->update([
                'status' => 'sending',
                'sent_at' => now(),
            ]);

            // Get active subscribers
            $subscribers = NewsletterSubscriber::where('is_active', true)->get();

            // Create send records for each subscriber
            foreach ($subscribers as $subscriber) {
                NewsletterSend::create([
                    'campaign_id' => $campaign->id,
                    'subscriber_id' => $subscriber->id,
                    'sent_at' => now(),
                ]);
            }

            // Update campaign status to sent
            $campaign->update(['status' => 'sent']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Campaign sent successfully to ' . $subscribers->count() . ' subscribers',
                'data' => $campaign,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to send campaign: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/newsletter/campaigns/{id}/stats",
     *     summary="Get campaign statistics",
     *     tags={"Newsletter Campaigns"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=403, description="Forbidden - Admin only"),
     *     @OA\Response(response=404, description="Campaign not found")
     * )
     */
    public function stats(int $id): JsonResponse
    {


        $campaign = NewsletterCampaign::find($id);

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found',
            ], 404);
        }

        $stats = [
            'total_sent' => $campaign->recipients_count,
            'opened' => $campaign->opened_count,
            'clicked' => $campaign->clicked_count,
            'bounced' => NewsletterSend::where('campaign_id', $id)
                ->where('bounced', true)
                ->count(),
            'unsubscribed' => NewsletterSend::where('campaign_id', $id)
                ->where('unsubscribed', true)
                ->count(),
            'open_rate' => $campaign->recipients_count > 0
                ? round(($campaign->opened_count / $campaign->recipients_count) * 100, 2)
                : 0,
            'click_rate' => $campaign->opened_count > 0
                ? round(($campaign->clicked_count / $campaign->opened_count) * 100, 2)
                : 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/newsletter/campaigns/{id}/duplicate",
     *     summary="Duplicate a campaign",
     *     tags={"Newsletter Campaigns"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=201, description="Campaign duplicated successfully"),
     *     @OA\Response(response=403, description="Forbidden - Admin only"),
     *     @OA\Response(response=404, description="Campaign not found")
     * )
     */
    public function duplicate(int $id): JsonResponse
    {
        

        $campaign = NewsletterCampaign::find($id);

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found',
            ], 404);
        }

        $newCampaign = $campaign->replicate();
        $newCampaign->title = $campaign->title . ' (Copy)';
        $newCampaign->status = 'draft';
        $newCampaign->scheduled_at = null;
        $newCampaign->sent_at = null;
        $newCampaign->opened_count = 0;
        $newCampaign->clicked_count = 0;
        $newCampaign->recipients_count = NewsletterSubscriber::where('is_active', true)->count();
        $newCampaign->created_by = Auth::id();
        $newCampaign->save();

        $newCampaign->load('creator');

        return response()->json([
            'success' => true,
            'message' => 'Campaign duplicated successfully',
            'data' => $newCampaign,
        ], 201);
    }

}
