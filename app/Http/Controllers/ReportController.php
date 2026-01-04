<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ReportReason;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @OA\Tag(
 *     name="Reports",
 *     description="API Endpoints for User Reporting"
 * )
 */
class ReportController extends Controller
{
    protected $notificationService;

    public function __construct(\App\Services\NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * @OA\Get(
     *     path="/api/reports/reasons",
     *     summary="Get list of report reasons",
     *     tags={"Reports"},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Type code (guide, listing) OR Type ID (1, 2)",
     *         required=false,
     *         @OA\Schema(type="string", default="default")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of report reasons",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="label_en", type="string"),
     *                 @OA\Property(property="label_ar", type="string"),
     *                 @OA\Property(property="type", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function getReasons(Request $request)
    {
        $type = $request->query('type', 'default');
        
        // Normalize type if needed (e.g. if frontend sends 'motorcycle', map to 'listing')
        if (in_array($type, ['motorcycle', 'sparepart', 'plate'])) {
            $type = 'listing';
        }

        // Fetch Type ID first
        $query = \App\Models\ReportType::query()->active();
        
        if (is_numeric($type)) {
            $query->where('id', $type);
        } else {
            $query->where('code', $type);
        }

        $reportType = $query->first();

        // Fallback to default if not found
        if (!$reportType) {
            $reportType = \App\Models\ReportType::where('code', 'default')->active()->first();
        }

        if (!$reportType) {
             return response()->json([]);
        }

        // Fetch reasons
        $reasons = ReportReason::where('report_type_id', $reportType->id)
            ->active()
            ->with(['type:id,code,name_en,name_ar'])
            ->get(['id', 'label_en', 'label_ar', 'report_type_id']);

        return response()->json($reasons);
    }

    /**
     * @OA\Post(
     *     path="/api/reports",
     *     summary="Submit a report",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reportable_id", "reportable_type", "report_reason_id"},
     *             @OA\Property(property="reportable_id", type="integer", description="ID of the item being reported", example=15),
     *             @OA\Property(property="reportable_type", type="string", description="Type of item (guide, listing, event, etc.)", example="listing"),
     *             @OA\Property(property="report_reason_id", type="integer", description="ID of the selected reason", example=1),
     *             @OA\Property(property="details", type="string", description="Optional additional details", example="This listing contains misleading price information.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Report submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="report", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reportable_id' => 'required|integer',
            'reportable_type' => 'required|string', 
            'report_reason_id' => 'required|exists:report_reasons,id',
            'details' => 'nullable|string',
        ]);

        // Check if "Other" reason requires details
        $reason = ReportReason::find($request->input('report_reason_id'));
        if ($reason && strtolower($reason->label_en) === 'other' && empty($request->input('details'))) {
             return response()->json(['error' => 'Validation Error', 'message' => 'The details field is required when choosing "Other" as a reason.'], 422);
        }

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $type = $request->input('reportable_type');
        
        // Map short names to full Model classes
        // You can update this map as you add more reportable models
        $modelMap = [
            'guide' => 'App\Models\Guide',
            'listing' => 'App\Models\Listing',
            'motorcycle' => 'App\Models\Motorcycle', // Assuming separate model or if listing uses type
            'event' => 'App\Models\Event',
            'user' => 'App\Models\User',
            'comment' => 'App\Models\GuideComment', // Example
            'review' => 'App\Models\EventReview', // Example
        ];

        // If it's a known short name, use the map. Otherwise check if class exists.
        $className = $modelMap[$type] ?? $type;
        
        // Handle case where frontend defines specific listing types but backend uses Listing model
        if (in_array($type, ['motorcycle', 'sparepart', 'plate']) && !isset($modelMap[$type])) {
             $className = 'App\Models\Listing';
        }

        if (!class_exists($className)) {
            // Check if it's a polymorphic alias
             try {
                $morphed = Relation::getMorphedModel($className);
                if ($morphed) {
                    $className = $morphed;
                } else {
                     return response()->json(['error' => 'Invalid reportable type provided.'], 400); 
                }
             } catch (\Exception $e) {
                 return response()->json(['error' => 'Invalid reportable type provided.'], 400);
             }
        }

        try {
            $report = Report::create([
                'user_id' => auth()->id(), // Nullable if not logged in, but widely expected to be auth
                'reportable_id' => $request->input('reportable_id'),
                'reportable_type' => $className,
                'report_reason_id' => $request->input('report_reason_id'),
                'details' => $request->input('details'),
                'status' => 'pending'
            ]);

            // Notify Admins
            $admins = \App\Models\User::where('role_id', 1)->get();
            foreach ($admins as $admin) {
                $this->notificationService->sendToUser($admin, 'new_report', [
                     'report_id' => $report->id,
                     'reason' => $reason ? $reason->label_en : 'Unknown',
                     'item' => class_basename($className),
                ]);
            }

            // Notify User (Confirmation)
            $user = auth()->user();
            if ($user) {
                $this->notificationService->sendToUser($user, 'report_received', [
                    'report_id' => $report->id,
                    'item' => class_basename($className),
                ]);
            }

            return response()->json(['message' => 'Report submitted successfully', 'report' => $report], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to submit report', 'message' => $e->getMessage()], 500);
        }
    }
}
