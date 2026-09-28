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
