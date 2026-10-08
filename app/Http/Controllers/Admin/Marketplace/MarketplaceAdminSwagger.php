<?php

namespace App\Http\Controllers\Admin\Marketplace;

use App\Http\Controllers\Controller;

/**
 * Swagger root for the marketplace Admin Panel documentation.
 * UI: /marketplace-admin/documentation
 *
 * @OA\Info(
 *     title="DabApp Marketplace Admin API",
 *     version="1.0.0",
 *     description="Back-office API for the marketplace (catalog, vendors, orders, payouts, moderation). Every route needs an admin Bearer token (middleware auth.admin) and role_id = 1."
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Admin Panel JWT."
 * )
 *
 * @OA\Schema(
 *     schema="AdminMarketplaceCategory",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=12),
 *     @OA\Property(property="parent_id", type="integer", nullable=true, example=3),
 *     @OA\Property(property="name", type="string", example="Tyres"),
 *     @OA\Property(property="name_ar", type="string", nullable=true, example="إطارات"),
 *     @OA\Property(property="slug", type="string", example="tyres"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="image_path", type="string", nullable=true),
 *     @OA\Property(property="image_url", type="string", nullable=true),
 *     @OA\Property(property="order_position", type="integer", example=0),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="children_count", type="integer", example=3),
 *     @OA\Property(property="products_count", type="integer", description="Direct products only", example=10),
 *     @OA\Property(property="created_at", type="string", example="2026-09-22T00:21:33.000000Z")
 * )
 *
 * @OA\Schema(
 *     schema="AdminMarketplaceBrand",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=4),
 *     @OA\Property(property="name", type="string", example="Michelin"),
 *     @OA\Property(property="slug", type="string", example="michelin"),
 *     @OA\Property(property="logo_path", type="string", nullable=true),
 *     @OA\Property(property="logo_url", type="string", nullable=true),
 *     @OA\Property(property="is_featured", type="boolean", example=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="products_count", type="integer", example=8)
 * )
 *
 * @OA\Schema(
 *     schema="AdminMarketplaceShippingProvider",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="DHL"),
 *     @OA\Property(property="code", type="string", example="dhl"),
 *     @OA\Property(property="requires_api_key", type="boolean", example=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="vendor_methods_count", type="integer", example=2)
 * )
 *
 * @OA\Schema(
 *     schema="AdminMarketplaceVendor",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=7),
 *     @OA\Property(property="user_id", type="integer", example=42),
 *     @OA\Property(property="shop_name", type="string", example="Moto Parts Riyadh"),
 *     @OA\Property(property="shop_name_ar", type="string", nullable=true),
 *     @OA\Property(property="slug", type="string", example="moto-parts-riyadh"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="description_ar", type="string", nullable=true),
 *     @OA\Property(property="logo_path", type="string", nullable=true),
 *     @OA\Property(property="logo_url", type="string", nullable=true),
 *     @OA\Property(property="cover_image_path", type="string", nullable=true),
 *     @OA\Property(property="cover_image_url", type="string", nullable=true),
 *     @OA\Property(property="brand_color", type="string", nullable=true, example="#E63946"),
 *     @OA\Property(property="phone", type="string", nullable=true),
 *     @OA\Property(property="email", type="string", nullable=true),
 *     @OA\Property(property="country_id", type="integer", nullable=true),
 *     @OA\Property(property="city_id", type="integer", nullable=true),
 *     @OA\Property(property="status", type="string", enum={"pending","active","suspended","rejected"}, example="active"),
 *     @OA\Property(property="is_featured", type="boolean", description="Curated by an admin: shown on the Marketplace home Shops row and the Featured badge on All Shops", example=false),
 *     @OA\Property(property="status_reason", type="string", nullable=true),
 *     @OA\Property(property="approved_at", type="string", nullable=true),
 *     @OA\Property(property="commission_override", type="number", nullable=true, description="% ; null = category or global rate", example=12.5),
 *     @OA\Property(property="bank_name", type="string", nullable=true),
 *     @OA\Property(property="iban", type="string", nullable=true, description="Detail only. The list never returns it."),
 *     @OA\Property(property="rating_avg", type="number", example=4.5),
 *     @OA\Property(property="reviews_count", type="integer", example=2),
 *     @OA\Property(property="products_count", type="integer", example=13),
 *     @OA\Property(property="owner", type="object", nullable=true,
 *         @OA\Property(property="id", type="integer"), @OA\Property(property="first_name", type="string"),
 *         @OA\Property(property="last_name", type="string"), @OA\Property(property="email", type="string"),
 *         @OA\Property(property="phone", type="string"))
 * )
 *
 * @OA\Schema(
 *     schema="AdminMarketplaceVendorShippingMethod",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=3),
 *     @OA\Property(property="vendor_id", type="integer", example=7),
 *     @OA\Property(property="provider_id", type="integer", example=1),
 *     @OA\Property(property="provider", type="object", @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string", example="DHL"), @OA\Property(property="code", type="string", example="dhl")),
 *     @OA\Property(property="uses_own_api", type="boolean", example=true),
 *     @OA\Property(property="has_credentials", type="boolean", description="Keys are write-only: only this flag is ever returned", example=true),
 *     @OA\Property(property="flat_rate", type="number", example=35),
 *     @OA\Property(property="estimated_days_min", type="integer", nullable=true, example=2),
 *     @OA\Property(property="estimated_days_max", type="integer", nullable=true, example=4),
 *     @OA\Property(property="is_active", type="boolean", example=true)
 * )
 *
 * @OA\Schema(
 *     schema="AdminMarketplaceProduct",
 *     type="object",
 *     description="List row: a cover thumbnail, no images/attributes/compatibility arrays (see the detail schema).",
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
 *     @OA\Property(property="status", type="string", enum={"draft","pending_review","active","inactive","rejected"}, example="active"),
 *     @OA\Property(property="status_reason", type="string", nullable=true),
 *     @OA\Property(property="is_featured", type="boolean", example=false),
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
 *     schema="AdminMarketplaceProductVariant",
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
 *     schema="AdminMarketplaceProductDetail",
 *     type="object",
 *     description="Same fields as AdminMarketplaceProduct, with the cover thumbnail replaced by the full images/attributes/compatibility arrays.",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/AdminMarketplaceProduct"),
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
 *                 @OA\Property(property="moto_model_id", type="integer", nullable=true), @OA\Property(property="model", type="string", nullable=true),
 *                 @OA\Property(property="moto_year_id", type="integer", nullable=true), @OA\Property(property="year", type="integer", nullable=true))),
 *             @OA\Property(property="variants", type="array", @OA\Items(ref="#/components/schemas/AdminMarketplaceProductVariant"))
 *         )
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="ConflictError",
 *     type="object",
 *     @OA\Property(property="message", type="string", example="Category has products and cannot be deleted"),
 *     @OA\Property(property="code", type="string", example="HAS_PRODUCTS")
 * )
 *
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     @OA\Property(property="errors", type="object", example={"name": {"The name field is required."}})
 * )
 */
class MarketplaceAdminSwagger extends Controller
{
    // Holds the Swagger root annotations only.
}
