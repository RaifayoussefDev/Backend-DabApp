<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\Brand;
use App\Models\Marketplace\Category;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\ProductAttribute;
use App\Models\Marketplace\ProductMotorcycle;
use App\Models\Marketplace\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Marketplace - Products",
 *     description="Catalog: list with search/filters, filter facets, product detail. Public, no token needed. Only active products of active vendors are ever visible."
 * )
 */
class ProductController extends Controller
{
    private const EAGER = ['vendor:id,slug,shop_name,shop_name_ar', 'category:id,slug,name,name_ar', 'brand:id,name,logo_path', 'coverImage', 'variants'];

    /**
     * @OA\Get(
     *     path="/api/marketplace/products",
     *     summary="List products (catalog, search & filters)",
     *     description="Active products of active vendors only. category_id also matches its sub-categories (Tyres also returns Front tyres / Rear tyres). moto_brand_id / moto_model_id / moto_year_id match a product's explicit bike-compatibility rows plus universal parts. attributes[Label][]=Value filters on the spec table (e.g. attributes[Grip Style][]=Open Ended) - see GET /marketplace/products/filters for which labels/values exist in a given view. Access: Public.",
     *     tags={"Marketplace - Products"},
     *     @OA\Parameter(name="search", in="query", required=false, description="name / name_ar / reference contains", @OA\Schema(type="string", example="tyre")),
     *     @OA\Parameter(name="category_id", in="query", required=false, description="Includes its sub-categories", @OA\Schema(type="integer", example=2)),
     *     @OA\Parameter(name="brand_id", in="query", required=false, description="Parts brand (Michelin) - not the bike's manufacturer", @OA\Schema(type="integer", example=4)),
     *     @OA\Parameter(name="vendor_id", in="query", required=false, @OA\Schema(type="integer", example=7)),
     *     @OA\Parameter(name="moto_brand_id", in="query", required=false, description="Bike manufacturer (Honda, KTM)", @OA\Schema(type="integer", example=2)),
     *     @OA\Parameter(name="moto_model_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="moto_year_id", in="query", required=false, description="The 'Shop By [year]' filter", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="condition", in="query", required=false, @OA\Schema(type="string", enum={"new","used"})),
     *     @OA\Parameter(name="price_min", in="query", required=false, @OA\Schema(type="number", example=100)),
     *     @OA\Parameter(name="price_max", in="query", required=false, @OA\Schema(type="number", example=500)),
     *     @OA\Parameter(name="rating_min", in="query", required=false, description="e.g. 4 = 4 stars and up", @OA\Schema(type="number", example=4)),
     *     @OA\Parameter(name="in_stock", in="query", required=false, @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Parameter(name="featured", in="query", required=false, @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Parameter(name="attributes", in="query", required=false, description="Map of label to one or more values, e.g. attributes[Finish][]=Matte", @OA\Schema(type="object")),
     *     @OA\Parameter(name="sort", in="query", required=false, description="Default newest. relevance=default, most_recent=newest, lowest_price=price_low, highest_price=price_high (app naming -> API naming)", @OA\Schema(type="string", enum={"newest","price_low","price_high","rating","popular"}, default="newest")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, description="1-50", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="Products, newest first by default. Load the next page with page+1 until current_page = last_page.", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MarketplaceProductCard")),
     *         @OA\Property(property="meta", type="object",
     *             @OA\Property(property="current_page", type="integer", example=1), @OA\Property(property="per_page", type="integer", example=15),
     *             @OA\Property(property="last_page", type="integer", example=1), @OA\Property(property="total", type="integer", example=2)),
     *         example={"data": {
     *             {"id": 21, "slug": "michelin-pilot-road-5-120-70-17", "reference": "MP-00021", "name": "Michelin Pilot Road 5 120/70-17", "name_ar": null, "price": 420, "price_max": null, "compare_at_price": null, "condition": "new", "in_stock": true, "has_variants": false, "cover_image_url": "https://api.dabapp.co/storage/marketplace/products/a1b2c3.jpg", "rating_avg": 4.5, "reviews_count": 3, "likes_count": 12, "is_featured": true, "vendor": {"id": 4, "slug": "moto-parts-riyadh", "shop_name": "Moto Parts Riyadh", "shop_name_ar": null}, "category": {"id": 3, "slug": "tyres-front", "name": "Front tyres", "name_ar": "إطارات أمامية"}, "brand": {"id": 4, "name": "Michelin", "logo_url": null}},
     *             {"id": 22, "slug": "bilt-sprint-gloves", "reference": "MP-00022", "name": "BILT Sprint Gloves", "name_ar": null, "price": 34.99, "price_max": 120.99, "compare_at_price": 49.99, "condition": "new", "in_stock": true, "has_variants": true, "cover_image_url": null, "rating_avg": 4.8, "reviews_count": 108, "likes_count": 0, "is_featured": false, "vendor": {"id": 5, "slug": "riders-gear-jeddah", "shop_name": "Rider's Gear Jeddah", "shop_name_ar": null}, "category": {"id": 20, "slug": "gloves", "name": "Gloves", "name_ar": null}, "brand": null}
     *         }, "meta": {"current_page": 1, "per_page": 15, "last_page": 1, "total": 2}}
     *     )),
     *     @OA\Response(response=404, description="category_id does not exist", @OA\JsonContent(example={"message": "Category not found"}))
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);

        $categoryIds = null;
        if ($request->filled('category_id')) {
            $category = Category::find((int) $request->input('category_id'));
            if (! $category) {
                return response()->json(['message' => 'Category not found'], 404);
            }
            $categoryIds = $category->descendantIds();
        }

        $query = Product::visible()->with(self::EAGER)
            ->when($categoryIds, fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', (int) $request->input('brand_id')))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', (int) $request->input('vendor_id')))
            ->when($request->filled('condition'), fn ($q) => $q->where('condition', $request->input('condition')))
            ->when($request->filled('price_min'), fn ($q) => $q->where('price', '>=', (float) $request->input('price_min')))
            ->when($request->filled('price_max'), fn ($q) => $q->where('price', '<=', (float) $request->input('price_max')))
            ->when($request->filled('rating_min'), fn ($q) => $q->where('rating_avg', '>=', (float) $request->input('rating_min')))
            ->when($request->boolean('in_stock'), fn ($q) => $q->inStock())
            ->when($request->filled('featured'), fn ($q) => $q->where('is_featured', $request->boolean('featured')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->input('search') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('name_ar', 'like', $term)->orWhere('reference', 'like', $term));
            })
            ->when($request->filled('attributes') && is_array($request->input('attributes')), function ($q) use ($request) {
                foreach ($request->input('attributes') as $label => $values) {
                    $values = array_map('strval', (array) $values);
                    $q->whereHas('attributes', fn ($a) => $a->where('label', $label)->whereIn('value', $values));
                }
            })
            ->when(
                $request->filled('moto_brand_id') || $request->filled('moto_model_id') || $request->filled('moto_year_id'),
                fn ($q) => $q->compatibleWith(
                    $request->filled('moto_brand_id') ? (int) $request->input('moto_brand_id') : null,
                    $request->filled('moto_model_id') ? (int) $request->input('moto_model_id') : null,
                    $request->filled('moto_year_id') ? (int) $request->input('moto_year_id') : null,
                )
            );

        match ($request->input('sort')) {
            'price_low'  => $query->orderBy('price'),
            'price_high' => $query->orderByDesc('price'),
            'rating'     => $query->orderByDesc('rating_avg')->orderByDesc('reviews_count'),
            'popular'    => $query->orderByDesc('sales_count'),
            default      => $query->orderByDesc('id'),
        };

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Product $p) => self::card($p))->values(),
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
     *     path="/api/marketplace/products/filters",
     *     summary="Available filters for a catalog view (facets)",
     *     description="Call it with the same scoping params as the list (category_id, search, vendor_id, brand_id, moto_brand_id/model_id/year_id) to get the options worth showing in the Filters sheet for that exact view, each with how many products it would leave. price reflects the real price (the variant range for products that have variants, not the fallback products.price column). Access: Public.",
     *     tags={"Marketplace - Products"},
     *     @OA\Parameter(name="category_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="vendor_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="brand_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="moto_brand_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="moto_model_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="moto_year_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Facets", @OA\JsonContent(
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="price", type="object", @OA\Property(property="min", type="number", nullable=true), @OA\Property(property="max", type="number", nullable=true)),
     *             @OA\Property(property="brands", type="array", description="Parts brand (Michelin) - use with brand_id", @OA\Items(type="object", @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string"), @OA\Property(property="count", type="integer"))),
     *             @OA\Property(property="moto_brands", type="array", description="The bike's manufacturer (Honda, BMW, KTM) - the Filters sheet's 'Brand' chip. Use with moto_brand_id, not brand_id.", @OA\Items(type="object", @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string"), @OA\Property(property="count", type="integer"))),
     *             @OA\Property(property="years", type="array", description="Bike compatibility years ('Shop By'). Use with moto_year_id.", @OA\Items(type="object", @OA\Property(property="id", type="integer"), @OA\Property(property="year", type="integer"), @OA\Property(property="count", type="integer"))),
     *             @OA\Property(property="attributes", type="object", description="Map of attribute label to a list of its values with how many products have that value, e.g. Grip Style: Open Ended (12), Close Ended (3)"),
     *             @OA\Property(property="ratings", type="array", description="min-rating buckets with at least one match", @OA\Items(type="object", @OA\Property(property="min", type="integer", example=4), @OA\Property(property="count", type="integer")))
     *         ),
     *         example={"data": {
     *             "price": {"min": 34.99, "max": 8999},
     *             "brands": {{"id": 4, "name": "Michelin", "count": 12}},
     *             "moto_brands": {{"id": 2, "name": "Honda", "count": 9}, {"id": 7, "name": "BMW", "count": 3}},
     *             "years": {{"id": 140, "year": 2024, "count": 5}},
     *             "attributes": {"Grip Style": {{"value": "Open Ended", "count": 8}, {"value": "Close Ended", "count": 3}}, "Finish": {{"value": "Matte", "count": 6}}},
     *             "ratings": {{"min": 5, "count": 2}, {"min": 4, "count": 9}}
     *         }}
     *     )),
     *     @OA\Response(response=404, description="category_id does not exist", @OA\JsonContent(example={"message": "Category not found"}))
     * )
     */
    public function filters(Request $request): JsonResponse
    {
        $categoryIds = null;
        if ($request->filled('category_id')) {
            $category = Category::find((int) $request->input('category_id'));
            if (! $category) {
                return response()->json(['message' => 'Category not found'], 404);
            }
            $categoryIds = $category->descendantIds();
        }

        $base = Product::visible()
            ->when($categoryIds, fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', (int) $request->input('brand_id')))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', (int) $request->input('vendor_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->input('search') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('name_ar', 'like', $term)->orWhere('reference', 'like', $term));
            })
            ->when(
                $request->filled('moto_brand_id') || $request->filled('moto_model_id') || $request->filled('moto_year_id'),
                fn ($q) => $q->compatibleWith(
                    $request->filled('moto_brand_id') ? (int) $request->input('moto_brand_id') : null,
                    $request->filled('moto_model_id') ? (int) $request->input('moto_model_id') : null,
                    $request->filled('moto_year_id') ? (int) $request->input('moto_year_id') : null,
                )
            );

        $ids = (clone $base)->pluck('id');

        $basePrices = (clone $base)->pluck('price', 'id');
        $variantMins = ProductVariant::whereIn('product_id', $ids)->selectRaw('product_id, MIN(price) as min_price')->groupBy('product_id')->pluck('min_price', 'product_id');
        $variantMaxes = ProductVariant::whereIn('product_id', $ids)->selectRaw('product_id, MAX(price) as max_price')->groupBy('product_id')->pluck('max_price', 'product_id');
        $effectiveMins = $basePrices->map(fn ($price, $id) => (float) ($variantMins[$id] ?? $price));
        $effectiveMaxes = $basePrices->map(fn ($price, $id) => (float) ($variantMaxes[$id] ?? $price));

        $brands = Brand::whereIn('id', (clone $base)->whereNotNull('brand_id')->pluck('brand_id'))
            ->withCount(['products as count' => fn ($q) => $q->visible()->whereIn('id', $ids)])
            ->get()->map(fn (Brand $b) => ['id' => $b->id, 'name' => $b->name, 'count' => $b->count])->values();

        $years = ProductMotorcycle::whereIn('product_id', $ids)->whereNotNull('moto_year_id')
            ->selectRaw('moto_year_id, COUNT(DISTINCT product_id) as count')->groupBy('moto_year_id')
            ->with('year:id,year')->get()
            ->filter(fn ($row) => $row->year)
            ->map(fn ($row) => ['id' => $row->moto_year_id, 'year' => $row->year->year, 'count' => $row->count])
            ->sortByDesc('year')->values();

        // The bike's manufacturer (Honda/BMW/KTM), from compatibility rows - NOT the `brands` facet above
        // (that one is the parts brand, e.g. Michelin). Same "Brand" label in the Filters sheet, different table.
        $motoBrands = ProductMotorcycle::whereIn('product_id', $ids)->whereNotNull('moto_brand_id')
            ->selectRaw('moto_brand_id, COUNT(DISTINCT product_id) as count')->groupBy('moto_brand_id')
            ->with('brand:id,name')->get()
            ->filter(fn ($row) => $row->brand)
            ->map(fn ($row) => ['id' => $row->moto_brand_id, 'name' => $row->brand->name, 'count' => $row->count])
            ->sortBy('name')->values();

        $attributes = ProductAttribute::whereIn('product_id', $ids)->get(['label', 'value', 'product_id'])
            ->groupBy('label')
            ->map(fn ($rows) => $rows->groupBy('value')
                ->map(fn ($g, $value) => ['value' => $value, 'count' => $g->pluck('product_id')->unique()->count()])
                ->values());

        $ratings = collect([5, 4, 3, 2, 1])
            ->map(fn ($min) => ['min' => $min, 'count' => (clone $base)->where('rating_avg', '>=', $min)->count()])
            ->filter(fn ($r) => $r['count'] > 0)->values();

        return response()->json(['data' => [
            'price'       => ['min' => $effectiveMins->min(), 'max' => $effectiveMaxes->max()],
            'brands'      => $brands,
            'moto_brands' => $motoBrands,
            'years'       => $years,
            'attributes'  => $attributes,
            'ratings'     => $ratings,
        ]]);
    }

