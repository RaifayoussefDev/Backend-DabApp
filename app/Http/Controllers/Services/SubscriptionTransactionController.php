<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionTransaction;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Subscription Transactions",
 *     description="API endpoints for viewing subscription invoices and transaction history"
 * )
 */
class SubscriptionTransactionController extends Controller
{
    /**
     * Get transaction history
     *
     * @OA\Get(
     *     path="/api/subscription/transactions",
     *     summary="Get subscription transaction history",
     *     description="Retrieve a list of subscription transactions (invoices) for the authenticated service provider",
     *     operationId="getSubscriptionTransactions",
     *     tags={"Subscription Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="invoice_number", type="string", example="INV-20260211-XYZ"),
     *                     @OA\Property(property="amount", type="number", format="float", example=29.00),
     *                     @OA\Property(property="currency", type="string", example="SAR"),
     *                     @OA\Property(property="status", type="string", example="completed"),
     *                     @OA\Property(property="type", type="string", example="subscription"),
     *                     @OA\Property(property="date", type="string", format="date-time", example="2026-02-11T12:00:00Z"),
     *                     @OA\Property(property="billing_start", type="string", format="date", example="2026-02-11"),
     *                     @OA\Property(property="billing_end", type="string", format="date", example="2026-03-11")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="total", type="integer", example=50)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $provider = ServiceProvider::where('user_id', $user->id)->firstOrFail();

        $transactions = SubscriptionTransaction::whereHas('subscription', function ($query) use ($provider) {
            $query->where('provider_id', $provider->id);
        })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Transform collection to match desired output if needed, or return paginated resource directly
        // Here we map to ensure structure consistency
        $data = $transactions->getCollection()->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'invoice_number' => $transaction->invoice_number,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'status' => $transaction->status,
                'type' => $transaction->transaction_type,
                'date' => $transaction->created_at,
                'billing_start' => $transaction->billing_period_start,
                'billing_end' => $transaction->billing_period_end,
                'payment_method' => $transaction->payment ? ($transaction->payment->paymentMethod->name ?? 'N/A') : 'N/A',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
                'per_page' => $transactions->perPage(),
            ],
        ]);
    }
}
