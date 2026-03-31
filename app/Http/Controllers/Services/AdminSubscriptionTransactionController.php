<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionTransaction;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Admin Subscription Transactions",
 *     description="API endpoints for viewing all subscription transactions (Admin)"
 * )
 */
class AdminSubscriptionTransactionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/subscription-transactions",
     *     summary="List all subscription transactions (Admin)",
     *     operationId="adminGetTransactions",
     *     tags={"Admin Subscription Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"completed", "failed", "pending", "refunded"})),
     *     @OA\Parameter(name="provider_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $query = SubscriptionTransaction::with(['subscription.provider.user', 'payment']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('provider_id')) {
            $query->whereHas('subscription', function($q) use ($request) {
                $q->where('provider_id', $request->provider_id);
            });
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        $perPage = $request->get('per_page', 15);
        $transactions = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions,
            'message' => 'Subscription transactions retrieved successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/subscription-transactions/{id}",
     *     summary="Get subscription transaction details (Admin)",
     *     operationId="adminGetTransaction",
     *     tags={"Admin Subscription Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show($id)
    {
        $transaction = SubscriptionTransaction::with(['subscription.provider.user', 'subscription.plan', 'payment.paymentMethod'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $transaction,
            'message' => 'Subscription transaction details retrieved successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/subscription-transactions/stats",
     *     summary="Get global subscription transaction statistics (Admin)",
     *     operationId="adminGetTransactionStats",
     *     tags={"Admin Subscription Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function stats()
    {
        $totalRevenue = SubscriptionTransaction::where('status', 'completed')->sum('amount');
        $monthlyRevenue = SubscriptionTransaction::where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
        
        $statusDistribution = SubscriptionTransaction::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => $totalRevenue,
                'monthly_revenue' => $monthlyRevenue,
                'status_distribution' => $statusDistribution
            ],
            'message' => 'Transaction statistics retrieved successfully'
        ]);
    }
}
