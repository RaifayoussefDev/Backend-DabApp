<?php

namespace App\Http\Controllers\Admin\Marketplace;

use App\Models\Marketplace\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Admin Marketplace - Orders",
 *     description="Read-only order visibility for support/ops. Every vendor, every status. Access: Admin."
 * )
 */
class OrderAdminController extends MarketplaceAdminController
{
    /**
     * @OA\Get(
     *     path="/api/admin/marketplace/orders",
     *     summary="List orders (Admin)",
     *     tags={"Admin Marketplace - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"pending_payment","paid","processing","shipped","delivered","cancelled","refunded"})),
     *     @OA\Parameter(name="user_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Orders", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdminMarketplaceOrder")),
     *         @OA\Property(property="meta", type="object")
     *     )),
     *     @OA\Response(response=403, description="Not an admin")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $query = Order::query()
            ->with('user:id,first_name,last_name,email', 'items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', (int) $request->input('user_id')))
            ->orderByDesc('id');

        $perPage = $this->perPage($request) ?? 20;

        return response()->json($this->paginated($query->paginate($perPage)));
    }

    /**
     * @OA\Get(
     *     path="/api/admin/marketplace/orders/{id}",
     *     summary="Order detail (Admin)",
     *     tags={"Admin Marketplace - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Order", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceOrder"))),
     *     @OA\Response(response=403, description="Not an admin"),
     *     @OA\Response(response=404, description="Order not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $order = Order::with('user:id,first_name,last_name,email', 'address', 'items.product:id,name', 'items.vendor:id,shop_name', 'payments')->find($id);

        return $order
            ? response()->json(['data' => $order])
            : response()->json(['message' => 'Order not found'], 404);
    }
}
