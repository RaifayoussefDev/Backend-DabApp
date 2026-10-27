<?php

namespace App\Http\Controllers\Marketplace;

use App\Exceptions\Marketplace\InsufficientStockException;
use App\Exceptions\Marketplace\InvalidPromoCodeException;
use App\Http\Controllers\Controller;
use App\Models\CurrencyExchangeRate;
use App\Models\Marketplace\Address;
use App\Models\Marketplace\Cart;
use App\Models\Marketplace\Order;
use App\Models\Marketplace\OrderItem;
use App\Models\Marketplace\OrderPromoCode;
use App\Models\Marketplace\Payment;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\ProductVariant;
use App\Models\Marketplace\Vendor;
use App\Models\Marketplace\VendorShippingMethod;
use App\Services\Marketplace\CommissionResolverService;
use App\Services\Marketplace\PromoCodeResolverService;
use App\Services\PayTabsConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Marketplace - Checkout",
 *     description="Converts the caller's active cart into one Order (split into one OrderItem group per vendor) and starts a PayTabs payment for it. Stock is decremented immediately (the 'reservation') and released automatically if the order is still unpaid 30 minutes later. Access: User (Bearer JWT) only — a guest must log in first."
 * )
 */
class CheckoutController extends Controller
{
    private const RESERVATION_MINUTES = 30;

