<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\Category;
use App\Models\Marketplace\Vendor;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleType;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Marketplace - Home",
 *     description="One call for the Marketplace home screen. Public, no token needed."
 * )
 */
class HomeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/marketplace/home",
     *     summary="Marketplace home screen",
     *     description="Everything the home screen needs in one call, in the order the screen shows them: Shops, Accessories (categories), Bike Type, Brand. bike_types and bike_brands come from the app's existing vehicle tables (the same ones the moto listings use), not from the marketplace catalog: 'Bike Brand' means Honda / KTM / Yamaha (who made the bike), never a marketplace parts brand like Michelin. Access: Public.",
     *     tags={"Marketplace - Home"},
     *     @OA\Response(response=200, description="Home sections", @OA\JsonContent(
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="shops", type="array", description="Featured shops first, then best rated. View All -> GET /marketplace/vendors.", @OA\Items(ref="#/components/schemas/MarketplaceVendorCard")),
     *             @OA\Property(property="categories", type="array", description="Top-level marketplace categories (the 'Accessories' row on the screen is one example; every root category — Parts, Oils & Care, Accessories, Riding gear — comes back here). View All / tapping one -> GET /marketplace/categories or /marketplace/categories/{id}.", @OA\Items(ref="#/components/schemas/MarketplaceCategory")),
     *             @OA\Property(property="bike_types", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="id", type="integer", example=3), @OA\Property(property="name", type="string", example="Standard"),
     *                 @OA\Property(property="name_ar", type="string", nullable=true), @OA\Property(property="icon", type="string", nullable=true))),
     *             @OA\Property(property="bike_brands", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="id", type="integer", example=7), @OA\Property(property="name", type="string", example="Honda")))
     *         ),
     *         example={"data": {
     *             "shops": {{"id": 4, "slug": "moto-parts-riyadh", "shop_name": "Moto Parts Riyadh", "shop_name_ar": "مركز قطع الغيار الرياض", "logo_url": "https://api.dabapp.co/storage/marketplace/vendors/moto-parts-riyadh-logo.png", "cover_image_url": null, "brand_color": "#E63946", "is_featured": true, "rating_avg": 4.5, "reviews_count": 2, "products_count": 13, "city": {"id": 1, "name": "Riyadh"}}},
     *             "categories": {{"id": 18, "parent_id": null, "slug": "accessories", "name": "Accessories", "name_ar": "الإكسسوارات", "image_url": null, "children_count": 4, "products_count": 9}},
     *             "bike_types": {{"id": 3, "name": "Standard", "name_ar": null, "icon": null}, {"id": 4, "name": "Racing", "name_ar": null, "icon": null}},
     *             "bike_brands": {{"id": 7, "name": "Honda"}, {"id": 9, "name": "Yamaha"}}
     *         }}
     *     ))
     * )
     */
    public function index(): JsonResponse
    {
        $shops = Vendor::active()
            ->with('city:id,name')
            ->withCount(['products as products_count' => fn ($q) => $q->where('status', 'active')])
            ->orderByDesc('is_featured')->orderByDesc('rating_avg')->orderByDesc('reviews_count')
            ->limit(8)->get()
            ->map(fn (Vendor $v) => VendorController::card($v))->values();

        $counts = Category::productCounts();
        $categories = Category::active()->roots()->withCount('children')->orderBy('order_position')->orderBy('name')->get()
            ->map(fn (Category $c) => [
                'id' => $c->id, 'parent_id' => $c->parent_id, 'slug' => $c->slug, 'name' => $c->name, 'name_ar' => $c->name_ar,
                'image_url' => $c->image_url, 'children_count' => $c->children_count, 'products_count' => $counts[$c->id] ?? 0,
            ])->values();

        // Existing vehicle tables (moto listings), not the new marketplace ones: "Bike Type" = Standard/Racing/..., "Bike Brand" = Honda/KTM/...
        $bikeTypes = MotorcycleType::orderBy('name')->limit(12)->get(['id', 'name', 'name_ar', 'icon']);
        $bikeBrands = MotorcycleBrand::where('is_displayed', true)->orderBy('name')->limit(12)->get(['id', 'name']);

        return response()->json(['data' => [
            'shops'       => $shops,
            'categories'  => $categories,
            'bike_types'  => $bikeTypes,
            'bike_brands' => $bikeBrands,
        ]]);
    }
}
