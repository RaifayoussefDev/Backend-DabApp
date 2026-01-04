<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\NotificationService;
use App\Models\User;

/**
 * @OA\Tag(
 *     name="Admin Reports",
 *     description="API Endpoints for Managing User Reports"
 * )
 */
class ReportController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * @OA\Get(
     *     path="/api/admin/reports",
     *     summary="List all reports",
     *     tags={"Admin Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (pending, resolved, dismissed)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "resolved", "dismissed"})
     *     ),
     *     @OA\Parameter(
     *         name="report_type_id",
     *         in="query",
     *         description="Filter by Report Type ID (e.g., 1 for Guide)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="report_reason_id",
     *         in="query",
     *         description="Filter by Report Reason ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page (default: 20)",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by reportable type string (e.g. 'Listing')",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of reports",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Report::query()->with(['user', 'reason', 'reason.type']);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('report_type_id')) {
            $query->whereHas('reason', function ($q) use ($request) {
                $q->where('report_type_id', $request->query('report_type_id'));
            });
        }

        if ($request->has('report_reason_id')) {
            $query->where('report_reason_id', $request->query('report_reason_id'));
        }

        if ($request->has('type')) {
            // Support searching by ID if numeric (though type should usually be report_type_id, keeping for backward compat)
             if (is_numeric($request->query('type'))) {
                 // Try to match reportable_id or report_type_id? No, user said "filter by id not libelle" for GET reports.
                 // Assuming they mean filtering by Report Type ID using the 'type' param or dedicated param.
                 // I added dedicated report_type_id above. Keeping 'type' for reportable_type string match.
                 $query->where('reportable_type', 'like', '%' . $request->query('type') . '%');
             } else {
                 $query->where('reportable_type', 'like', '%' . $request->query('type') . '%');
             }
        }
        
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);
        
        if ($perPage > 100) {
            $perPage = 100;
        }

        return response()->json($query->latest()->paginate($perPage, ['*'], 'page', $page));
    }

    /**
     * @OA\Get(
     *     path="/api/admin/reports/{id}",
     *     summary="Get specific report details",
     *     tags={"Admin Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Report details",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function show($id)
    {
        return response()->json(Report::with(['user', 'reason', 'reportable'])->findOrFail($id));
    }

    /**
     * @OA\Put(
     *     path="/api/admin/reports/{id}",
     *     summary="Update report status",
     *     tags={"Admin Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"pending", "resolved", "dismissed"}, example="resolved")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Report updated"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $report = Report::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,resolved,dismissed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $oldStatus = $report->status;
        $newStatus = $request->status;
        
        $report->update(['status' => $newStatus]);

        // Send Notification to User if status changed
        if ($oldStatus !== $newStatus && $report->user_id) {
             $user = User::find($report->user_id);
             if ($user) {
                 $reason = $report->reason;
                 $reasonLabel = ($reason && $user->language === 'ar') ? $reason->label_ar : ($reason ? $reason->label_en : 'N/A');

                 $this->notificationService->sendToUser($user, 'report_status_updated', [
                     'report_id' => $report->id,
                     'status' => $newStatus,
                     'item' => class_basename($report->reportable_type),
                     'reason' => $reasonLabel,
                 ]);
             }
        }

        return response()->json($report);
    }
}
