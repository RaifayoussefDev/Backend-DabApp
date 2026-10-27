<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;

/**
 * Shared schemas and reusable responses of the marketplace storefront documentation
 * (the Info block and the auth scheme are in MarketplaceApiInfo). UI: /marketplace/documentation
 *
 * @OA\Response(
 *     response="Unauthenticated",
 *     description="Guest, or the token expired. Open the login screen, then repeat the call.",
 *     @OA\JsonContent(example={"success": false, "error": "Unauthenticated", "message": "You must be authenticated to access this resource.", "requires_auth": true, "action": "login"})
 * )
 *
 * @OA\Response(
 *     response="ValidationFailed",
 *     description="Invalid input. Show each message under its field.",
 *     @OA\JsonContent(example={"errors": {"phone": {"The phone field is required."}, "city_id": {"The selected city id is invalid."}}})
 * )
 *
 * @OA\Schema(
 *     schema="MarketplaceCategory",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=12),
 *     @OA\Property(property="parent_id", type="integer", nullable=true, example=3),
 *     @OA\Property(property="slug", type="string", example="tyres"),
 *     @OA\Property(property="name", type="string", example="Tyres"),
 *     @OA\Property(property="name_ar", type="string", nullable=true, example="إطارات"),
 *     @OA\Property(property="image_url", type="string", nullable=true),
 *     @OA\Property(property="children_count", type="integer", example=3),
 *     @OA\Property(property="products_count", type="integer", description="Visible products in this category and all its sub-categories", example=24),
 *     @OA\Property(property="children", type="array", description="Only when tree=1", @OA\Items(ref="#/components/schemas/MarketplaceCategory"))
 * )
 *
 * @OA\Schema(
 *     schema="MarketplaceVendorCard",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=7),
 *     @OA\Property(property="slug", type="string", example="moto-parts-riyadh"),
 *     @OA\Property(property="shop_name", type="string", example="Moto Parts Riyadh"),
 *     @OA\Property(property="shop_name_ar", type="string", nullable=true),
 *     @OA\Property(property="logo_url", type="string", nullable=true),
 *     @OA\Property(property="cover_image_url", type="string", nullable=true),
 *     @OA\Property(property="brand_color", type="string", nullable=true, example="#E63946"),
 *     @OA\Property(property="is_featured", type="boolean", description="Curated by an admin: the Featured badge on All Shops and eligible for the Marketplace home Shops row", example=false),
 *     @OA\Property(property="rating_avg", type="number", format="float", example=4.6),
 *     @OA\Property(property="reviews_count", type="integer", example=23),
 *     @OA\Property(property="products_count", type="integer", description="Active products of the shop", example=128),
 *     @OA\Property(property="city", type="object", nullable=true,
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Riyadh"))
 * )
 *
 * @OA\Schema(
 *     schema="MarketplaceMyVendor",
 *     type="object",
 *     description="The owner's view of their own shop (any status).",
 *     @OA\Property(property="id", type="integer", example=7),
 *     @OA\Property(property="slug", type="string", example="moto-parts-riyadh"),
 *     @OA\Property(property="shop_name", type="string"),
 *     @OA\Property(property="shop_name_ar", type="string", nullable=true),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="description_ar", type="string", nullable=true),
 *     @OA\Property(property="logo_url", type="string", nullable=true),
 *     @OA\Property(property="cover_image_url", type="string", nullable=true),
 *     @OA\Property(property="brand_color", type="string", nullable=true, example="#E63946"),
 *     @OA\Property(property="phone", type="string", nullable=true),
 *     @OA\Property(property="email", type="string", nullable=true),
 *     @OA\Property(property="country", type="object", nullable=true, @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string")),
 *     @OA\Property(property="city", type="object", nullable=true, @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string")),
 *     @OA\Property(property="address", type="string", nullable=true, example="King Fahd Rd, Al Olaya"),
 *     @OA\Property(property="latitude", type="number", format="float", nullable=true, example=24.7136),
 *     @OA\Property(property="longitude", type="number", format="float", nullable=true, example=46.6753),
 *     @OA\Property(property="status", type="string", enum={"pending","active","suspended","rejected"}, example="pending"),
 *     @OA\Property(property="status_reason", type="string", nullable=true, description="Why it was rejected or suspended"),
 *     @OA\Property(property="can_sell", type="boolean", description="true only when status is active", example=false),
 *     @OA\Property(property="approved_at", type="string", nullable=true),
 *     @OA\Property(property="bank_name", type="string", nullable=true),
 *     @OA\Property(property="iban", type="string", nullable=true),
 *     @OA\Property(property="rating_avg", type="number", example=0),
 *     @OA\Property(property="reviews_count", type="integer", example=0),
 *     @OA\Property(property="products_count", type="integer", example=0),
 *     @OA\Property(property="created_at", type="string")
 * )
 *
 * @OA\Schema(
 *     schema="VendorMarketplaceShippingMethod",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=3),
 *     @OA\Property(property="vendor_id", type="integer", example=7),
 *     @OA\Property(property="provider_id", type="integer", example=1),
 *     @OA\Property(property="provider", type="object", @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string", example="DHL"), @OA\Property(property="code", type="string", example="dhl")),
 *     @OA\Property(property="uses_own_api", type="boolean", example=false),
 *     @OA\Property(property="has_credentials", type="boolean", description="Keys are write-only: only this flag is ever returned", example=false),
 *     @OA\Property(property="flat_rate", type="number", example=35, description="Rest-of-the-country rate"),
 *     @OA\Property(property="local_rate", type="number", nullable=true, example=15, description="Applied instead of flat_rate when the order ships to the vendor's own city"),
 *     @OA\Property(property="estimated_days_min", type="integer", nullable=true, example=2),
 *     @OA\Property(property="estimated_days_max", type="integer", nullable=true, example=4),
 *     @OA\Property(property="is_active", type="boolean", example=true)
 * )
 *
 * @OA\Schema(
 *     schema="MarketplaceAddress",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=31),
 *     @OA\Property(property="user_id", type="integer", example=65),
 *     @OA\Property(property="full_name", type="string", example="Khalid Al-Otaibi"),
 *     @OA\Property(property="phone", type="string", example="+966500000000"),
 *     @OA\Property(property="country_id", type="integer", example=1),
 *     @OA\Property(property="city_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="address_line", type="string", example="King Fahd Road, Building 12"),
 *     @OA\Property(property="address_line_2", type="string", nullable=true),
 *     @OA\Property(property="postal_code", type="string", nullable=true, example="12345"),
 *     @OA\Property(property="is_default", type="boolean", example=true),
 *     @OA\Property(property="country", type="object", nullable=true, @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string", example="Saudi Arabia")),
 *     @OA\Property(property="city", type="object", nullable=true, @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string", example="Riyadh"), @OA\Property(property="name_ar", type="string", nullable=true))
 * )
 *
 * @OA\Schema(
 *     schema="MarketplaceOrderItem",
 *     type="object",
 *     description="One product/variant line, frozen at checkout time - name/price/commission never change afterwards even if the product does.",
 *     @OA\Property(property="id", type="integer", example=116),
 *     @OA\Property(property="order_id", type="integer", example=113),
 *     @OA\Property(property="product_id", type="integer", example=19),
 *     @OA\Property(property="variant_id", type="integer", nullable=true, example=71),
 *     @OA\Property(property="vendor_id", type="integer", example=4),
 *     @OA\Property(property="shipping_method_id", type="integer", nullable=true, example=7),
 *     @OA\Property(property="product_name", type="string", example="Michelin Pilot Road 5 120/70 ZR17", description="Snapshot - survives the product being edited or deleted"),
 *     @OA\Property(property="product_reference", type="string", nullable=true, example="DEMO-001"),
 *     @OA\Property(property="variant_sku", type="string", nullable=true, example="DEMO-001-1"),
 *     @OA\Property(property="variant_label", type="string", nullable=true, example="Front 120/70-17"),
 *     @OA\Property(property="quantity", type="integer", example=2),
 *     @OA\Property(property="unit_price", type="number", example=649),
 *     @OA\Property(property="total_price", type="number", example=1298),
 *     @OA\Property(property="shipping_fee", type="number", example=35, description="Only non-zero on one line per vendor group - the vendor's flat/local rate, charged once"),
 *     @OA\Property(property="discount_amount", type="number", example=97.35, description="This line's share of every applied promo code (platform + shop combined)"),
 *     @OA\Property(property="commission_rate", type="number", example=15, description="% frozen at order time: vendor override > category > global"),
 *     @OA\Property(property="commission_amount", type="number", example=87.62, description="Computed on total_price minus only the shop-funded discount share - a platform-funded discount comes out of DabApp's margin, not the vendor's commission base"),
 *     @OA\Property(property="vendor_net", type="number", example=531.48, description="(total_price - shop-funded discount - commission_amount) + shipping_fee (shipping is never commissioned or discounted)"),
 *     @OA\Property(property="payout_id", type="integer", nullable=true, description="Not wired up yet - vendor payouts are a separate future feature"),
 *     @OA\Property(property="fulfillment_status", type="string", enum={"pending","processing","shipped","delivered","cancelled"}, example="pending"),
 *     @OA\Property(property="tracking_number", type="string", nullable=true),
 *     @OA\Property(property="shipped_at", type="string", nullable=true),
 *     @OA\Property(property="delivered_at", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="VendorMarketplaceOrderItem",
 *     type="object",
 *     description="Same fields as MarketplaceOrderItem, with the parent order's own status/number and the product/variant names attached - this is what a vendor sees when preparing an item.",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/MarketplaceOrderItem"),
 *         @OA\Schema(
 *             @OA\Property(property="order", type="object", nullable=true,
 *                 @OA\Property(property="id", type="integer"), @OA\Property(property="order_number", type="string", example="DAB-261023-0001"),
 *                 @OA\Property(property="status", type="string", example="paid"), @OA\Property(property="created_at", type="string")),
 *             @OA\Property(property="product", type="object", nullable=true, @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string")),
 *             @OA\Property(property="variant", type="object", nullable=true, @OA\Property(property="id", type="integer"), @OA\Property(property="sku", type="string"))
 *         )
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="MarketplacePayment",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=6),
 *     @OA\Property(property="order_id", type="integer", example=113),
 *     @OA\Property(property="gateway", type="string", example="paytabs"),
 *     @OA\Property(property="amount", type="number", example=2062),
 *     @OA\Property(property="currency", type="string", example="AED"),
 *     @OA\Property(property="payment_status", type="string", enum={"pending","completed","failed","refunded"}, example="completed"),
 *     @OA\Property(property="cart_id", type="string", nullable=true, example="order_DAB-261023-0001_1792748009", description="PayTabs-side reference, unrelated to the shopping Cart"),
 *     @OA\Property(property="tran_ref", type="string", nullable=true),
 *     @OA\Property(property="payment_url", type="string", nullable=true, description="Open in a browser/WebView to complete payment"),
 *     @OA\Property(property="expires_at", type="string", nullable=true, description="Matches the order's own expires_at - the 30-minute reservation window"),
 *     @OA\Property(property="paid_at", type="string", nullable=true),
 *     @OA\Property(property="failure_reason", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="MarketplaceOrder",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=113),
 *     @OA\Property(property="order_number", type="string", example="DAB-261023-0001", description="Sortable, human-readable - show this to the customer, not the numeric id"),
 *     @OA\Property(property="user_id", type="integer", example=65),
 *     @OA\Property(property="address_id", type="integer", nullable=true, example=7),
 *     @OA\Property(property="shipping_address", type="object", description="Snapshot taken at checkout - survives the address being edited or deleted afterwards",
 *         @OA\Property(property="full_name", type="string"), @OA\Property(property="phone", type="string"),
 *         @OA\Property(property="country", type="string", nullable=true), @OA\Property(property="city", type="string", nullable=true), @OA\Property(property="city_id", type="integer", nullable=true),
 *         @OA\Property(property="address_line", type="string"), @OA\Property(property="address_line_2", type="string", nullable=true), @OA\Property(property="postal_code", type="string", nullable=true)),
 *     @OA\Property(property="subtotal", type="number", example=649),
 *     @OA\Property(property="shipping_fee", type="number", example=35),
 *     @OA\Property(property="discount", type="number", example=97.35, description="Sum of every applied promo code's discount_amount across all lines (see promo_codes below)"),
 *     @OA\Property(property="total", type="number", example=586.65, description="subtotal + shipping_fee - discount"),
 *     @OA\Property(property="currency", type="string", example="SAR", description="Follows the buyer's own checkout-address country (falls back to SAR if unknown), not a fixed value - PayTabs itself still always settles in its own merchant currency underneath"),
 *     @OA\Property(property="status", type="string", enum={"pending_payment","paid","processing","shipped","delivered","cancelled","refunded"}, example="pending_payment"),
 *     @OA\Property(property="idempotency_key", type="string", nullable=true),
 *     @OA\Property(property="expires_at", type="string", nullable=true, description="Pay before this, or the order auto-cancels and stock is released (30 min after creation)"),
 *     @OA\Property(property="paid_at", type="string", nullable=true),
 *     @OA\Property(property="cancelled_at", type="string", nullable=true),
 *     @OA\Property(property="cancel_reason", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string"),
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/MarketplaceOrderItem")),
 *     @OA\Property(property="promo_codes", type="array", description="One row per applied code (at most one platform + one shop code)", @OA\Items(ref="#/components/schemas/MarketplaceOrderPromoCode")),
 *     @OA\Property(property="payments", type="array", description="Only on the detail call", @OA\Items(ref="#/components/schemas/MarketplacePayment"))
 * )
 *
 * @OA\Schema(
 *     schema="MarketplaceOrderPromoCode",
 *     type="object",
 *     description="One applied promo code, snapshotted at checkout time.",
 *     @OA\Property(property="id", type="integer", example=12),
 *     @OA\Property(property="order_id", type="integer", example=134),
 *     @OA\Property(property="promo_code_id", type="integer", example=3),
 *     @OA\Property(property="vendor_id", type="integer", nullable=true, example=4, description="null = platform code (DabApp-wide, discounts every line); set = shop code (discounts only that vendor's lines)"),
 *     @OA\Property(property="code", type="string", example="SHOPA10"),
 *     @OA\Property(property="discount_amount", type="number", example=64.90, description="This code's total effect across every line it applied to")
 * )
 *
 * @OA\Schema(
 *     schema="MarketplaceConflict",
 *     type="object",
 *     @OA\Property(property="message", type="string", example="Your application is already waiting for approval"),
 *     @OA\Property(property="code", type="string", example="ALREADY_APPLIED")
 * )
 *
 * @OA\Schema(
 *     schema="MarketplaceValidationError",
 *     type="object",
 *     @OA\Property(property="errors", type="object", example={"phone": {"The phone field is required."}})
 * )
 *
 * @OA\Schema(
 *     schema="MarketplaceProductCard",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=21),
 *     @OA\Property(property="slug", type="string", example="michelin-pilot-road-5-120-70-17"),
 *     @OA\Property(property="reference", type="string", example="MP-00021"),
 *     @OA\Property(property="name", type="string", example="Michelin Pilot Road 5 120/70-17"),
 *     @OA\Property(property="name_ar", type="string", nullable=true),
 *     @OA\Property(property="price", type="number", format="float", example=420, description="The cheapest variant's price when has_variants is true, otherwise the product's own price"),
 *     @OA\Property(property="price_max", type="number", format="float", nullable=true, example=null, description="Set only when has_variants is true and variant prices differ - show as a 'from-to' range"),
 *     @OA\Property(property="compare_at_price", type="number", format="float", nullable=true, example=null, description="Strikethrough original price"),
 *     @OA\Property(property="condition", type="string", enum={"new","used"}, example="new"),
 *     @OA\Property(property="in_stock", type="boolean", example=true),
 *     @OA\Property(property="has_variants", type="boolean", example=false, description="true = sold by variant (color/size/...), open the detail for the variants array"),
 *     @OA\Property(property="cover_image_url", type="string", nullable=true),
 *     @OA\Property(property="rating_avg", type="number", format="float", example=4.5),
 *     @OA\Property(property="reviews_count", type="integer", example=3),
 *     @OA\Property(property="likes_count", type="integer", example=12),
 *     @OA\Property(property="is_featured", type="boolean", example=false),
 *     @OA\Property(property="vendor", type="object", nullable=true,
 *         @OA\Property(property="id", type="integer"), @OA\Property(property="slug", type="string"),
 *         @OA\Property(property="shop_name", type="string"), @OA\Property(property="shop_name_ar", type="string", nullable=true)),
 *     @OA\Property(property="category", type="object", nullable=true,
 *         @OA\Property(property="id", type="integer"), @OA\Property(property="slug", type="string"),
 *         @OA\Property(property="name", type="string"), @OA\Property(property="name_ar", type="string", nullable=true)),
 *     @OA\Property(property="brand", type="object", nullable=true, description="Parts brand (Michelin), not the bike's manufacturer",
 *         @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string"), @OA\Property(property="logo_url", type="string", nullable=true))
 * )
 *
 * @OA\Schema(
 *     schema="MarketplaceProductVariant",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="sku", type="string", example="MP-00022-BLK-XS"),
 *     @OA\Property(property="option1_name", type="string", nullable=true, example="Color"),
 *     @OA\Property(property="option1_value", type="string", nullable=true, example="Black"),
 *     @OA\Property(property="color_hex", type="string", nullable=true, example="#000000"),
 *     @OA\Property(property="option2_name", type="string", nullable=true, example="Size"),
 *     @OA\Property(property="option2_value", type="string", nullable=true, example="XS"),
 *     @OA\Property(property="price", type="number", example=34.99),
 *     @OA\Property(property="compare_at_price", type="number", nullable=true, example=49.99),
 *     @OA\Property(property="in_stock", type="boolean", example=true),
 *     @OA\Property(property="image_url", type="string", nullable=true, description="Falls back to the product's own cover image when null"),
 *     @OA\Property(property="is_default", type="boolean", example=true, description="Pre-selected on the product page")
 * )
 *
 * @OA\Schema(
 *     schema="VendorMarketplaceProduct",
 *     type="object",
 *     description="List row: a cover thumbnail, no images/attributes/compatibility arrays (see the detail schema). Same shape the admin sees for this same product - nothing here is hidden from its own owner.",
 *     @OA\Property(property="id", type="integer", example=21),
 *     @OA\Property(property="vendor", type="object", nullable=true, @OA\Property(property="id", type="integer"), @OA\Property(property="slug", type="string"), @OA\Property(property="shop_name", type="string")),
 *     @OA\Property(property="category", type="object", nullable=true, @OA\Property(property="id", type="integer"), @OA\Property(property="slug", type="string"), @OA\Property(property="name", type="string")),
 *     @OA\Property(property="brand", type="object", nullable=true, @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string")),
 *     @OA\Property(property="reference", type="string", example="MP-00021"),
 *     @OA\Property(property="slug", type="string", example="michelin-pilot-road-5-120-70-17"),
 *     @OA\Property(property="name", type="string", example="Michelin Pilot Road 5 120/70-17"),
 *     @OA\Property(property="name_ar", type="string", nullable=true),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="description_ar", type="string", nullable=true),
 *     @OA\Property(property="price", type="number", example=420, description="Cheapest variant when has_variants is true, otherwise the product's own price"),
 *     @OA\Property(property="price_max", type="number", nullable=true, example=null),
 *     @OA\Property(property="compare_at_price", type="number", nullable=true, example=null),
 *     @OA\Property(property="in_stock", type="boolean", example=true),
 *     @OA\Property(property="has_variants", type="boolean", example=false),
 *     @OA\Property(property="stock_quantity", type="integer", example=14, description="Ignored once has_variants is true"),
 *     @OA\Property(property="condition", type="string", enum={"new","used"}, example="new"),
 *     @OA\Property(property="status", type="string", enum={"draft","pending_review","active","inactive","rejected"}, example="active", description="Only draft/pending_review are settable from this controller - active/inactive/rejected are admin-only"),
 *     @OA\Property(property="status_reason", type="string", nullable=true, description="Set by the admin when rejecting - clears automatically on your next resubmission"),
 *     @OA\Property(property="is_featured", type="boolean", example=false, description="Admin-curated, read-only here"),
 *     @OA\Property(property="published_at", type="string", nullable=true),
 *     @OA\Property(property="rating_avg", type="number", example=4.5),
 *     @OA\Property(property="reviews_count", type="integer", example=3),
 *     @OA\Property(property="likes_count", type="integer", example=12),
 *     @OA\Property(property="sales_count", type="integer", example=6),
 *     @OA\Property(property="created_at", type="string"),
 *     @OA\Property(property="cover_image_url", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="VendorMarketplaceProductVariant",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="sku", type="string", example="MP-00022-BLK-XS"),
 *     @OA\Property(property="option1_name", type="string", nullable=true, example="Color"),
 *     @OA\Property(property="option1_value", type="string", nullable=true, example="Black"),
 *     @OA\Property(property="color_hex", type="string", nullable=true, example="#000000"),
 *     @OA\Property(property="option2_name", type="string", nullable=true, example="Size"),
 *     @OA\Property(property="option2_value", type="string", nullable=true, example="XS"),
 *     @OA\Property(property="price", type="number", example=34.99),
 *     @OA\Property(property="compare_at_price", type="number", nullable=true, example=49.99),
 *     @OA\Property(property="in_stock", type="boolean", example=true),
 *     @OA\Property(property="image_url", type="string", nullable=true),
 *     @OA\Property(property="is_default", type="boolean", example=true)
 * )
 *
 * @OA\Schema(
 *     schema="VendorMarketplaceProductDetail",
 *     type="object",
 *     description="Same fields as VendorMarketplaceProduct, with the cover thumbnail replaced by the full images/attributes/compatibility/variants arrays.",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/VendorMarketplaceProduct"),
 *         @OA\Schema(
 *             @OA\Property(property="images", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer"), @OA\Property(property="image_url", type="string"),
 *                 @OA\Property(property="is_cover", type="boolean"), @OA\Property(property="order_position", type="integer"))),
 *             @OA\Property(property="attributes", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer"), @OA\Property(property="label", type="string"), @OA\Property(property="label_ar", type="string", nullable=true),
 *                 @OA\Property(property="value", type="string"), @OA\Property(property="value_ar", type="string", nullable=true))),
 *             @OA\Property(property="compatibility", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer"), @OA\Property(property="is_universal", type="boolean"),
 *                 @OA\Property(property="moto_brand_id", type="integer", nullable=true), @OA\Property(property="brand", type="string", nullable=true),
 *                 @OA\Property(property="bike_type_id", type="integer", nullable=true), @OA\Property(property="bike_type", type="string", nullable=true),
 *                 @OA\Property(property="moto_model_id", type="integer", nullable=true), @OA\Property(property="model", type="string", nullable=true),
 *                 @OA\Property(property="moto_year_id", type="integer", nullable=true), @OA\Property(property="year", type="integer", nullable=true))),
 *             @OA\Property(property="variants", type="array", @OA\Items(ref="#/components/schemas/VendorMarketplaceProductVariant"))
 *         )
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="MarketplaceCart",
 *     type="object",
 *     @OA\Property(property="id", type="integer", nullable=true, example=12, description="null on the never-touched empty cart shape"),
 *     @OA\Property(property="items", type="array", @OA\Items(type="object",
 *         @OA\Property(property="id", type="integer", example=40),
 *         @OA\Property(property="product", type="object", nullable=true, description="null if the product was removed since this was added",
 *             @OA\Property(property="id", type="integer"), @OA\Property(property="slug", type="string"), @OA\Property(property="name", type="string"), @OA\Property(property="name_ar", type="string", nullable=true),
 *             @OA\Property(property="cover_image_url", type="string", nullable=true),
 *             @OA\Property(property="vendor", type="object", nullable=true, @OA\Property(property="id", type="integer"), @OA\Property(property="slug", type="string"), @OA\Property(property="shop_name", type="string"))),
 *         @OA\Property(property="variant", type="object", nullable=true, description="null when the product has no variants",
 *             @OA\Property(property="id", type="integer"), @OA\Property(property="sku", type="string"),
 *             @OA\Property(property="option1_name", type="string", nullable=true), @OA\Property(property="option1_value", type="string", nullable=true), @OA\Property(property="color_hex", type="string", nullable=true),
 *             @OA\Property(property="option2_name", type="string", nullable=true), @OA\Property(property="option2_value", type="string", nullable=true),
 *             @OA\Property(property="image_url", type="string", nullable=true)),
 *         @OA\Property(property="quantity", type="integer", example=2),
 *         @OA\Property(property="unit_price", type="number", example=420, description="Price when it was added - frozen, compare to current_price"),
 *         @OA\Property(property="current_price", type="number", nullable=true, example=450),
 *         @OA\Property(property="price_changed", type="boolean", example=true, description="unit_price != current_price - show a 'price has changed' hint"),
 *         @OA\Property(property="line_total", type="number", example=840, description="quantity * unit_price (the frozen price, not current_price)"),
 *         @OA\Property(property="available", type="boolean", example=true, description="false if the product or its shop is no longer active"),
 *         @OA\Property(property="in_stock", type="boolean", example=true),
 *         @OA\Property(property="max_quantity", type="integer", example=14, description="Current available stock for this product/variant"))),
 *     @OA\Property(property="items_count", type="integer", example=3, description="Sum of quantities, not line count"),
 *     @OA\Property(property="subtotal", type="number", example=840),
 *     @OA\Property(property="updated_at", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="MarketplaceProductReview",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=301),
 *     @OA\Property(property="rating", type="integer", example=5),
 *     @OA\Property(property="title", type="string", nullable=true, example="Great Product!!"),
 *     @OA\Property(property="comment", type="string", nullable=true),
 *     @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"Build Quality","Delivery Time"}),
 *     @OA\Property(property="image_urls", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="verified_purchase", type="boolean", example=false, description="Always false for now - there is no Orders/Cart yet to verify a purchase against"),
 *     @OA\Property(property="author", type="object", nullable=true, description="null when the reviewer chose is_anonymous",
 *         @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string")),
 *     @OA\Property(property="created_at", type="string")
 * )
 *
 * @OA\Schema(
 *     schema="MarketplaceBrand",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=4),
 *     @OA\Property(property="slug", type="string", example="michelin"),
 *     @OA\Property(property="name", type="string", example="Michelin"),
 *     @OA\Property(property="logo_url", type="string", nullable=true),
 *     @OA\Property(property="is_featured", type="boolean", example=true),
 *     @OA\Property(property="products_count", type="integer", example=58)
 * )
 *
 * @OA\Tag(
 *     name="Marketplace - Images",
 *     description="Upload images before you use them, the same way listings do it: a standalone endpoint that returns URL strings, decoupled from creating or editing the resource. Send those strings as logo_path / cover_image_path / image_path on the vendor, category, brand or product endpoints — those endpoints take plain JSON, never files."
 * )
 *
 * @OA\Post(
 *     path="/api/marketplace/upload-image",
 *     summary="Upload one or more images",
 *     description="Resizes to fit inside 1200x800, no watermark (these are catalog/branding assets, not the peer-to-peer listing photos the watermark protects). Returns one absolute URL per file, in the same order. Nothing is attached to any resource yet: pass the URL(s) as a plain string field on the next call (see each resource's endpoint). type=category and type=brand are admin-only (403 ADMIN_ONLY for a regular user). Access: User.",
 *     tags={"Marketplace - Images"},
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
 *         required={"type","images"},
 *         @OA\Property(property="type", type="string", enum={"vendor","category","brand","product"}, example="vendor", description="Picks the storage folder. vendor: shop logo/cover. product: product photos. category/brand: admin only."),
 *         @OA\Property(property="images", type="array", @OA\Items(type="string", format="binary"), description="1 to 10 files, jpg/png/webp, max 8 MB each")
 *     ))),
 *     @OA\Response(response=200, description="Uploaded", @OA\JsonContent(
 *         @OA\Property(property="message", type="string", example="Images uploaded successfully"),
 *         @OA\Property(property="type", type="string", example="vendor"),
 *         @OA\Property(property="paths", type="array", @OA\Items(type="string"), example={"https://api.dabapp.co/storage/marketplace/vendors/aB3dE9fGh1JkLmN0pQrS.jpg"})
 *     )),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
 *     @OA\Response(response=403, description="type=category or type=brand from a non-admin token.", @OA\JsonContent(example={"message": "Only admins can upload category images", "code": "ADMIN_ONLY"})),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationFailed")
 * )
 */
class MarketplaceSwagger extends Controller
{
    // Holds the Swagger root annotations only.
}
