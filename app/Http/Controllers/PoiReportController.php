<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PoiReport;
use App\Models\PointOfInterest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="POI Reports",
 *     description="API Endpoints for reporting POI issues (closed, wrong info, duplicate, etc.)"
 * )
 */
class PoiReportController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pois/{poi_id}/reports",
     *     summary="Get all reports for a POI (Admin only)",
     *     tags={"POI Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="poi_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=403, description="Forbidden - Admin only"),
     *     @OA\Response(response=404, description="POI not found")
     * )
     */
    public function index(int $poiId): JsonResponse
    {
        }

        $poi = PointOfInterest::find($poiId);

        if (!$poi) {
            return response()->json([
                'success' => false,
                'message' => 'Point of interest not found',
            ], 404);
        }

        $reports = PoiReport::where('poi_id', $poiId)
            ->with('user')
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/pois/{poi_id}/reports",
     *     summary="Report an issue with a POI",
     *     tags={"POI Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="poi_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", example="Closed permanently"),
     *             @OA\Property(property="description", type="string", example="This location has been closed for 6 months")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Report submitted successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, int $poiId): JsonResponse
    {
        $poi = PointOfInterest::find($poiId);

        if (!$poi) {
            return response()->json([
                'success' => false,
                'message' => 'Point of interest not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if user already reported this POI with pending status
        $existingReport = PoiReport::where('poi_id', $poiId)
            ->where('user_id', Auth::id())
            ->where('status', 'pending')
            ->first();

        if ($existingReport) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending report for this POI',
            ], 422);
        }

        $report = PoiReport::create([
            'poi_id' => $poiId,
            'user_id' => Auth::id(),
            'reason' => $request->reason,
            'description' => $request->description,
            'status' => 'pending',
        ]);

        $report->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Report submitted successfully. We will review it shortly.',
            'data' => $report,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pois/{poi_id}/reports/{id}",
     *     summary="Get a specific report",
     *     tags={"POI Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="poi_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Report not found")
     * )
     */
    public function show(int $poiId, int $id): JsonResponse
    {
        $report = PoiReport::where('poi_id', $poiId)->find($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found',
            ], 404);
        }

        // Only admin or report creator can view
        if ($report->user_id !== Auth::id() ) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this report',
            ], 403);
        }

        $report->load(['user', 'poi']);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/pois/{poi_id}/reports/{id}/status",
     *     summary="Update report status (Admin only)",
     *     tags={"POI Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="poi_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
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
     *             @OA\Property(property="status", type="string", enum={"pending","resolved","rejected"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Report status updated successfully"),
     *     @OA\Response(response=403, description="Forbidden - Admin only"),
     *     @OA\Response(response=404, description="Report not found")
     * )
     */
    public function updateStatus(Request $request, int $poiId, int $id): JsonResponse
    {
        }

        $report = PoiReport::where('poi_id', $poiId)->find($id);

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
     *     path="/api/pois/{poi_id}/reports/{id}",
     *     summary="Delete a report",
     *     tags={"POI Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="poi_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Report deleted successfully"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Report not found")
     * )
     */
    public function destroy(int $poiId, int $id): JsonResponse
    {
        $report = PoiReport::where('poi_id', $poiId)->find($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found',
            ], 404);
        }

        // Only admin or report creator can delete
        if ($report->user_id !== Auth::id() ) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this report',
            ], 403);
        }

        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'Report deleted successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/reports/pending",
     *     summary="Get all pending reports (Admin only)",
     *     tags={"POI Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=403, description="Forbidden - Admin only")
     * )
     */
    public function pending(): JsonResponse
    {
        }

        $reports = PoiReport::where('status', 'pending')
            ->with(['user', 'poi'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/user/reports",
     *     summary="Get all reports by the authenticated user",
     *     tags={"POI Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function userReports(): JsonResponse
    {
        $reports = PoiReport::where('user_id', Auth::id())
            ->with('poi')
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/reports/stats",
     *     summary="Get report statistics (Admin only)",
     *     tags={"POI Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=403, description="Forbidden - Admin only")
     * )
     */
    public function stats(): JsonResponse
    {
        }

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