    /**
     * @OA\Get(
     *     path="/api/marketplace/products/{idOrSlug}",
     *     summary="Product detail",
     *     description="Full gallery, specs (attributes), variants (color/size/...), bike compatibility and the selling shop. Access: Public.",
     *     tags={"Marketplace - Products"},
     *     @OA\Parameter(name="idOrSlug", in="path", required=true, description="Numeric id or slug", @OA\Schema(type="string", example="bilt-sprint-gloves")),
     *     @OA\Response(response=200, description="Product", @OA\JsonContent(
     *         example={"data": {
     *             "id": 22, "slug": "bilt-sprint-gloves", "reference": "MP-00022", "name": "BILT Sprint Gloves", "name_ar": null,
     *             "price": 34.99, "price_max": 120.99, "compare_at_price": 49.99, "condition": "new", "in_stock": true, "has_variants": true, "stock_quantity": 0,
     *             "cover_image_url": "https://api.dabapp.co/storage/marketplace/products/a1b2c3.jpg",
     *             "rating_avg": 4.8, "reviews_count": 108, "likes_count": 0, "is_featured": false,
     *             "description": "Aggressive, short cuff glove for warm months.", "description_ar": null,
     *             "category": {"id": 20, "slug": "gloves", "name": "Gloves", "name_ar": null},
     *             "brand": null,
     *             "images": {{"id": 101, "image_url": "https://api.dabapp.co/storage/marketplace/products/a1b2c3.jpg", "is_cover": true}},
     *             "attributes": {{"label": "Material", "label_ar": null, "value": "Leather", "value_ar": null}},
     *             "compatibility": {},
     *             "variants": {
     *                 {"id": 1, "sku": "MP-00022-BLK-XS", "option1_name": "Color", "option1_value": "Black", "color_hex": "#000000", "option2_name": "Size", "option2_value": "XS", "price": 34.99, "compare_at_price": 49.99, "in_stock": true, "image_url": null, "is_default": true},
     *                 {"id": 2, "sku": "MP-00022-GRN-XS", "option1_name": "Color", "option1_value": "Green", "color_hex": "#1F7A3F", "option2_name": "Size", "option2_value": "XS", "price": 120.99, "compare_at_price": null, "in_stock": true, "image_url": null, "is_default": false}
     *             },
     *             "vendor": {"id": 5, "slug": "riders-gear-jeddah", "shop_name": "Rider's Gear Jeddah", "shop_name_ar": null, "logo_url": null, "cover_image_url": null, "brand_color": null, "is_featured": false, "rating_avg": 4, "reviews_count": 1, "products_count": 10, "city": {"id": 2, "name": "Jeddah"}}
     *         }},
     *         @OA\Property(property="data", allOf={
     *             @OA\Schema(ref="#/components/schemas/MarketplaceProductCard"),
     *             @OA\Schema(
     *                 @OA\Property(property="description", type="string", nullable=true),
     *                 @OA\Property(property="description_ar", type="string", nullable=true),
     *                 @OA\Property(property="stock_quantity", type="integer", description="Ignore when has_variants is true - use each variant's own stock"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="id", type="integer"), @OA\Property(property="image_url", type="string"), @OA\Property(property="is_cover", type="boolean"))),
     *                 @OA\Property(property="attributes", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="label", type="string"), @OA\Property(property="label_ar", type="string", nullable=true),
     *                     @OA\Property(property="value", type="string"), @OA\Property(property="value_ar", type="string", nullable=true))),
     *                 @OA\Property(property="compatibility", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="is_universal", type="boolean"),
     *                     @OA\Property(property="brand", type="string", nullable=true), @OA\Property(property="model", type="string", nullable=true), @OA\Property(property="year", type="integer", nullable=true))),
     *                 @OA\Property(property="variants", type="array", @OA\Items(ref="#/components/schemas/MarketplaceProductVariant")),
     *                 @OA\Property(property="vendor", ref="#/components/schemas/MarketplaceVendorCard")
     *             )
     *         })
     *     )),
     *     @OA\Response(response=404, description="Not found, or not visible", @OA\JsonContent(example={"message": "Product not found"}))
     * )
     */
    public function show(string $idOrSlug): JsonResponse
    {
        $product = Product::visible()
            ->with([
                'vendor', 'vendor.city:id,name',
                'category:id,slug,name,name_ar', 'brand:id,name,logo_path',
                'images', 'attributes', 'variants',
                'compatibility.brand:id,name', 'compatibility.model:id,name', 'compatibility.year:id,year',
            ])
            ->where(ctype_digit($idOrSlug) ? 'id' : 'slug', $idOrSlug)
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $vendorCard = VendorController::card($product->vendor->loadCount(['products as products_count' => fn ($q) => $q->where('status', 'active')]));

        return response()->json(['data' => array_merge(self::card($product), [
            'description'    => $product->description,
            'description_ar' => $product->description_ar,
            'stock_quantity' => $product->stock_quantity,
            'images'         => $product->images->map(fn ($i) => ['id' => $i->id, 'image_url' => $i->image_url, 'is_cover' => (bool) $i->is_cover])->values(),
            'attributes'     => $product->attributes->map(fn ($a) => ['label' => $a->label, 'label_ar' => $a->label_ar, 'value' => $a->value, 'value_ar' => $a->value_ar])->values(),
            'compatibility'  => $product->compatibility->map(fn ($c) => [
                'is_universal' => (bool) $c->is_universal,
                'brand'        => $c->brand->name ?? null,
                'model'        => $c->model->name ?? null,
                'year'         => $c->year->year ?? null,
            ])->values(),
            'variants' => $product->variants->map(fn (ProductVariant $v) => self::variantRow($v))->values(),
            'vendor'   => $vendorCard,
        ])]);
    }

