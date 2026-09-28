<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\Category;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Marketplace - Vendors",
 *     description="Shops (All Shops screen and shop page). Public, no token needed. Only approved (active) vendors are ever visible."
 * )
 */
class VendorController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/marketplace/vendors",
     *     summary="List shops (All Shops)",
     *     description="Active vendors only. products_count = active products of the shop. Access: Public.",
     *     tags={"Marketplace - Vendors"},
     *     @OA\Parameter(name="search", in="query", required=false, description="shop_name / shop_name_ar contains", @OA\Schema(type="string", example="moto")),
     *     @OA\Parameter(name="sort", in="query", required=false, description="Default rating (the All Shops 'Top Rated' tab)", @OA\Schema(type="string", enum={"rating","newest","name","products"}, default="rating")),
     *     @OA\Parameter(name="featured", in="query", required=false, description="1 = only admin-curated shops (the Featured badge)", @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Parameter(name="country_id", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="city_id", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, description="1-50", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="Shops, best rated first by default. Load the next page with page+1 until current_page = last_page.", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MarketplaceVendorCard")),
     *         @OA\Property(property="meta", type="object",
     *             @OA\Property(property="current_page", type="integer", example=1), @OA\Property(property="per_page", type="integer", example=15),
     *             @OA\Property(property="last_page", type="integer", example=3), @OA\Property(property="total", type="integer", example=41)),
     *         example={"data": {
     *             {"id": 4, "slug": "moto-parts-riyadh", "shop_name": "Moto Parts Riyadh", "shop_name_ar": "مركز قطع الغيار الرياض", "logo_url": "https://api.dabapp.co/storage/marketplace/vendors/moto-parts-riyadh-logo.png", "cover_image_url": "https://api.dabapp.co/storage/marketplace/vendors/moto-parts-riyadh-cover.png", "brand_color": "#E63946", "is_featured": true, "rating_avg": 4.5, "reviews_count": 2, "products_count": 13, "city": {"id": 1, "name": "Riyadh"}},
     *             {"id": 5, "slug": "riders-gear-jeddah", "shop_name": "Rider's Gear Jeddah", "shop_name_ar": "معدات الراكب جدة", "logo_url": null, "cover_image_url": null, "brand_color": "#1D3557", "is_featured": false, "rating_avg": 4, "reviews_count": 1, "products_count": 10, "city": {"id": 2, "name": "Jeddah"}}
     *         }, "meta": {"current_page": 1, "per_page": 15, "last_page": 1, "total": 2}}
     *     ))
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);

        $query = Vendor::active()
            ->with('city:id,name')
            ->withCount(['products as products_count' => fn ($q) => $q->where('status', 'active')])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->input('search') . '%';
                $q->where(fn ($w) => $w->where('shop_name', 'like', $term)->orWhere('shop_name_ar', 'like', $term));
            })
            ->when($request->filled('country_id'), fn ($q) => $q->where('country_id', (int) $request->input('country_id')))
            ->when($request->filled('city_id'), fn ($q) => $q->where('city_id', (int) $request->input('city_id')))
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true));

        match ($request->input('sort')) {
            'newest'   => $query->orderByDesc('id'),
            'name'     => $query->orderBy('shop_name'),
            'products' => $query->orderByDesc('products_count')->orderBy('shop_name'),
            default    => $query->orderByDesc('rating_avg')->orderByDesc('reviews_count')->orderBy('shop_name'),
        };

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Vendor $v) => self::card($v))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/marketplace/vendors/{idOrSlug}",
     *     summary="Shop page",
     *     description="Shop header: branding, rating, contact location, categories the shop sells in, and its enabled shipping methods (flat rate). Bank data, commission and carrier keys are never exposed. Access: Public.",
     *     tags={"Marketplace - Vendors"},
     *     @OA\Parameter(name="idOrSlug", in="path", required=true, description="Numeric id or slug", @OA\Schema(type="string", example="moto-parts-riyadh")),
     *     @OA\Response(response=200, description="Shop", @OA\JsonContent(
     *         example={"data": {"id": 4, "slug": "moto-parts-riyadh", "shop_name": "Moto Parts Riyadh", "shop_name_ar": "مركز قطع الغيار الرياض", "logo_url": "https://api.dabapp.co/storage/marketplace/vendors/moto-parts-riyadh-logo.png", "cover_image_url": "https://api.dabapp.co/storage/marketplace/vendors/moto-parts-riyadh-cover.png", "brand_color": "#E63946", "is_featured": true, "rating_avg": 4.5, "reviews_count": 2, "products_count": 13, "city": {"id": 1, "name": "Riyadh"},
     *             "description": "Tyres, brakes and engine parts for sport and touring bikes.", "description_ar": null, "country": {"id": 1, "name": "Saudi Arabia"},
     *             "categories": {
     *                 {"id": 11, "slug": "air-filters", "name": "Air filters", "name_ar": "فلاتر الهواء", "products_count": 1},
     *                 {"id": 14, "slug": "batteries", "name": "Batteries", "name_ar": "البطاريات", "products_count": 1}
     *             },
     *             "shipping_methods": {
     *                 {"id": 8, "provider": "DabApp Shipping", "code": "dabapp", "flat_rate": 25, "estimated_days_min": 3, "estimated_days_max": 6},
     *                 {"id": 7, "provider": "DHL", "code": "dhl", "flat_rate": 35, "estimated_days_min": 2, "estimated_days_max": 4}
     *             }}},
     *         @OA\Property(property="data", allOf={
     *             @OA\Schema(ref="#/components/schemas/MarketplaceVendorCard"),
     *             @OA\Schema(
     *                 @OA\Property(property="description", type="string", nullable=true),
     *                 @OA\Property(property="description_ar", type="string", nullable=true),
     *                 @OA\Property(property="country", type="object", nullable=true, @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string")),
     *                 @OA\Property(property="categories", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="id", type="integer"), @OA\Property(property="slug", type="string"),
     *                     @OA\Property(property="name", type="string"), @OA\Property(property="name_ar", type="string", nullable=true),
     *                     @OA\Property(property="products_count", type="integer"))),
     *                 @OA\Property(property="shipping_methods", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="id", type="integer"), @OA\Property(property="provider", type="string", example="DHL"),
     *                     @OA\Property(property="code", type="string", example="dhl"), @OA\Property(property="flat_rate", type="number", example=35),
     *                     @OA\Property(property="estimated_days_min", type="integer", nullable=true), @OA\Property(property="estimated_days_max", type="integer", nullable=true)))
     *             )
     *         })
     *     )),
     *     @OA\Response(response=404, description="Unknown shop, or a shop that is not approved (pending, rejected, suspended). Both look the same to the app.", @OA\JsonContent(example={"message": "Vendor not found"}))
     * )
     */
    public function show(string $idOrSlug): JsonResponse
    {
        $vendor = Vendor::active()
            ->with(['city:id,name', 'country:id,name'])
            ->withCount(['products as products_count' => fn ($q) => $q->where('status', 'active')])
            ->where(ctype_digit($idOrSlug) ? 'id' : 'slug', $idOrSlug)
            ->first();

        if (! $vendor) {
            return response()->json(['message' => 'Vendor not found'], 404);
        }

        $perCategory = Product::where('vendor_id', $vendor->id)->where('status', 'active')
            ->selectRaw('category_id, COUNT(*) as total')->groupBy('category_id')->pluck('total', 'category_id');

        $categories = Category::active()->whereIn('id', $perCategory->keys())->orderBy('name')->get()
            ->map(fn (Category $c) => [
                'id' => $c->id, 'slug' => $c->slug, 'name' => $c->name, 'name_ar' => $c->name_ar,
                'products_count' => (int) $perCategory[$c->id],
            ])->values();

        $shipping = $vendor->shippingMethods()->active()
            ->whereHas('provider', fn ($q) => $q->where('is_active', true))
            ->with('provider:id,name,code')->orderBy('flat_rate')->get()
            ->map(fn ($m) => [
                'id'                 => $m->id,
                'provider'           => $m->provider->name,
                'code'               => $m->provider->code,
                'flat_rate'          => (float) $m->flat_rate,
                'estimated_days_min' => $m->estimated_days_min,
                'estimated_days_max' => $m->estimated_days_max,
            ])->values();

        return response()->json(['data' => self::card($vendor) + [
            'description'      => $vendor->description,
            'description_ar'   => $vendor->description_ar,
            'country'          => $vendor->country ? ['id' => $vendor->country->id, 'name' => $vendor->country->name] : null,
            'categories'       => $categories,
            'shipping_methods' => $shipping,
        ]]);
    }

    /** Shared with HomeController so the "Shops" row on the Marketplace home uses the exact same card shape. */
    public static function card(Vendor $vendor): array
    {
        return [
            'id'              => $vendor->id,
            'slug'            => $vendor->slug,
            'shop_name'       => $vendor->shop_name,
            'shop_name_ar'    => $vendor->shop_name_ar,
            'logo_url'        => $vendor->logo_url,
            'cover_image_url' => $vendor->cover_image_url,
            'brand_color'     => $vendor->brand_color,
            'is_featured'     => (bool) $vendor->is_featured,
            'rating_avg'      => (float) $vendor->rating_avg,
            'reviews_count'   => (int) $vendor->reviews_count,
            'products_count'  => (int) $vendor->products_count,
            'city'            => $vendor->city ? ['id' => $vendor->city->id, 'name' => $vendor->city->name] : null,
        ];
    }
}
