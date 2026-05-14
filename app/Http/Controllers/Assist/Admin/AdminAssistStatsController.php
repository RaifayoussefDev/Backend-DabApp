<?php

namespace App\Http\Controllers\Assist\Admin;

use App\Http\Controllers\Assist\AssistBaseController;
use App\Models\Assist\AssistanceRequest;
use App\Models\Assist\AssistProposal;
use App\Models\Assist\HelperProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Assist - Admin Stats",
 *     description="Global statistics and request monitoring for admins"
 * )
 */
class AdminAssistStatsController extends AssistBaseController
{
    /**
     * @OA\Get(
     *     path="/api/assist/admin/stats",
     *     summary="Get global Velocity Assist statistics",
     *     tags={"Assist - Admin Stats"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Platform-wide statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_requests",            type="integer", example=320),
     *                 @OA\Property(property="completed_requests",        type="integer", example=274),
     *                 @OA\Property(property="completion_rate",           type="number",  format="float", example=85.63),
     *                 @OA\Property(property="active_helpers",            type="integer", example=42),
     *                 @OA\Property(property="verified_helpers",          type="integer", example=38),
     *                 @OA\Property(property="avg_response_time_minutes", type="number",  format="float", example=6.4),
     *                 @OA\Property(property="requests_by_status", type="object",
     *                     @OA\Property(property="pending",   type="integer", example=5),
     *                     @OA\Property(property="accepted",  type="integer", example=3),
     *                     @OA\Property(property="en_route",  type="integer", example=8),
     *                     @OA\Property(property="arrived",   type="integer", example=2),
     *                     @OA\Property(property="completed", type="integer", example=274),
     *                     @OA\Property(property="cancelled", type="integer", example=28)
     *                 ),
     *                 @OA\Property(property="requests_by_expertise", type="object",
     *                     description="Request count grouped by expertise name",
     *                     example={"tire_repair": 90, "fuel": 55, "mechanical": 70, "towing": 35}
     *                 ),
     *                 @OA\Property(property="top_helpers", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",            type="integer", example=2),
     *                         @OA\Property(property="name",          type="string",  example="Ahmed Al-Rashid"),
     *                         @OA\Property(property="rating",        type="number",  format="float", example=4.95),
     *                         @OA\Property(property="total_assists", type="integer", example=47)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function stats(): JsonResponse
    {
        $total     = AssistanceRequest::count();
        $completed = AssistanceRequest::where('status', 'completed')->count();

        $completionRate = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

        $avgResponseMinutes = AssistanceRequest::whereNotNull('accepted_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, accepted_at)) as avg_minutes')
            ->value('avg_minutes');

        $byStatus = AssistanceRequest::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byExpertise = DB::table('assistance_request_expertise')
            ->join('expertise_types', 'expertise_types.id', '=', 'assistance_request_expertise.expertise_type_id')
            ->select('expertise_types.name', DB::raw('COUNT(*) as count'))
            ->groupBy('expertise_types.id', 'expertise_types.name')
            ->pluck('count', 'name')
            ->toArray();

        $topHelpers = HelperProfile::with('user:id,first_name,last_name')
            ->where('status', 'accepted')
            ->orderByDesc('rating')
            ->orderByDesc('total_assists')
            ->limit(10)
            ->get()
            ->map(fn($hp) => [
                'id'            => $hp->id,
                'name'          => trim(optional($hp->user)->first_name . ' ' . optional($hp->user)->last_name),
                'rating'        => $hp->rating,
                'total_assists' => $hp->total_assists,
            ]);

        return $this->success([
            'total_requests'            => $total,
            'completed_requests'        => $completed,
            'completion_rate'           => $completionRate,
            'active_helpers'            => HelperProfile::where('status', 'accepted')->where('is_available', true)->count(),
            'verified_helpers'          => HelperProfile::where('status', 'accepted')->count(),
            'avg_response_time_minutes' => round($avgResponseMinutes ?? 0, 1),
            'requests_by_status'        => $byStatus,
            'requests_by_expertise'     => $byExpertise,
            'top_helpers'               => $topHelpers,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/assist/admin/requests",
     *     summary="Monitor all assistance requests with filters",
     *     tags={"Assist - Admin Stats"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", required=false,
     *         description="Filter by request status",
     *         @OA\Schema(type="string", enum={"pending","accepted","en_route","arrived","completed","cancelled"}, example="pending")
     *     ),
     *     @OA\Parameter(name="expertise_type_id", in="query", required=false,
     *         description="Filter by expertise type ID (matches requests that include this expertise)",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(name="date_from", in="query", required=false,
     *         description="Filter requests created from this date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date", example="2026-01-01")
     *     ),
     *     @OA\Parameter(name="date_to", in="query", required=false,
     *         description="Filter requests created up to this date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date", example="2026-04-15")
     *     ),
     *     @OA\Parameter(name="page", in="query", required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Paginated list of requests",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page",     type="integer", example=20),
     *                 @OA\Property(property="total",        type="integer", example=320),
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",             type="integer", example=12),
     *                         @OA\Property(property="status",         type="string",  example="pending"),
     *                         @OA\Property(property="description",    type="string",  example="My rear tire is flat."),
     *                         @OA\Property(property="location_label", type="string",  example="King Fahd Road, Riyadh"),
     *                         @OA\Property(property="latitude",       type="number",  format="float", example=24.714),
     *                         @OA\Property(property="longitude",      type="number",  format="float", example=46.675),
     *                         @OA\Property(property="created_at",     type="string",  format="date-time"),
     *                         @OA\Property(property="expertise_types", type="array",
     *                             @OA\Items(type="object",
     *                                 @OA\Property(property="id",   type="integer", example=1),
     *                                 @OA\Property(property="name", type="string",  example="tire_repair")
     *                             )
     *                         ),
     *                         @OA\Property(property="seeker", type="object",
     *                             @OA\Property(property="id",         type="integer", example=65),
     *                             @OA\Property(property="first_name", type="string",  example="Raifa"),
     *                             @OA\Property(property="last_name",  type="string",  example="Youssef")
     *                         ),
     *                         @OA\Property(property="helper", type="object", nullable=true,
     *                             @OA\Property(property="id",         type="integer", example=2),
     *                             @OA\Property(property="first_name", type="string",  example="Ahmed"),
     *                             @OA\Property(property="last_name",  type="string",  example="Al-Rashid")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    /**
     * @OA\Get(
     *     path="/api/assist/admin/requests/{id}",
     *     summary="Get full details of a single assistance request",
     *     description="Full admin view: seeker, helper, expertise, photos, rating, all proposals with helper profiles, and computed durations (time to accept, en-route duration, total mission time).",
     *     tags={"Assist - Admin Stats"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="AssistanceRequest ID",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\Response(response=200, description="Request detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",             type="integer", example=12),
     *                 @OA\Property(property="status",         type="string",  example="completed"),
     *                 @OA\Property(property="description",    type="string",  nullable=true),
     *                 @OA\Property(property="location_label", type="string",  example="King Fahd Road, Riyadh"),
     *                 @OA\Property(property="latitude",       type="number",  format="float", example=24.714),
     *                 @OA\Property(property="longitude",      type="number",  format="float", example=46.675),
     *                 @OA\Property(property="accepted_price", type="integer", nullable=true, example=100),
     *                 @OA\Property(property="cancel_reason",  type="string",  nullable=true),
     *                 @OA\Property(property="created_at",     type="string",  format="date-time"),
     *                 @OA\Property(property="accepted_at",    type="string",  format="date-time", nullable=true),
     *                 @OA\Property(property="arrived_at",     type="string",  format="date-time", nullable=true),
     *                 @OA\Property(property="completed_at",   type="string",  format="date-time", nullable=true),
     *                 @OA\Property(property="cancelled_at",   type="string",  format="date-time", nullable=true),
     *                 @OA\Property(property="durations", type="object",
     *                     description="Computed durations in minutes",
     *                     @OA\Property(property="time_to_accept_minutes",  type="number", nullable=true, example=4.5,
     *                         description="From request creation to seeker accepting a proposal"),
     *                     @OA\Property(property="en_route_minutes",        type="number", nullable=true, example=12.3,
     *                         description="From accepted to helper arrived"),
     *                     @OA\Property(property="on_site_minutes",         type="number", nullable=true, example=20.1,
     *                         description="From helper arrived to mission completed"),
     *                     @OA\Property(property="total_mission_minutes",   type="number", nullable=true, example=36.9,
     *                         description="From request creation to mission completed")
     *                 ),
     *                 @OA\Property(property="proposals_summary", type="object",
     *                     @OA\Property(property="total",    type="integer", example=3),
     *                     @OA\Property(property="accepted", type="integer", example=1),
     *                     @OA\Property(property="rejected", type="integer", example=2)
     *                 ),
     *                 @OA\Property(property="proposals", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",             type="integer", example=7),
     *                         @OA\Property(property="proposed_price", type="integer", example=100),
     *                         @OA\Property(property="status",         type="string",  example="accepted"),
     *                         @OA\Property(property="rejection_type", type="string",  nullable=true, example=null),
     *                         @OA\Property(property="accepted_at",    type="string",  format="date-time", nullable=true),
     *                         @OA\Property(property="rejected_at",    type="string",  format="date-time", nullable=true),
     *                         @OA\Property(property="created_at",     type="string",  format="date-time"),
     *                         @OA\Property(property="helper", type="object",
     *                             @OA\Property(property="id",              type="integer", example=2),
     *                             @OA\Property(property="first_name",      type="string",  example="Ahmed"),
     *                             @OA\Property(property="last_name",       type="string",  example="Al-Rashid"),
     *                             @OA\Property(property="email",           type="string",  example="ahmed@example.com"),
     *                             @OA\Property(property="profile_picture", type="string",  nullable=true),
     *                             @OA\Property(property="rating",          type="number",  example=4.8),
     *                             @OA\Property(property="total_assists",   type="integer", example=23)
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="seeker", type="object",
     *                     @OA\Property(property="id",              type="integer", example=65),
     *                     @OA\Property(property="first_name",      type="string",  example="Raifa"),
     *                     @OA\Property(property="last_name",       type="string",  example="Youssef"),
     *                     @OA\Property(property="email",           type="string",  example="raifa@example.com"),
     *                     @OA\Property(property="phone",           type="string",  example="+966500000000"),
     *                     @OA\Property(property="profile_picture", type="string",  nullable=true)
     *                 ),
     *                 @OA\Property(property="helper", type="object", nullable=true,
     *                     @OA\Property(property="id",              type="integer", example=2),
     *                     @OA\Property(property="first_name",      type="string",  example="Ahmed"),
     *                     @OA\Property(property="last_name",       type="string",  example="Al-Rashid"),
     *                     @OA\Property(property="email",           type="string",  example="ahmed@example.com"),
     *                     @OA\Property(property="phone",           type="string",  example="+966500000001"),
     *                     @OA\Property(property="profile_picture", type="string",  nullable=true)
     *                 ),
     *                 @OA\Property(property="rating", type="object", nullable=true,
     *                     @OA\Property(property="stars",   type="integer", example=5),
     *                     @OA\Property(property="comment", type="string",  nullable=true, example="Excellent!")
     *                 ),
     *                 @OA\Property(property="photos", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",   type="integer", example=3),
     *                         @OA\Property(property="path", type="string",  example="https://cdn.example.com/photo.jpg")
     *                     )
     *                 ),
     *                 @OA\Property(property="expertise_types", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",      type="integer", example=1),
     *                         @OA\Property(property="name",    type="string",  example="tire_repair"),
     *                         @OA\Property(property="name_en", type="string",  example="Tire Repair"),
     *                         @OA\Property(property="name_ar", type="string",  example="إصلاح الإطارات")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $assistRequest = AssistanceRequest::with([
            'seeker:id,first_name,last_name,email,phone,profile_picture',
            'helper:id,first_name,last_name,email,phone,profile_picture',
            'expertiseTypes:id,name,name_en,name_ar',
            'rating:id,request_id,stars,comment',
            'photos:id,request_id,path',
        ])->find($id);

        if (!$assistRequest) {
            return $this->error('Assistance request not found.', 404);
        }

        // Computed durations
        $durations = [
            'time_to_accept_minutes' => $assistRequest->accepted_at
                ? round($assistRequest->created_at->diffInSeconds($assistRequest->accepted_at) / 60, 1)
                : null,
            'en_route_minutes' => ($assistRequest->accepted_at && $assistRequest->arrived_at)
                ? round($assistRequest->accepted_at->diffInSeconds($assistRequest->arrived_at) / 60, 1)
                : null,
            'on_site_minutes' => ($assistRequest->arrived_at && $assistRequest->completed_at)
                ? round($assistRequest->arrived_at->diffInSeconds($assistRequest->completed_at) / 60, 1)
                : null,
            'total_mission_minutes' => $assistRequest->completed_at
                ? round($assistRequest->created_at->diffInSeconds($assistRequest->completed_at) / 60, 1)
                : null,
        ];

        // All proposals with helper profile stats
        $proposals = AssistProposal::where('request_id', $id)
            ->with('helper:id,first_name,last_name,email,profile_picture')
            ->orderBy('created_at')
            ->get()
            ->map(function ($proposal) {
                $hp = HelperProfile::where('user_id', $proposal->helper_id)
                    ->select('rating', 'total_assists')
                    ->first();

                return [
                    'id'             => $proposal->id,
                    'proposed_price' => $proposal->proposed_price,
                    'status'         => $proposal->status,
                    'rejection_type' => $proposal->rejection_type,
                    'accepted_at'    => $proposal->accepted_at,
                    'rejected_at'    => $proposal->rejected_at,
                    'created_at'     => $proposal->created_at,
                    'helper'         => [
                        'id'              => $proposal->helper->id,
                        'first_name'      => $proposal->helper->first_name,
                        'last_name'       => $proposal->helper->last_name,
                        'email'           => $proposal->helper->email,
                        'profile_picture' => $proposal->helper->profile_picture,
                        'rating'          => $hp?->rating ?? 0,
                        'total_assists'   => $hp?->total_assists ?? 0,
                    ],
                ];
            });

        $proposalCounts = $proposals->groupBy('status');

        return $this->success(array_merge($assistRequest->toArray(), [
            'durations'         => $durations,
            'proposals_summary' => [
                'total'    => $proposals->count(),
                'accepted' => ($proposalCounts->get('accepted') ?? collect())->count(),
                'rejected' => ($proposalCounts->get('rejected') ?? collect())->count(),
                'pending'  => ($proposalCounts->get('pending')  ?? collect())->count(),
            ],
            'proposals' => $proposals,
        ]));
    }

    /**
     * @OA\Patch(
     *     path="/api/assist/admin/requests/{id}/cancel",
     *     summary="Force-cancel an assistance request",
     *     description="Admin can cancel any active request. Sends a notification to the seeker and helper.",
     *     tags={"Assist - Admin Stats"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true,
     *         description="AssistanceRequest ID",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="cancel_reason", type="string", nullable=true,
     *                 example="Request cancelled by admin due to policy violation.")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Request cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Request cancelled by admin.")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Request is already completed or cancelled")
     * )
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $assistRequest = AssistanceRequest::find($id);

        if (!$assistRequest) {
            return $this->error('Assistance request not found.', 404);
        }

        if (\in_array($assistRequest->getAttribute('status'), ['completed', 'cancelled'])) {
            return $this->error('Request is already ' . $assistRequest->getAttribute('status') . '.', 422);
        }

        $assistRequest->update([
            'status'        => 'cancelled',
            'cancelled_at'  => now(),
            'cancel_reason' => $request->input('cancel_reason', 'Cancelled by admin.'),
        ]);

        return $this->success(null, 'Request cancelled by admin.');
    }

    public function requests(Request $request): JsonResponse
    {
        $query = AssistanceRequest::with([
            'seeker:id,first_name,last_name',
            'helper:id,first_name,last_name',
            'expertiseTypes:id,name',
        ])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('expertise_type_id')) {
            $query->whereHas('expertiseTypes', fn($q) => $q->where('expertise_types.id', $request->expertise_type_id));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $this->success($query->paginate(20));
    }
}