    /** Shared with ProductAdminController and WishlistController so every card looks the same. Needs `variants` eager-loaded to compute price range/stock; falls back to the plain product price/stock otherwise. */
    public static function card(Product $product): array
    {
        $pricing = $product->effectivePricing();

        return [
            'id'               => $product->id,
            'slug'             => $product->slug,
            'reference'        => $product->reference,
            'name'             => $product->name,
            'name_ar'          => $product->name_ar,
            'price'            => $pricing['price'],
            'price_max'        => $pricing['price_max'],
            'compare_at_price' => $pricing['compare_at_price'],
            'condition'        => $product->condition,
            'in_stock'         => $pricing['in_stock'],
            'has_variants'     => $product->relationLoaded('variants') ? $product->variants->isNotEmpty() : false,
            'cover_image_url'  => $product->coverImage?->image_url,
            'rating_avg'       => (float) $product->rating_avg,
            'reviews_count'    => (int) $product->reviews_count,
            'likes_count'      => (int) $product->likes_count,
            'is_featured'      => (bool) $product->is_featured,
            'vendor'           => $product->vendor ? [
                'id' => $product->vendor->id, 'slug' => $product->vendor->slug,
                'shop_name' => $product->vendor->shop_name, 'shop_name_ar' => $product->vendor->shop_name_ar,
            ] : null,
            'category' => $product->category ? [
                'id' => $product->category->id, 'slug' => $product->category->slug,
                'name' => $product->category->name, 'name_ar' => $product->category->name_ar,
            ] : null,
            'brand' => $product->brand ? [
                'id' => $product->brand->id, 'name' => $product->brand->name, 'logo_url' => $product->brand->logo_url,
            ] : null,
        ];
    }

    public static function variantRow(ProductVariant $v): array
    {
        return [
            'id'               => $v->id,
            'sku'              => $v->sku,
            'option1_name'     => $v->option1_name,
            'option1_value'    => $v->option1_value,
            'color_hex'        => $v->color_hex,
            'option2_name'     => $v->option2_name,
            'option2_value'    => $v->option2_value,
            'price'            => (float) $v->price,
            'compare_at_price' => $v->compare_at_price !== null ? (float) $v->compare_at_price : null,
            'in_stock'         => $v->in_stock,
            'image_url'        => $v->image_url,
            'is_default'       => (bool) $v->is_default,
        ];
    }
}
