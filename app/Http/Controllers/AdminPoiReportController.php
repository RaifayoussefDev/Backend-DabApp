<?php

namespace App\Http\Controllers;

use App\Models\PoiReport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin POI Reports",
 *     description="Admin API Endpoints for managing all POI reports"
 * )
 */
class AdminPoiReportController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/poi-reports",
     *     summary="Get all POI reports (Admin)",
     *     tags={"Admin POI Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by reason or description",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by report status (pending, resolved, rejected)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page. Leave empty to get all results.",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = PoiReport::with(['user', 'poi']);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $perPage = $request->input('per_page');

        if ($perPage === null || $perPage === '') {
            $reports = $query->latest()->get();
        } else {
            $reports = $query->latest()->paginate((int) $perPage);
        }

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/poi-reports/{id}",
     *     summary="Get a specific POI report (Admin)",
     *     tags={"Admin POI Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Report not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $report = PoiReport::with(['user', 'poi'])->find($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/poi-reports/{id}/status",
     *     summary="Update a POI report status (Admin)",
     *     tags={"Admin POI Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             example={
     *                 "status": "resolved"
     *             },
     *             @OA\Property(property="status", type="string", enum={"pending","resolved","rejected"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Report status updated successfully"),
     *     @OA\Response(response=404, description="Report not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $report = PoiReport::find($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,resolved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $report->update(['status' => $request->status]);
        $report->load(['user', 'poi']);

        return response()->json([
            'success' => true,
            'message' => 'Report status updated successfully',
            'data' => $report,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/poi-reports/{id}",
     *     summary="Delete a POI report (Admin)",
     *     tags={"Admin POI Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Report deleted successfully"),
     *     @OA\Response(response=404, description="Report not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $report = PoiReport::find($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found',
            ], 404);
        }

        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'Report deleted successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/poi-reports/stats/overview",
     *     summary="Get POI report statistics (Admin)",
     *     tags={"Admin POI Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_reports' => PoiReport::count(),
            'pending_reports' => PoiReport::where('status', 'pending')->count(),
            'resolved_reports' => PoiReport::where('status', 'resolved')->count(),
            'rejected_reports' => PoiReport::where('status', 'rejected')->count(),
            'reports_this_month' => PoiReport::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'top_reasons' => PoiReport::select('reason', \DB::raw('count(*) as count'))
                ->groupBy('reason')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
