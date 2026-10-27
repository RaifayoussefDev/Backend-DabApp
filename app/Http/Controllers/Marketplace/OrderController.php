<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\Order;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Marketplace - Orders",
 *     description="A customer's own orders. Access: User (Bearer JWT)."
 * )
 */
class OrderController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/marketplace/orders",
     *     summary="List my orders",
     *     tags={"Marketplace - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Orders", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MarketplaceOrder")),
     *         @OA\Property(property="meta", type="object")
     *     )),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $page = Order::where('user_id', Auth::id())->with('items')->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(), 'per_page' => $page->perPage(),
                'last_page' => $page->lastPage(), 'total' => $page->total(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/marketplace/orders/{id}",
     *     summary="My order detail",
     *     tags={"Marketplace - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Order", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/MarketplaceOrder"))),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=404, description="Not your order")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $order = $this->find($id);

        return $order
            ? response()->json(['data' => $order])
            : response()->json(['message' => 'Order not found'], 404);
    }

    /**
     * @OA\Post(
     *     path="/api/marketplace/orders/{id}/cancel",
     *     summary="Cancel my order before it ships",
     *     description="Allowed while the order is pending_payment/paid/processing AND no item has shipped/delivered yet (a multi-vendor order can have one vendor ship before another). Releases reserved stock. Refund (if already paid) is a manual support process, not automated here.",
     *     tags={"Marketplace - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=false, @OA\JsonContent(@OA\Property(property="reason", type="string", nullable=true))),
     *     @OA\Response(response=200, description="Cancelled", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/MarketplaceOrder"))),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=404, description="Not your order"),
     *     @OA\Response(response=409, description="Cannot be cancelled anymore", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict"))
     * )
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = $this->find($id);
        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if (! in_array($order->status, [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAID, Order::STATUS_PROCESSING], true)) {
            return response()->json(['message' => 'This order can no longer be cancelled', 'code' => 'NOT_CANCELLABLE'], 409);
        }

        if ($order->items->contains(fn ($i) => in_array($i->fulfillment_status, ['shipped', 'delivered'], true))) {
            return response()->json(['message' => 'Part of this order has already shipped', 'code' => 'ALREADY_SHIPPED'], 409);
        }

        DB::transaction(function () use ($order, $request) {
            $locked = Order::where('id', $order->id)->lockForUpdate()->first();
            $items = $locked->items()->get();

            $items->whereNull('variant_id')->groupBy('product_id')->each(function ($group, $productId) {
                Product::where('id', $productId)->lockForUpdate()->first()
                    ?->increment('stock_quantity', $group->sum('quantity'));
            });

            $items->whereNotNull('variant_id')->groupBy('variant_id')->each(function ($group, $variantId) {
                ProductVariant::where('id', $variantId)->lockForUpdate()->first()
                    ?->increment('stock_quantity', $group->sum('quantity'));
            });

            $locked->update([
                'status'        => Order::STATUS_CANCELLED,
                'cancelled_at'  => now(),
                'cancel_reason' => $request->input('reason', 'Cancelled by customer'),
            ]);
            $locked->items()->update(['fulfillment_status' => 'cancelled']);
            $locked->payments()->where('payment_status', 'pending')->update(['payment_status' => 'failed', 'failure_reason' => 'Order cancelled by customer']);
        });

        return response()->json(['data' => $order->fresh('items')]);
    }

    /**
     * @OA\Post(
     *     path="/api/marketplace/orders/{id}/pay",
     *     summary="Retry payment for a still-unpaid order",
     *     description="Use when the initial PayTabs payment/request call itself failed (not a declined card) and the order hasn't expired yet.",
     *     tags={"Marketplace - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Payment started", @OA\JsonContent(@OA\Property(property="payment_url", type="string", nullable=true))),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=404, description="Not your order"),
     *     @OA\Response(response=409, description="Order no longer payable", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict"))
     * )
     */
    public function pay(CheckoutController $checkout, int $id): JsonResponse
    {
        $order = $this->find($id);
        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->status !== Order::STATUS_PENDING_PAYMENT || $order->expires_at?->isPast()) {
            return response()->json(['message' => 'This order can no longer be paid', 'code' => 'NOT_PAYABLE'], 409);
        }

        return response()->json(['payment_url' => $checkout->retryPayment($order)]);
    }

    private function find(int $id): ?Order
    {
        return Order::where('user_id', Auth::id())->with('items', 'payments', 'promoCodes')->find($id);
    }
}
