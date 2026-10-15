<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\Cart;
use App\Models\Marketplace\CartItem;
use App\Models\Marketplace\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Marketplace - Cart",
 *     description="Works for both guests and logged-in users (mode invité): send a Bearer token to use the account's cart, or an X-Cart-Session header (any client-generated string, e.g. a UUID you keep in local storage) to use a guest cart - never both resolved at once, the token wins if both are sent. A guest who then logs in should call POST /cart/merge once with their old session id to fold it into the account's cart. No stock is reserved by the cart itself - that happens when the order is created (see Checkout); adding to cart only checks that enough stock exists right now."
 * )
 */
class CartController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/marketplace/cart",
     *     summary="View my cart",
     *     description="Empty cart shape (items: []) when nothing was ever added - never a 404. Access: Public (guest via X-Cart-Session) or User.",
     *     tags={"Marketplace - Cart"},
     *     @OA\Parameter(name="X-Cart-Session", in="header", required=false, description="Guest only, ignored for a logged-in user", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Cart", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/MarketplaceCart")))
     * )
     */
    public function show(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);

        return response()->json(['data' => $cart ? $this->present($cart) : $this->emptyCart()]);
    }

    /**
     * @OA\Post(
     *     path="/api/marketplace/cart/items",
     *     summary="Add an item to the cart",
     *     description="variant_id is required when the product has variants (color/size...), refused otherwise (422) - check has_variants on the product first. Adding a product/variant already in the cart increases its quantity instead of creating a second line. Access: Public (guest via X-Cart-Session) or User.",
     *     tags={"Marketplace - Cart"},
     *     @OA\Parameter(name="X-Cart-Session", in="header", required=false, description="Required for a guest (no Bearer token) - any client-generated string", @OA\Schema(type="string", example="a1b2c3d4-guest-session")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"product_id"},
     *         @OA\Property(property="product_id", type="integer", example=21),
     *         @OA\Property(property="variant_id", type="integer", nullable=true, example=1, description="Required when the product has variants"),
     *         @OA\Property(property="quantity", type="integer", example=1, default=1)
     *     )),
     *     @OA\Response(response=201, description="Added", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/MarketplaceCart"))),
     *     @OA\Response(response=400, description="Guest with no X-Cart-Session header", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict")),
     *     @OA\Response(response=404, description="Product or variant not found"),
     *     @OA\Response(response=409, description="Not enough stock", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="code", type="string", example="INSUFFICIENT_STOCK"), @OA\Property(property="available", type="integer"))),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/MarketplaceValidationError"))
     * )
     */
    public function addItem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer',
            'variant_id' => 'nullable|integer',
            'quantity'   => 'nullable|integer|min:1|max:99',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product = Product::visible()->with('variants')->find($request->input('product_id'));
        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $hasVariants = $product->variants->isNotEmpty();
        $variant = null;
        if ($hasVariants) {
            if (! $request->filled('variant_id')) {
                return response()->json(['errors' => ['variant_id' => ['This product is sold by variant: variant_id is required.']]], 422);
            }
            $variant = $product->variants->firstWhere('id', (int) $request->input('variant_id'));
            if (! $variant) {
                return response()->json(['message' => 'Variant not found'], 404);
            }
        } elseif ($request->filled('variant_id')) {
            return response()->json(['errors' => ['variant_id' => ['This product has no variants.']]], 422);
        }

        $cart = $this->resolveCart($request, createIfMissing: true);
        if (! $cart) {
            return $this->missingCartSession();
        }

        $quantity = (int) $request->input('quantity', 1);
        $existing = $cart->items()->where('product_id', $product->id)->where('variant_id', $variant?->id)->first();
        $wantedQuantity = ($existing->quantity ?? 0) + $quantity;
        $available = $variant ? $variant->stock_quantity : $product->stock_quantity;

        if ($wantedQuantity > $available) {
            return response()->json(['message' => 'Not enough stock for this quantity', 'code' => 'INSUFFICIENT_STOCK', 'available' => $available], 409);
        }

        if ($existing) {
            $existing->update(['quantity' => $wantedQuantity]);
        } else {
            $cart->items()->create([
                'product_id'           => $product->id,
                'variant_id'           => $variant?->id,
                'vendor_id'            => $product->vendor_id,
                'quantity'             => $quantity,
                'unit_price_snapshot'  => $variant ? $variant->price : $product->price,
            ]);
        }
        $cart->update(['last_activity_at' => now()]);

        return response()->json(['data' => $this->present($cart->fresh())], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/marketplace/cart/items/{id}",
     *     summary="Change an item's quantity",
     *     tags={"Marketplace - Cart"},
     *     @OA\Parameter(name="X-Cart-Session", in="header", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"quantity"}, @OA\Property(property="quantity", type="integer", example=2))),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/MarketplaceCart"))),
     *     @OA\Response(response=404, description="No cart, or not this cart's item"),
     *     @OA\Response(response=409, description="Not enough stock", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="code", type="string", example="INSUFFICIENT_STOCK"), @OA\Property(property="available", type="integer"))),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/MarketplaceValidationError"))
     * )
     */
    public function updateItem(Request $request, int $id): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $item = $cart?->items()->find($id);
        if (! $item) {
            return response()->json(['message' => 'Cart item not found'], 404);
        }

        $validator = Validator::make($request->all(), ['quantity' => 'required|integer|min:1|max:99']);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $available = $item->variant ? $item->variant->stock_quantity : $item->product?->stock_quantity ?? 0;
        $quantity = (int) $request->input('quantity');
        if ($quantity > $available) {
            return response()->json(['message' => 'Not enough stock for this quantity', 'code' => 'INSUFFICIENT_STOCK', 'available' => $available], 409);
        }

        $item->update(['quantity' => $quantity]);
        $cart->update(['last_activity_at' => now()]);

        return response()->json(['data' => $this->present($cart->fresh())]);
    }

    /**
     * @OA\Delete(
     *     path="/api/marketplace/cart/items/{id}",
     *     summary="Remove an item from the cart",
     *     tags={"Marketplace - Cart"},
     *     @OA\Parameter(name="X-Cart-Session", in="header", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Removed", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/MarketplaceCart"))),
     *     @OA\Response(response=404, description="No cart, or not this cart's item")
     * )
     */
    public function removeItem(Request $request, int $id): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $item = $cart?->items()->find($id);
        if (! $item) {
            return response()->json(['message' => 'Cart item not found'], 404);
        }

        $item->delete();
        $cart->update(['last_activity_at' => now()]);

        return response()->json(['data' => $this->present($cart->fresh())]);
    }

    /**
     * @OA\Delete(
     *     path="/api/marketplace/cart",
     *     summary="Clear the cart",
     *     description="Removes every item, keeps the cart itself (empty cart shape afterwards, never 404).",
     *     tags={"Marketplace - Cart"},
     *     @OA\Parameter(name="X-Cart-Session", in="header", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Cleared", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/MarketplaceCart")))
     * )
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        if ($cart) {
            $cart->items()->delete();
            $cart->update(['last_activity_at' => now()]);
        }

        return response()->json(['data' => $cart ? $this->present($cart->fresh()) : $this->emptyCart()]);
    }

    /**
     * @OA\Post(
     *     path="/api/marketplace/cart/merge",
     *     summary="Merge a guest cart into my account on login",
     *     description="Call this once right after login, passing the X-Cart-Session value the app was using as a guest. Matching product/variant lines are added together (capped to available stock); the rest are moved over. The guest cart is marked converted and emptied. Safe to call with no guest cart to merge - just returns your account cart. Access: User.",
     *     tags={"Marketplace - Cart"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"session_id"}, @OA\Property(property="session_id", type="string", example="a1b2c3d4-guest-session"))),
     *     @OA\Response(response=200, description="Merged", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/MarketplaceCart"))),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/MarketplaceValidationError"))
     * )
     */
    public function merge(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['session_id' => 'required|string|max:64']);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = auth('api')->id();
        $userCart = Cart::active()->where('user_id', $userId)->first()
            ?? Cart::create(['user_id' => $userId, 'status' => 'active', 'last_activity_at' => now()]);

        $guestCart = Cart::active()->whereNull('user_id')->where('session_id', $request->input('session_id'))->first();

        if ($guestCart) {
            foreach ($guestCart->items as $guestItem) {
                $existing = $userCart->items()->where('product_id', $guestItem->product_id)->where('variant_id', $guestItem->variant_id)->first();
                $available = $guestItem->variant ? $guestItem->variant->stock_quantity : $guestItem->product?->stock_quantity ?? 0;

                if ($existing) {
                    $existing->update(['quantity' => min($available, $existing->quantity + $guestItem->quantity)]);
                } else {
                    $userCart->items()->create([
                        'product_id'          => $guestItem->product_id,
                        'variant_id'          => $guestItem->variant_id,
                        'vendor_id'           => $guestItem->vendor_id,
                        'quantity'            => min($available, $guestItem->quantity),
                        'unit_price_snapshot' => $guestItem->unit_price_snapshot,
                    ]);
                }
            }

            $guestCart->items()->delete();
            $guestCart->update(['status' => 'converted']);
        }

        $userCart->update(['last_activity_at' => now()]);

        return response()->json(['data' => $this->present($userCart->fresh())]);
    }

    // ------------------------------------------------------------------ internals

    /**
     * auth('api') is used on purpose, not the bare Auth facade: the auth.optional middleware
     * (OptionalJwtAuth) authenticates the api guard directly without switching Laravel's default
     * guard, so Auth::user() would silently miss a logged-in caller under that middleware.
     */
    private function resolveCart(Request $request, bool $createIfMissing = false): ?Cart
    {
        if ($user = auth('api')->user()) {
            $cart = Cart::active()->where('user_id', $user->id)->first();

            return $cart ?? ($createIfMissing ? Cart::create(['user_id' => $user->id, 'status' => 'active', 'last_activity_at' => now()]) : null);
        }

        $sessionId = $request->header('X-Cart-Session');
        if (! $sessionId) {
            return null;
        }

        $cart = Cart::active()->whereNull('user_id')->where('session_id', $sessionId)->first();

        return $cart ?? ($createIfMissing ? Cart::create(['session_id' => $sessionId, 'status' => 'active', 'last_activity_at' => now()]) : null);
    }

    private function missingCartSession(): JsonResponse
    {
        return response()->json(['message' => 'Send a Bearer token, or an X-Cart-Session header to use a guest cart', 'code' => 'MISSING_CART_SESSION'], 400);
    }

    private function emptyCart(): array
    {
        return ['id' => null, 'items' => [], 'items_count' => 0, 'subtotal' => 0, 'updated_at' => null];
    }

    private function present(Cart $cart): array
    {
        $cart->load(['items.product.vendor:id,slug,shop_name', 'items.product.coverImage', 'items.variant']);

        $items = $cart->items->map(function (CartItem $item) {
            $product = $item->product;
            $variant = $item->variant;
            $currentPrice = $variant ? (float) $variant->price : ($product ? (float) $product->price : null);
            $stock = $variant ? $variant->stock_quantity : ($product->stock_quantity ?? 0);
            $available = $product && $product->status === 'active' && $product->vendor?->status === 'active';
            $lineTotal = round($item->quantity * (float) $item->unit_price_snapshot, 2);

            return [
                'id'      => $item->id,
                'product' => $product ? [
                    'id' => $product->id, 'slug' => $product->slug, 'name' => $product->name, 'name_ar' => $product->name_ar,
                    'cover_image_url' => $product->coverImage?->image_url,
                    'vendor' => $product->vendor ? ['id' => $product->vendor->id, 'slug' => $product->vendor->slug, 'shop_name' => $product->vendor->shop_name] : null,
                ] : null,
                'variant' => $variant ? [
                    'id' => $variant->id, 'sku' => $variant->sku,
                    'option1_name' => $variant->option1_name, 'option1_value' => $variant->option1_value, 'color_hex' => $variant->color_hex,
                    'option2_name' => $variant->option2_name, 'option2_value' => $variant->option2_value,
                    'image_url' => $variant->image_url,
                ] : null,
                'quantity'      => $item->quantity,
                'unit_price'    => (float) $item->unit_price_snapshot,
                'current_price' => $currentPrice,
                'price_changed' => $currentPrice !== null && $currentPrice !== (float) $item->unit_price_snapshot,
                'line_total'    => $lineTotal,
                'available'     => (bool) $available,
                'in_stock'      => $stock > 0,
                'max_quantity'  => $stock,
            ];
        })->values();

        return [
            'id'          => $cart->id,
            'items'       => $items,
            'items_count' => (int) $items->sum('quantity'),
            'subtotal'    => round((float) $items->sum('line_total'), 2),
            'updated_at'  => $cart->updated_at?->toIso8601String(),
        ];
    }
}
