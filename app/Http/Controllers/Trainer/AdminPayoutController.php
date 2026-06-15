<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\TrainerPayout;
use App\Services\NotificationService;
use App\Traits\ExportsToExcel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Admin - Payouts",
 *     description="Manage trainer payouts — approve, mark as paid, upload transfer proof"
 * )
 */
class AdminPayoutController extends Controller
{
    use ExportsToExcel;

    protected NotificationService $notifications;

    public function __construct(NotificationService $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * @OA\Get(
     *     path="/api/admin/payouts",
     *     summary="List all payouts (Admin)",
     *     description="Returns trainer payouts with optional status filter. Includes trainer info and split details.",
     *     operationId="adminListPayouts",
     *     tags={"Admin - Payouts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status",     in="query", required=false, @OA\Schema(type="string", enum={"pending","approved","paid","failed"})),
     *     @OA\Parameter(name="trainer_id", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="per_page",   in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(
     *         response=200,
     *         description="Payouts retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",           type="integer", example=1),
     *                         @OA\Property(property="amount",       type="number",  format="float", example=120.00),
     *                         @OA\Property(property="currency",     type="string",  example="SAR"),
     *                         @OA\Property(property="status",       type="string",  example="pending"),
     *                         @OA\Property(property="bank_name",    type="string",  nullable=true),
     *                         @OA\Property(property="iban",         type="string",  nullable=true),
     *                         @OA\Property(property="transfer_ref", type="string",  nullable=true),
     *                         @OA\Property(property="transfer_proof_url", type="string", nullable=true),
     *                         @OA\Property(property="approved_at",  type="string",  format="datetime", nullable=true),
     *                         @OA\Property(property="paid_at",      type="string",  format="datetime", nullable=true),
     *                         @OA\Property(property="trainer", type="object",
     *                             @OA\Property(property="id",   type="integer"),
     *                             @OA\Property(property="name", type="string")
     *                         ),
     *                         @OA\Property(property="split", type="object",
     *                             @OA\Property(property="total_amount",          type="number", format="float", example=150.00),
     *                             @OA\Property(property="commission_percentage", type="number", format="float", example=20.00),
     *                             @OA\Property(property="commission_amount",     type="number", format="float", example=30.00),
     *                             @OA\Property(property="trainer_amount",        type="number", format="float", example=120.00)
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="summary", type="object",
     *                     @OA\Property(property="total_pending_amount",  type="number", format="float", example=3600.00),
     *                     @OA\Property(property="total_paid_amount",     type="number", format="float", example=12000.00),
     *                     @OA\Property(property="count_pending",         type="integer", example=15),
     *                     @OA\Property(property="count_paid",            type="integer", example=48)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = TrainerPayout::with([
            'trainer:id,name,name_ar',
            'split:id,total_amount,commission_percentage,commission_amount,trainer_amount',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('trainer_id')) { $query->where('trainer_id', $request->trainer_id); }
        if ($request->filled('date_from'))  { $query->whereDate('created_at', '>=', $request->date_from); }
        if ($request->filled('date_to'))    { $query->whereDate('created_at', '<=', $request->date_to); }
        if ($request->filled('amount_min')) { $query->where('amount', '>=', $request->amount_min); }
        if ($request->filled('amount_max')) { $query->where('amount', '<=', $request->amount_max); }

        $payouts = $query->latest()->paginate($request->get('per_page', 20));

        $payouts->getCollection()->transform(fn ($p) => array_merge($p->toArray(), [
            'transfer_proof_url' => $p->transfer_proof_url,
        ]));

        $summary = [
            'total_pending_amount' => TrainerPayout::where('status', 'pending')->sum('amount'),
            'total_paid_amount'    => TrainerPayout::where('status', 'paid')->sum('amount'),
            'count_pending'        => TrainerPayout::where('status', 'pending')->count(),
            'count_paid'           => TrainerPayout::where('status', 'paid')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => ['data' => $payouts, 'summary' => $summary],
            'message' => 'Payouts retrieved successfully',
        ]);
    }

    public function export(Request $request)
    {
        $query = TrainerPayout::with(['trainer:id,name']);

        if ($request->filled('status'))     { $query->where('status', $request->status); }
        if ($request->filled('trainer_id')) { $query->where('trainer_id', $request->trainer_id); }
        if ($request->filled('date_from'))  { $query->whereDate('created_at', '>=', $request->date_from); }
        if ($request->filled('date_to'))    { $query->whereDate('created_at', '<=', $request->date_to); }
        if ($request->filled('amount_min')) { $query->where('amount', '>=', $request->amount_min); }
        if ($request->filled('amount_max')) { $query->where('amount', '<=', $request->amount_max); }

        $items    = $query->latest()->get();
        $cols     = ['ID', 'Trainer', 'Amount', 'Currency', 'Status', 'Transfer Ref', 'Bank', 'IBAN', 'Approved At', 'Paid At', 'Created At'];
        $filename = 'trainer-payouts-' . now()->format('Y-m-d');

        $rowMapper = fn ($p) => [
            $p->id, $p->trainer?->name,
            $p->amount, $p->currency ?? 'AED',
            $p->status, $p->transfer_ref,
            $p->bank_name, $p->iban,
            $p->approved_at?->format('Y-m-d H:i'),
            $p->paid_at?->format('Y-m-d H:i'),
            $p->created_at?->format('Y-m-d H:i'),
        ];

        if ($request->get('format') === 'excel') {
            return $this->excelResponse($filename, $cols, $items->map($rowMapper));
        }

        return response()->stream(function () use ($items, $cols, $rowMapper) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $cols);
            foreach ($items as $p) { fputcsv($file, $rowMapper($p)); }
            fclose($file);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/payouts/{id}/approve",
     *     summary="Approve a payout",
     *     description="Approves a pending payout. Optionally record bank details for the transfer. A push notification + email is sent to the trainer.",
     *     operationId="adminApprovePayout",
     *     tags={"Admin - Payouts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="bank_name", type="string", example="Al Rajhi Bank"),
     *             @OA\Property(property="iban",      type="string", example="SA0380000000608010167519"),
     *             @OA\Property(property="notes",     type="string", example="Ready for transfer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payout approved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Payout approved")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Payout is not in pending status"),
     *     @OA\Response(response=404, description="Payout not found")
     * )
     */
    public function approve(Request $request, int $id)
    {
        $admin  = JWTAuth::parseToken()->authenticate();
        $payout = TrainerPayout::find($id);

        if (!$payout) {
            return response()->json(['success' => false, 'message' => 'Payout not found'], 404);
        }

        if ($payout->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Payout is not in pending status'], 400);
        }

        $request->validate([
            'bank_name' => 'nullable|string|max:255',
            'iban'      => 'nullable|string|max:34',
            'notes'     => 'nullable|string|max:1000',
        ]);

        $payout->update([
            'status'      => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'bank_name'   => $request->bank_name ?? $payout->bank_name,
            'iban'        => $request->iban      ?? $payout->iban,
            'notes'       => $request->notes     ?? $payout->notes,
        ]);

        try {
            $this->notifications->notifyTrainerPayoutApproved($payout->trainer->user, $payout);
        } catch (\Exception $e) {
            Log::error('AdminPayoutController@approve notify failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Payout approved']);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/payouts/{id}/mark-paid",
     *     summary="Mark payout as paid",
     *     description="Mark an approved payout as paid after the bank transfer is done. Upload proof (screenshot/receipt) to confirm the transaction. A push notification + email is sent to the trainer.",
     *     operationId="adminMarkPayoutPaid",
     *     tags={"Admin - Payouts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"transfer_ref"},
     *                 @OA\Property(property="transfer_ref",   type="string", example="IB20260612001234",
     *                     description="Bank transfer reference number"),
     *                 @OA\Property(property="transfer_proof", type="string", format="binary",
     *                     description="Screenshot or receipt of the transfer (image, max 5MB)")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payout marked as paid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success",             type="boolean", example=true),
     *             @OA\Property(property="message",             type="string",  example="Payout marked as paid"),
     *             @OA\Property(property="transfer_ref",        type="string",  example="IB20260612001234"),
     *             @OA\Property(property="transfer_proof_url",  type="string",  example="https://example.com/storage/payouts/proof.jpg")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Payout is not in approved status"),
     *     @OA\Response(response=404, description="Payout not found"),
     *     @OA\Response(response=422, description="Validation error — transfer_ref required")
     * )
     */
    /**
     * @OA\Get(
     *     path="/api/admin/payouts/{id}",
     *     summary="Get payout details (Admin)",
     *     description="Returns full details of a single payout including trainer info, linked payment split, booking, and transfer proof if uploaded.",
     *     operationId="adminShowPayout",
     *     tags={"Admin - Payouts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Payout details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",                   type="integer", example=1),
     *                 @OA\Property(property="amount",               type="number",  format="float", example=240.00),
     *                 @OA\Property(property="currency",             type="string",  example="SAR"),
     *                 @OA\Property(property="status",               type="string",  example="approved"),
     *                 @OA\Property(property="bank_name",            type="string",  nullable=true, example="Al Rajhi Bank"),
     *                 @OA\Property(property="iban",                 type="string",  nullable=true, example="SA0380000000608010167519"),
     *                 @OA\Property(property="transfer_ref",         type="string",  nullable=true),
     *                 @OA\Property(property="transfer_proof_url",   type="string",  nullable=true),
     *                 @OA\Property(property="notes",                type="string",  nullable=true),
     *                 @OA\Property(property="approved_at",          type="string",  format="datetime", nullable=true),
     *                 @OA\Property(property="paid_at",              type="string",  format="datetime", nullable=true),
     *                 @OA\Property(property="trainer", type="object",
     *                     @OA\Property(property="id",        type="integer"),
     *                     @OA\Property(property="name",      type="string"),
     *                     @OA\Property(property="specialty", type="string")
     *                 ),
     *                 @OA\Property(property="split", type="object",
     *                     @OA\Property(property="total_amount",          type="number", format="float"),
     *                     @OA\Property(property="commission_percentage", type="number", format="float"),
     *                     @OA\Property(property="commission_amount",     type="number", format="float"),
     *                     @OA\Property(property="trainer_amount",        type="number", format="float"),
     *                     @OA\Property(property="status",                type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Payout not found")
     * )
     */
    public function show(int $id)
    {
        $payout = TrainerPayout::with([
            'trainer:id,name,name_ar,specialty',
            'split',
        ])->find($id);

        if (!$payout) {
            return response()->json(['success' => false, 'message' => 'Payout not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => array_merge($payout->toArray(), ['transfer_proof_url' => $payout->transfer_proof_url]),
            'message' => 'Payout details retrieved',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/payouts/{id}/reject",
     *     summary="Reject a payout",
     *     description="Rejects a pending or approved payout. A reason is required. A push notification + email is sent to the trainer.",
     *     operationId="adminRejectPayout",
     *     tags={"Admin - Payouts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", example="Incorrect IBAN provided by trainer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payout rejected",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Payout rejected")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Payout cannot be rejected — already paid"),
     *     @OA\Response(response=404, description="Payout not found"),
     *     @OA\Response(response=422, description="Validation error — reason is required")
     * )
     */
    public function reject(Request $request, int $id)
    {
        $payout = TrainerPayout::find($id);

        if (!$payout) {
            return response()->json(['success' => false, 'message' => 'Payout not found'], 404);
        }

        if ($payout->status === 'paid') {
            return response()->json(['success' => false, 'message' => 'Cannot reject a payout that has already been paid'], 400);
        }

        $request->validate(['reason' => 'required|string|max:1000']);

        $payout->update([
            'status' => 'failed',
            'notes'  => '[REJECTED] ' . $request->reason,
        ]);

        try {
            $this->notifications->notifyTrainerPayoutRejected($payout->trainer->user, $payout, $request->reason);
        } catch (\Exception $e) {
            Log::error('AdminPayoutController@reject notify failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Payout rejected']);
    }

    public function markPaid(Request $request, int $id)
    {
        $payout = TrainerPayout::find($id);

        if (!$payout) {
            return response()->json(['success' => false, 'message' => 'Payout not found'], 404);
        }

        if ($payout->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'Payout must be approved before marking as paid'], 400);
        }

        $request->validate([
            'transfer_ref'   => 'required|string|max:255',
            'transfer_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $proofPath = $payout->transfer_proof;
        if ($request->hasFile('transfer_proof')) {
            if ($proofPath) {
                Storage::disk('public')->delete($proofPath);
            }
            $proofPath = $request->file('transfer_proof')->store('payouts/proofs', 'public');
        }

        $payout->update([
            'status'         => 'paid',
            'transfer_ref'   => $request->transfer_ref,
            'transfer_proof' => $proofPath,
            'paid_at'        => now(),
        ]);

        // Mark the payment split as settled
        $payout->split()->update(['status' => 'settled', 'settled_at' => now()]);

        try {
            $this->notifications->notifyTrainerPayoutPaid($payout->trainer->user, $payout->fresh());
        } catch (\Exception $e) {
            Log::error('AdminPayoutController@markPaid notify failed: ' . $e->getMessage());
        }

        return response()->json([
            'success'            => true,
            'message'            => 'Payout marked as paid',
            'transfer_ref'       => $request->transfer_ref,
            'transfer_proof_url' => $payout->fresh()->transfer_proof_url,
        ]);
    }
}
