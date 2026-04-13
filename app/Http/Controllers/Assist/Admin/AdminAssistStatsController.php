<?php

namespace App\Http\Controllers\Assist\Admin;

use App\Http\Controllers\Assist\AssistBaseController;
use App\Models\Assist\AssistanceRequest;
use App\Models\Assist\HelperProfile;
use App\Models\Assist\Rating;
use Illuminate\Http\JsonResponse;
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
     *                 @OA\Property(property="total_requests", type="integer", example=320),
     *                 @OA\Property(property="completed_requests", type="integer", example=274),
     *                 @OA\Property(property="completion_rate", type="number", format="float", example=85.63),
     *                 @OA\Property(property="active_helpers", type="integer", example=42),
     *                 @OA\Property(property="verified_helpers", type="integer", example=38),
     *                 @OA\Property(property="avg_response_time_minutes", type="number", format="float", example=6.4),
     *                 @OA\Property(property="requests_by_status", type="object",
     *                     @OA\Property(property="pending", type="integer", example=5),
     *                     @OA\Property(property="accepted", type="integer", example=3),
     *                     @OA\Property(property="en_route", type="integer", example=8),
     *                     @OA\Property(property="arrived", type="integer", example=2),
     *                     @OA\Property(property="completed", type="integer", example=274),
     *                     @OA\Property(property="cancelled", type="integer", example=28)
     *                 ),
     *                 @OA\Property(property="requests_by_expertise", type="object",
     *                     example={"tire_repair": 90, "fuel": 55, "mechanical": 70}
     *                 ),
     *                 @OA\Property(property="top_helpers", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="name", type="string", example="Ahmed Al-Rashid"),
     *                         @OA\Property(property="rating", type="number", example=4.95),
     *                         @OA\Property(property="total_assists", type="integer", example=47)
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function stats(): JsonResponse
    {
        $total     = AssistanceRequest::count();
        $completed = AssistanceRequest::where('status', 'completed')->count();

        $completionRate = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

        // Average response time: accepted_at - created_at in minutes
        $avgResponseMinutes = AssistanceRequest::whereNotNull('accepted_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, accepted_at)) as avg_minutes')
            ->value('avg_minutes');

        $byStatus = AssistanceRequest::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byExpertise = AssistanceRequest::select('expertise_type_id', DB::raw('COUNT(*) as count'))
            ->with('expertiseType:id,name')
            ->groupBy('expertise_type_id')
            ->get()
            ->mapWithKeys(fn($row) => [
                optional($row->expertiseType)->name ?? $row->expertise_type_id => $row->count,
            ])
            ->toArray();

        $topHelpers = HelperProfile::with('user:id,first_name,last_name')
            ->where('is_verified', true)
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
            'active_helpers'            => HelperProfile::where('is_available', true)->count(),
            'verified_helpers'          => HelperProfile::where('is_verified', true)->count(),
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
     *         @OA\Schema(type="string", enum={"pending","accepted","en_route","arrived","completed","cancelled"})
     *     ),
     *     @OA\Parameter(name="expertise_type_id", in="query", required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(name="date_from", in="query", required=false,
     *         description="Filter requests created from this date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date", example="2026-01-01")
     *     ),
     *     @OA\Parameter(name="date_to", in="query", required=false,
     *         @OA\Schema(type="string", format="date", example="2026-04-08")
     *     ),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Paginated list of requests",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total", type="integer", example=320),
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(ref="#/components/schemas/AssistanceRequest")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function requests(\Illuminate\Http\Request $request): JsonResponse
    {
        $query = AssistanceRequest::with([
            'seeker:id,first_name,last_name',
            'helper:id,first_name,last_name',
            'expertiseType:id,name',
        ])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('expertise_type_id')) {
            $query->where('expertise_type_id', $request->expertise_type_id);
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
