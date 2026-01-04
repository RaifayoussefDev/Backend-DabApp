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
     *         name="type",
     *         in="query",
     *         description="Filter by reportable type (listing, guide, etc.)",
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

        if ($request->has('type')) {
            $query->where('reportable_type', 'like', '%' . $request->query('type') . '%');
        }

        return response()->json($query->latest()->paginate(20));
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
                 $this->notificationService->sendToUser($user, 'report_status_updated', [
                     'report_id' => $report->id,
                     'status' => $newStatus,
                     'item' => class_basename($report->reportable_type),
                 ]);
             }
        }

        return response()->json($report);
    }
}
