<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\PaymentSplit;
use App\Models\TrainerPayment;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Admin - Trainer Payments",
 *     description="Admin view of all trainer payment transactions and their commission splits"
 * )
 */
class AdminTrainerPaymentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/trainer-payments",
     *     summary="List all trainer payment transactions (Admin)",
     *     description="Returns all PayTabs transactions initiated for trainer bookings. Filter by payment_status, date range, or user. Each payment includes its booking and commission split.",
     *     operationId="adminListTrainerPayments",
     *     tags={"Admin - Trainer Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="payment_status", in="query", required=false,
     *         @OA\Schema(type="string", enum={"pending","paid","failed","refunded"})),
     *     @OA\Parameter(name="user_id",    in="query", required=false, @OA\Schema(type="integer", example=5)),
     *     @OA\Parameter(name="date_from",  in="query", required=false, @OA\Schema(type="string", format="date", example="2026-06-01")),
     *     @OA\Parameter(name="date_to",    in="query", required=false, @OA\Schema(type="string", format="date", example="2026-06-30")),
     *     @OA\Parameter(name="per_page",   in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(
     *         response=200,
     *         description="Payments list",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",             type="integer", example=1),
     *                         @OA\Property(property="amount",         type="number",  format="float", example=300.00),
     *                         @OA\Property(property="currency",       type="string",  example="SAR"),
     *                         @OA\Property(property="payment_status", type="string",  example="paid"),
     *                         @OA\Property(property="tran_ref",       type="string",  example="TST2212200065566"),
     *                         @OA\Property(property="cart_id",        type="string",  example="TRAINER_42"),
     *                         @OA\Property(property="resp_code",      type="string",  example="A"),
     *                         @OA\Property(property="created_at",     type="string",  format="datetime"),
     *                         @OA\Property(property="user", type="object",
     *                             @OA\Property(property="id",         type="integer"),
     *                             @OA\Property(property="first_name", type="string"),
     *                             @OA\Property(property="last_name",  type="string"),
     *                             @OA\Property(property="email",      type="string")
     *                         ),
     *                         @OA\Property(property="split", type="object", nullable=true,
     *                             @OA\Property(property="commission_percentage", type="number", format="float", example=20.00),
     *                             @OA\Property(property="commission_amount",     type="number", format="float", example=60.00),
     *                             @OA\Property(property="trainer_amount",        type="number", format="float", example=240.00),
     *                             @OA\Property(property="status",                type="string", example="settled")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="summary", type="object",
     *                     @OA\Property(property="total_transactions", type="integer", example=260),
     *                     @OA\Property(property="total_paid",         type="number",  format="float", example=48000.00),
     *                     @OA\Property(property="total_failed",       type="integer", example=8)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = TrainerPayment::with([
            'user:id,first_name,last_name,email',
            'split:id,payment_id,commission_percentage,commission_amount,trainer_amount,status',
        ]);

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->latest()->paginate($request->get('per_page', 20));

        $summary = [
            'total_transactions' => TrainerPayment::count(),
            'total_paid'         => (float) TrainerPayment::where('payment_status', 'paid')->sum('amount'),
            'total_failed'       => TrainerPayment::where('payment_status', 'failed')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => ['data' => $payments, 'summary' => $summary],
            'message' => 'Payments retrieved successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/trainer-payments/{id}",
     *     summary="Get payment transaction details (Admin)",
     *     description="Returns full PayTabs transaction details including the raw PayTabs response, the linked booking, commission split, and payout status.",
     *     operationId="adminShowTrainerPayment",
     *     tags={"Admin - Trainer Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Payment details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id",               type="integer", example=1),
     *                 @OA\Property(property="amount",           type="number",  format="float", example=300.00),
     *                 @OA\Property(property="currency",         type="string",  example="SAR"),
     *                 @OA\Property(property="payment_status",   type="string",  example="paid"),
     *                 @OA\Property(property="tran_ref",         type="string",  example="TST2212200065566"),
     *                 @OA\Property(property="cart_id",          type="string",  example="TRAINER_42"),
     *                 @OA\Property(property="resp_code",        type="string",  example="A"),
     *                 @OA\Property(property="resp_message",     type="string",  example="Authorised"),
     *                 @OA\Property(property="paytabs_response", type="object",  description="Full raw JSON from PayTabs"),
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id",         type="integer"),
     *                     @OA\Property(property="first_name", type="string"),
     *                     @OA\Property(property="last_name",  type="string"),
     *                     @OA\Property(property="email",      type="string")
     *                 ),
     *                 @OA\Property(property="booking", type="object", nullable=true,
     *                     @OA\Property(property="id",           type="integer"),
     *                     @OA\Property(property="booking_date", type="string", format="date"),
     *                     @OA\Property(property="status",       type="string"),
     *                     @OA\Property(property="price",        type="number", format="float")
     *                 ),
     *                 @OA\Property(property="split", type="object", nullable=true,
     *                     @OA\Property(property="total_amount",          type="number", format="float", example=300.00),
     *                     @OA\Property(property="commission_percentage", type="number", format="float", example=20.00),
     *                     @OA\Property(property="commission_amount",     type="number", format="float", example=60.00),
     *                     @OA\Property(property="trainer_amount",        type="number", format="float", example=240.00),
     *                     @OA\Property(property="status",                type="string", example="settled")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Payment not found")
     * )
     */
    public function show(int $id)
    {
        $payment = TrainerPayment::with([
            'user:id,first_name,last_name,email,phone',
            'booking:id,booking_date,start_time,end_time,status,price,session_type',
            'split',
        ])->find($id);

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $payment,
            'message' => 'Payment details retrieved',
        ]);
    }
}
