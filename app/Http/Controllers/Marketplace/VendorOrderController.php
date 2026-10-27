<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketplace\Concerns\RequiresActiveVendor;
use App\Models\Marketplace\Order;
use App\Models\Marketplace\OrderItem;
use App\Services\Marketplace\OrderStatusRollupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Marketplace - Vendor orders",
 *     description="A vendor's own order lines, for fulfillment. Only lines belonging to a paid order are visible/editable - a pending_payment order never shows up here. Access: User (Bearer JWT)."
 * )
 */
class VendorOrderController extends Controller
{
    use RequiresActiveVendor;

    private const FORWARD_FLOW = ['pending', 'processing', 'shipped', 'delivered'];

    /**
     * @OA\Get(
     *     path="/api/marketplace/vendor/orders",
     *     summary="List my order items",
     *     tags={"Marketplace - Vendor orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="fulfillment_status", in="query", required=false, @OA\Schema(type="string", enum={"pending","processing","shipped","delivered","cancelled"})),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Order items", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/VendorMarketplaceOrderItem")),
     *         @OA\Property(property="meta", type="object")
     *     )),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, description="Shop not active", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict")),
     *     @OA\Response(response=404, description="No shop", @OA\JsonContent(example={"message": "You do not have a vendor account", "code": "NO_VENDOR"}))
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $vendor = $this->myActiveVendorOrError();
        if ($vendor instanceof JsonResponse) {
            return $vendor;
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $page = OrderItem::where('vendor_id', $vendor->id)
            ->whereHas('order', fn ($q) => $q->where('status', '!=', Order::STATUS_PENDING_PAYMENT))
            ->when($request->filled('fulfillment_status'), fn ($q) => $q->where('fulfillment_status', $request->input('fulfillment_status')))
            ->with('order:id,order_number,status,created_at', 'product:id,name,name_ar', 'variant')
            ->orderByDesc('id')
            ->paginate($perPage);

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
     *     path="/api/marketplace/vendor/orders/items/{id}",
     *     summary="My order item detail",
     *     tags={"Marketplace - Vendor orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Order item", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/VendorMarketplaceOrderItem"))),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, description="Shop not active", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict")),
     *     @OA\Response(response=404, description="No shop, or not your order item")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $vendor = $this->myActiveVendorOrError();
        if ($vendor instanceof JsonResponse) {
            return $vendor;
        }

        $item = $this->find($vendor->id, $id);

        return $item
            ? response()->json(['data' => $item])
            : response()->json(['message' => 'Order item not found'], 404);
    }

    /**
     * @OA\Put(
     *     path="/api/marketplace/vendor/orders/items/{id}",
     *     summary="Update fulfillment for my order item",
     *     description="Forward-only: pending -> processing -> shipped -> delivered (or cancelled, blocked once shipped/delivered). Setting shipped/delivered stamps shipped_at/delivered_at. The parent order's own status rolls up once every vendor's lines in it cross the same step.",
     *     tags={"Marketplace - Vendor orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"fulfillment_status"},
     *         @OA\Property(property="fulfillment_status", type="string", enum={"processing","shipped","delivered"}),
     *         @OA\Property(property="tracking_number", type="string", nullable=true)
     *     )),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/VendorMarketplaceOrderItem"))),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, description="Shop not active, or order not paid yet", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict")),
     *     @OA\Response(response=404, description="No shop, or not your order item"),
     *     @OA\Response(response=422, description="Validation error, or backward/invalid transition", @OA\JsonContent(ref="#/components/schemas/MarketplaceValidationError"))
     * )
     */
    public function updateFulfillment(Request $request, int $id): JsonResponse
    {
        $vendor = $this->myActiveVendorOrError();
        if ($vendor instanceof JsonResponse) {
            return $vendor;
        }

        $item = $this->find($vendor->id, $id);
        if (! $item) {
            return response()->json(['message' => 'Order item not found'], 404);
        }

        if (! in_array($item->order->status, [Order::STATUS_PAID, Order::STATUS_PROCESSING], true)) {
            return response()->json(['message' => 'This order is not payable/active yet', 'code' => 'ORDER_NOT_ACTIVE'], 403);
        }

        $validator = Validator::make($request->all(), [
            'fulfillment_status' => ['required', Rule::in(['processing', 'shipped', 'delivered'])],
            'tracking_number'    => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $next = $request->input('fulfillment_status');
        $currentRank = array_search($item->fulfillment_status, self::FORWARD_FLOW, true);
        $nextRank = array_search($next, self::FORWARD_FLOW, true);

        if ($nextRank === false || $nextRank <= $currentRank) {
            return response()->json(['errors' => ['fulfillment_status' => ['Cannot move fulfillment status backwards.']]], 422);
        }

        $data = ['fulfillment_status' => $next];
        if ($request->filled('tracking_number')) {
            $data['tracking_number'] = $request->input('tracking_number');
        }
        if ($next === 'shipped') {
            $data['shipped_at'] = now();
        }
        if ($next === 'delivered') {
            $data['delivered_at'] = now();
        }

        $item->update($data);

        app(OrderStatusRollupService::class)->recalculate($item->order);

        return response()->json(['data' => $item->fresh()->load('order:id,order_number,status', 'product:id,name', 'variant')]);
    }

    private function find(int $vendorId, int $id): ?OrderItem
    {
        return OrderItem::where('vendor_id', $vendorId)
            ->whereHas('order', fn ($q) => $q->where('status', '!=', Order::STATUS_PENDING_PAYMENT))
            ->with('order', 'product:id,name,name_ar', 'variant')
            ->find($id);
    }
}