    public function __construct(
        private CommissionResolverService $commission,
        private PromoCodeResolverService $promoCodes,
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/marketplace/checkout",
     *     summary="Convert my cart into an order and start payment",
     *     description="Send exactly one shipping_methods entry per vendor present in the cart. idempotency_key: any client-generated string (e.g. a UUID) — resending the same key returns the original order instead of creating a second one, safe to retry on a network error. address_id XOR new_address is required.",
     *     tags={"Marketplace - Checkout"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"shipping_methods","idempotency_key"},
     *         @OA\Property(property="address_id", type="integer", nullable=true, example=12),
     *         @OA\Property(property="new_address", type="object", nullable=true,
     *             @OA\Property(property="full_name", type="string"), @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="country_id", type="integer"), @OA\Property(property="city_id", type="integer", nullable=true),
     *             @OA\Property(property="address_line", type="string"), @OA\Property(property="address_line_2", type="string", nullable=true),
     *             @OA\Property(property="postal_code", type="string", nullable=true), @OA\Property(property="save", type="boolean", example=true)),
     *         @OA\Property(property="shipping_methods", type="array", @OA\Items(type="object",
     *             @OA\Property(property="vendor_id", type="integer"), @OA\Property(property="shipping_method_id", type="integer"))),
     *         @OA\Property(property="promo_codes", type="array", nullable=true, description="At most 2: one platform code (DabApp-wide) and one shop code (discounts only that vendor's lines).", @OA\Items(type="string"), example={"MP5"}),
     *         @OA\Property(property="idempotency_key", type="string", example="b2f1c9e0-...")
     *     )),
     *     @OA\Response(response=201, description="Order created, payment started", @OA\JsonContent(
     *         @OA\Property(property="data", ref="#/components/schemas/MarketplaceOrder"),
     *         @OA\Property(property="payment_url", type="string", nullable=true)
     *     )),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=404, description="Address not found"),
     *     @OA\Response(response=409, description="Cart empty or not enough stock", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict")),
     *     @OA\Response(response=422, description="Validation error, or an invalid/expired/inapplicable promo_codes entry (code=INVALID_PROMO_CODE)", @OA\JsonContent(ref="#/components/schemas/MarketplaceValidationError"))
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $userId = Auth::id();

        $validator = Validator::make($request->all(), [
            'address_id'                        => 'nullable|integer|required_without:new_address',
            'new_address'                        => 'nullable|array|required_without:address_id',
            'new_address.full_name'              => 'required_with:new_address|string|max:255',
            'new_address.phone'                   => 'required_with:new_address|string|max:30',
            'new_address.country_id'              => 'required_with:new_address|integer',
            'new_address.city_id'                 => 'nullable|integer',
            'new_address.address_line'            => 'required_with:new_address|string|max:255',
            'new_address.address_line_2'          => 'nullable|string|max:255',
            'new_address.postal_code'             => 'nullable|string|max:20',
            'new_address.save'                    => 'nullable|boolean',
            'shipping_methods'                    => 'required|array|min:1',
            'shipping_methods.*.vendor_id'         => 'required|integer',
            'shipping_methods.*.shipping_method_id' => 'required|integer',
            'promo_codes'                          => 'nullable|array|max:2',
            'promo_codes.*'                        => 'string|max:50',
            'idempotency_key'                     => 'required|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $existing = Order::where('user_id', $userId)->where('idempotency_key', $request->input('idempotency_key'))->first();
        if ($existing) {
            return response()->json([
                'data'        => $existing->load('items', 'promoCodes'),
                'payment_url' => $existing->payments()->latest()->value('payment_url'),
            ], 201);
        }

        $cart = Cart::active()->where('user_id', $userId)->with('items')->first();
        if (! $cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty', 'code' => 'CART_EMPTY'], 409);
        }

        $itemsByVendor = $cart->items->groupBy('vendor_id');
        $methodByVendor = collect($request->input('shipping_methods'))->keyBy('vendor_id');

        if ($itemsByVendor->keys()->sort()->values()->all() !== $methodByVendor->keys()->sort()->values()->all()) {
            return response()->json(['errors' => ['shipping_methods' => ['Send exactly one shipping method per vendor in your cart.']]], 422);
        }

        $shippingMethods = $this->resolveShippingMethods($itemsByVendor->keys(), $methodByVendor);
        if ($shippingMethods instanceof JsonResponse) {
            return $shippingMethods;
        }

        $addressResult = $this->resolveAddress($request, $userId);
        if ($addressResult instanceof JsonResponse) {
            return $addressResult;
        }
        [$addressId, $shippingSnapshot] = $addressResult;

        try {
            $order = DB::transaction(function () use ($cart, $itemsByVendor, $shippingMethods, $userId, $addressId, $shippingSnapshot, $request) {
                return $this->buildOrder($cart, $itemsByVendor, $shippingMethods, $userId, $addressId, $shippingSnapshot, $request->input('idempotency_key'), $request->input('promo_codes', []));
            });
        } catch (InsufficientStockException $e) {
            return response()->json([
                'message'   => $e->getMessage(),
                'code'      => 'INSUFFICIENT_STOCK',
                'product_id' => $e->productId,
                'variant_id' => $e->variantId,
                'available' => $e->available,
            ], 409);
        } catch (InvalidPromoCodeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'INVALID_PROMO_CODE', 'promo_code' => $e->promoCode], 422);
        }

        $paymentUrl = $this->initiatePayTabsPayment($order);

        return response()->json(['data' => $order->load('items', 'promoCodes'), 'payment_url' => $paymentUrl], 201);
    }

    /** Used by OrderController::pay() to retry a failed PayTabs initiation on an already-created order. */
    public function retryPayment(Order $order): ?string
    {
        return $this->initiatePayTabsPayment($order);
    }

    private function resolveCurrencyCountryId(?int $addressCountryId): int
    {
        if ($addressCountryId && CurrencyExchangeRate::where('country_id', $addressCountryId)->exists()) {
            return $addressCountryId;
        }

        return 1; // Saudi Arabia / SAR
    }

    // ------------------------------------------------------------------ internals

    /** @return JsonResponse|\Illuminate\Support\Collection<int,VendorShippingMethod> keyed by vendor_id */
    private function resolveShippingMethods($vendorIds, $methodByVendor): JsonResponse|\Illuminate\Support\Collection
    {
        $resolved = collect();

        foreach ($vendorIds as $vendorId) {
            $method = VendorShippingMethod::active()
                ->where('vendor_id', $vendorId)
                ->find($methodByVendor[$vendorId]['shipping_method_id']);

            if (! $method) {
                return response()->json(['errors' => ['shipping_methods' => ["No active shipping method {$methodByVendor[$vendorId]['shipping_method_id']} for vendor {$vendorId}."]]], 422);
            }

            $resolved->put($vendorId, $method);
        }

        return $resolved;
    }

    private function resolveAddress(Request $request, int $userId): JsonResponse|array
    {
        if ($request->filled('address_id')) {
            $address = Address::where('user_id', $userId)->find($request->input('address_id'));
            if (! $address) {
                return response()->json(['message' => 'Address not found'], 404);
            }
        } else {
            $data = $request->input('new_address');
            $address = Address::create([
                'user_id'        => $userId,
                'full_name'      => $data['full_name'],
                'phone'          => $data['phone'],
                'country_id'     => $data['country_id'],
                'city_id'        => $data['city_id'] ?? null,
                'address_line'   => $data['address_line'],
                'address_line_2' => $data['address_line_2'] ?? null,
                'postal_code'    => $data['postal_code'] ?? null,
            ]);

            if (empty($data['save'])) {
                // Still a real row (address_id FK needs one) — just not meant to linger in the
                // book the customer sees; the order's own shipping_address snapshot is what lasts.
                $address->delete();
            }
        }

        $address->loadMissing('country:id,name', 'city:id,name');

        $snapshot = [
            'full_name'      => $address->full_name,
            'phone'          => $address->phone,
            'country'        => $address->country?->name,
            'country_id'     => $address->country_id,
            'city'           => $address->city?->name,
            'city_id'        => $address->city_id,
            'address_line'   => $address->address_line,
            'address_line_2' => $address->address_line_2,
            'postal_code'    => $address->postal_code,
        ];

        return [$address->id, $snapshot];
    }

    private function buildOrder($cart, $itemsByVendor, $shippingMethods, int $userId, ?int $addressId, array $shippingSnapshot, string $idempotencyKey, array $promoCodes): Order
    {
        $productIds = $cart->items->whereNull('variant_id')->pluck('product_id')->unique()->sort()->values();
        $variantIds = $cart->items->whereNotNull('variant_id')->pluck('variant_id')->unique()->sort()->values();

        $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');
        $variants = ProductVariant::whereIn('id', $variantIds)->with('product')->lockForUpdate()->get()->keyBy('id');

        foreach ($cart->items as $cartItem) {
            $locked = $cartItem->variant_id ? $variants->get($cartItem->variant_id) : $products->get($cartItem->product_id);
            $label = $cartItem->variant_id ? ($locked?->sku ?? 'variant') : ($locked?->name ?? 'product');

            if (! $locked || $locked->stock_quantity < $cartItem->quantity) {
                throw new InsufficientStockException($cartItem->product_id, $cartItem->variant_id, $label, $cartItem->quantity, $locked?->stock_quantity ?? 0);
            }
        }

        foreach ($cart->items as $cartItem) {
            $locked = $cartItem->variant_id ? $variants->get($cartItem->variant_id) : $products->get($cartItem->product_id);
            $locked->decrement('stock_quantity', $cartItem->quantity);
        }

        $currencyCountryId = $this->resolveCurrencyCountryId($shippingSnapshot['country_id'] ?? null);
        $currency = CurrencyExchangeRate::where('country_id', $currencyCountryId)->value('currency_code') ?? 'SAR';

        $order = Order::create([
            'order_number'     => Order::generateOrderNumber(),
            'user_id'          => $userId,
            'address_id'       => $addressId,
            'shipping_address' => $shippingSnapshot,
            'subtotal'         => 0,
            'shipping_fee'     => 0,
            'discount'         => 0,
            'total'            => 0,
            'currency'         => $currency,
            'status'           => Order::STATUS_PENDING_PAYMENT,
            'idempotency_key'  => $idempotencyKey,
            'expires_at'       => now()->addMinutes(self::RESERVATION_MINUTES),
        ]);

        $lineTotals = [];
        $vendorSubtotals = [];
        $cartSubtotal = 0.0;
        foreach ($itemsByVendor as $vendorId => $cartItems) {
            foreach ($cartItems as $cartItem) {
                $lineTotal = round(((float) $cartItem->unit_price_snapshot) * $cartItem->quantity, 2);
                $lineTotals[$cartItem->id] = $lineTotal;
                $vendorSubtotals[$vendorId] = ($vendorSubtotals[$vendorId] ?? 0) + $lineTotal;
                $cartSubtotal += $lineTotal;
            }
        }

        $appliedPromos = $this->promoCodes->resolve($promoCodes, $userId, $cartSubtotal, $vendorSubtotals);

        $vendorFundedByItem = [];
        $platformFundedByItem = [];
        foreach ($appliedPromos as $applied) {
            $isVendorFunded = $applied['vendor_id'] !== null;
            $scopeItemIds = $isVendorFunded
                ? $itemsByVendor->get($applied['vendor_id'], collect())->pluck('id')->all()
                : array_keys($lineTotals);

            $remaining = $applied['discount'];
            $count = count($scopeItemIds);
            foreach ($scopeItemIds as $i => $itemId) {
                $share = ($i === $count - 1)
                    ? $remaining
                    : round($applied['discount'] * ($lineTotals[$itemId] / $applied['scope_subtotal']), 2);

                if ($isVendorFunded) {
                    $vendorFundedByItem[$itemId] = ($vendorFundedByItem[$itemId] ?? 0) + $share;
                } else {
                    $platformFundedByItem[$itemId] = ($platformFundedByItem[$itemId] ?? 0) + $share;
                }
                $remaining = round($remaining - $share, 2);
            }
        }

        $subtotal = 0.0;
        $shippingFeeTotal = 0.0;
        $discountTotal = 0.0;
        $destinationCityId = $shippingSnapshot['city_id'] ?? null;

        foreach ($itemsByVendor as $vendorId => $cartItems) {
            $vendor = Vendor::findOrFail($vendorId);
            $shippingMethod = $shippingMethods->get($vendorId);
            $vendorShippingRate = $shippingMethod->rateFor($destinationCityId, $vendor->city_id);

            $isFirstLineForVendor = true;

            foreach ($cartItems as $cartItem) {
                $variant = $cartItem->variant_id ? $variants->get($cartItem->variant_id) : null;
                $product = $variant ? $variant->product : $products->get($cartItem->product_id);

                $unitPrice = (float) $cartItem->unit_price_snapshot;
                $lineTotal = $lineTotals[$cartItem->id];
                $lineShippingFee = $isFirstLineForVendor ? $vendorShippingRate : 0.0;
                $isFirstLineForVendor = false;

                $vendorDiscount = $vendorFundedByItem[$cartItem->id] ?? 0.0;
                $platformDiscount = $platformFundedByItem[$cartItem->id] ?? 0.0;
                $lineDiscount = round($vendorDiscount + $platformDiscount, 2);
                $commissionBase = round($lineTotal - $vendorDiscount, 2);

                $commission = $this->commission->resolve($vendor, $product?->category_id, $commissionBase);

                OrderItem::create([
                    'order_id'            => $order->id,
                    'product_id'          => $cartItem->product_id,
                    'variant_id'          => $cartItem->variant_id,
                    'vendor_id'           => $vendorId,
                    'shipping_method_id'  => $shippingMethod->id,
                    'product_name'        => $product?->name,
                    'product_reference'   => $product?->reference,
                    'variant_sku'         => $variant?->sku,
                    'variant_label'       => $variant ? trim(($variant->option1_value ?? '') . ' ' . ($variant->option2_value ?? '')) : null,
                    'quantity'            => $cartItem->quantity,
                    'unit_price'          => $unitPrice,
                    'total_price'         => $lineTotal,
                    'shipping_fee'        => $lineShippingFee,
                    'discount_amount'     => $lineDiscount,
                    'commission_rate'     => $commission['rate'],
                    'commission_amount'   => $commission['amount'],
                    'vendor_net'          => $commission['net'] + $lineShippingFee,
                    'fulfillment_status'  => 'pending',
                ]);

                $subtotal += $lineTotal;
                $shippingFeeTotal += $lineShippingFee;
                $discountTotal += $lineDiscount;
            }
        }

        foreach ($appliedPromos as $applied) {
            OrderPromoCode::create([
                'order_id'       => $order->id,
                'promo_code_id'  => $applied['promo']->id,
                'vendor_id'      => $applied['vendor_id'],
                'code'           => $applied['promo']->code,
                'discount_amount' => $applied['discount'],
            ]);
        }

        $order->update([
            'subtotal'     => round($subtotal, 2),
            'shipping_fee' => round($shippingFeeTotal, 2),
            'discount'     => round($discountTotal, 2),
            'total'        => round($subtotal + $shippingFeeTotal - $discountTotal, 2),
        ]);

        $cart->update(['status' => 'converted']);

        return $order->fresh();
    }

    private function initiatePayTabsPayment(Order $order): ?string
    {
        $config = PayTabsConfigService::getConfig();
        $order->loadMissing('user');

        $payment = Payment::create([
            'order_id'       => $order->id,
            'gateway'        => 'paytabs',
            'amount'         => $order->total,
            'currency'       => $order->currency,
            'payment_status' => 'pending',
            'cart_id'        => 'order_' . $order->order_number . '_' . time(),
            'expires_at'     => $order->expires_at,
        ]);

        $shipping = $order->shipping_address ?? [];
        $buyerCountryId = $this->resolveCurrencyCountryId($shipping['country_id'] ?? null);

        $paymentData = [
            'profile_id'       => $config['profile_id'],
            'tran_type'        => 'sale',
            'tran_class'       => 'ecom',
            'cart_id'          => $payment->cart_id,
            'cart_description' => 'Order ' . $order->order_number,
            'cart_currency'    => $config['currency'],
            'cart_amount'      => PayTabsConfigService::convertToSettlementCurrency((float) $order->total, $buyerCountryId),
            'callback'         => config('app.url') . '/api/marketplace/orders/paytabs/callback',
            'return'           => config('app.url') . '/api/marketplace/orders/paytabs/return',
            'customer_details' => [
                'name'    => $shipping['full_name'] ?? $order->user?->name ?? 'N/A',
                'email'   => $order->user?->email ?? 'N/A',
                'phone'   => $shipping['phone'] ?? 'N/A',
                'street1' => $shipping['address_line'] ?? 'N/A',
                'city'    => $shipping['city'] ?? 'N/A',
                'state'   => $shipping['city'] ?? 'N/A',
                'country' => $config['region'],
                'zip'     => $shipping['postal_code'] ?? '00000',
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => $config['server_key'],
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])->post(PayTabsConfigService::getBaseUrl() . 'payment/request', $paymentData);

            if ($response->successful()) {
                $data = $response->json();
                $payment->update(['tran_ref' => $data['tran_ref'] ?? null, 'payment_url' => $data['redirect_url'] ?? null]);

                return $data['redirect_url'] ?? null;
            }

            Log::error('Marketplace checkout: PayTabs payment/request failed', ['order_id' => $order->id, 'response' => $response->json()]);
            $payment->update(['payment_status' => 'failed', 'failure_reason' => 'PayTabs payment/request failed']);
        } catch (\Throwable $e) {
            Log::error('Marketplace checkout: PayTabs payment/request exception', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            $payment->update(['payment_status' => 'failed', 'failure_reason' => $e->getMessage()]);
        }

        return null;
    }
}
