<?php

namespace App\Http\Controllers\Admin\Marketplace;

use App\Http\Controllers\Marketplace\ProductController;
use App\Models\Marketplace\OrderItem;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Admin Marketplace - Products",
 *     description="Catalog management. No vendor self-service yet (see backlog): the admin creates and edits every product on a shop's behalf. images / attributes / compatibility / variants are plain arrays on the create/update call - each sent array REPLACES the product's current set (send the full list every time, not just what changed). A product with at least one variants row is sold by variant (color/size/...): each variant carries its own price/compare_at_price/stock, and the product's own price/stock_quantity/compare_at_price are then ignored by the storefront. image_path values are URLs from POST /api/marketplace/upload-image (type=product), not files."
 * )
 */
class ProductAdminController extends MarketplaceAdminController
{
    private const STATUSES = ['draft', 'pending_review', 'active', 'inactive', 'rejected'];
    private const CONDITIONS = ['new', 'used'];

    /**
     * @OA\Get(
     *     path="/api/admin/marketplace/products",
     *     summary="List products (Admin)",
     *     description="All statuses, every vendor. per_page empty = everything, no pagination.",
     *     tags={"Admin Marketplace - Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, description="name / name_ar / reference contains", @OA\Schema(type="string")),
     *     @OA\Parameter(name="vendor_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="category_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="brand_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"draft","pending_review","active","inactive","rejected"})),
     *     @OA\Parameter(name="condition", in="query", required=false, @OA\Schema(type="string", enum={"new","used"})),
     *     @OA\Parameter(name="is_featured", in="query", required=false, @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(type="string", enum={"newest","name","price_low","price_high"}, default="newest")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Products", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdminMarketplaceProduct")),
     *         @OA\Property(property="meta", type="object", description="Only when paginated",
     *             @OA\Property(property="current_page", type="integer"), @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="last_page", type="integer"), @OA\Property(property="total", type="integer"))
     *     )),
     *     @OA\Response(response=403, description="Not an admin")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $query = Product::query()
            ->with(['vendor:id,slug,shop_name', 'category:id,slug,name', 'brand:id,name', 'coverImage', 'variants'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->input('search') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('name_ar', 'like', $term)->orWhere('reference', 'like', $term));
            })
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', (int) $request->input('vendor_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', (int) $request->input('category_id')))
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', (int) $request->input('brand_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('condition'), fn ($q) => $q->where('condition', $request->input('condition')))
            ->when($request->filled('is_featured'), fn ($q) => $q->where('is_featured', $request->boolean('is_featured')));

        match ($request->input('sort')) {
            'name'       => $query->orderBy('name'),
            'price_low'  => $query->orderBy('price'),
            'price_high' => $query->orderByDesc('price'),
            default      => $query->orderByDesc('id'),
        };

        $perPage = $this->perPage($request);

        if ($perPage) {
            $page = $query->paginate($perPage);
            $page->getCollection()->transform(fn (Product $p) => $this->present($p));

            return response()->json($this->paginated($page));
        }

        return response()->json(['data' => $query->get()->map(fn (Product $p) => $this->present($p))->values()]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/marketplace/products/stats",
     *     summary="Product counters",
     *     description="Totals per status, for the tabs of the products screen.",
     *     tags={"Admin Marketplace - Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Counters", @OA\JsonContent(
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="total", type="integer", example=58), @OA\Property(property="draft", type="integer", example=3),
     *             @OA\Property(property="pending_review", type="integer", example=0), @OA\Property(property="active", type="integer", example=54),
     *             @OA\Property(property="inactive", type="integer", example=1), @OA\Property(property="rejected", type="integer", example=0))
     *     ))
     * )
     */
    public function stats(): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $counts = Product::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $data = ['total' => (int) $counts->sum()];
        foreach (self::STATUSES as $status) {
            $data[$status] = (int) ($counts[$status] ?? 0);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/marketplace/products/{id}",
     *     summary="Product detail (Admin)",
     *     description="Full record: images, attributes and bike compatibility, any status.",
     *     tags={"Admin Marketplace - Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceProductDetail"))),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $product = Product::with([
            'vendor:id,slug,shop_name', 'category:id,slug,name', 'brand:id,name',
            'images', 'attributes', 'variants', 'compatibility.brand:id,name', 'compatibility.model:id,name', 'compatibility.year:id,year',
        ])->find($id);

        return $product
            ? response()->json(['data' => $this->present($product, true)])
            : response()->json(['message' => 'Product not found'], 404);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/marketplace/products",
     *     summary="Create a product",
     *     description="reference is the SKU (one product = no variants). slug is generated from name when omitted. status defaults to draft; setting it to active stamps published_at. To add photos, call POST /api/marketplace/upload-image (type=product) first and send the URLs it returns in images[].image_path.",
     *     tags={"Admin Marketplace - Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"vendor_id","category_id","reference","name","price"},
     *         @OA\Property(property="vendor_id", type="integer", example=4),
     *         @OA\Property(property="category_id", type="integer", example=3),
     *         @OA\Property(property="brand_id", type="integer", nullable=true, example=4, description="Parts brand (Michelin), not the bike's manufacturer"),
     *         @OA\Property(property="reference", type="string", example="MP-00021", description="SKU, unique"),
     *         @OA\Property(property="slug", type="string", example="michelin-pilot-road-5-120-70-17"),
     *         @OA\Property(property="name", type="string", example="Michelin Pilot Road 5 120/70-17"),
     *         @OA\Property(property="name_ar", type="string"),
     *         @OA\Property(property="description", type="string", example="Sport touring front tyre, excellent wet grip."),
     *         @OA\Property(property="description_ar", type="string"),
     *         @OA\Property(property="price", type="number", example=420, description="Base/fallback price. Ignored by the storefront once at least one variants[] row exists - set variants[].price instead."),
     *         @OA\Property(property="compare_at_price", type="number", nullable=true, example=499, description="Strikethrough original price. Same ignore rule as price once variants exist."),
     *         @OA\Property(property="stock_quantity", type="integer", example=14, description="Ignored once variants exist - stock lives on each variant."),
     *         @OA\Property(property="condition", type="string", enum={"new","used"}, example="new"),
     *         @OA\Property(property="status", type="string", enum={"draft","pending_review","active","inactive","rejected"}, example="active"),
     *         @OA\Property(property="status_reason", type="string", nullable=true),
     *         @OA\Property(property="is_featured", type="boolean", example=false),
     *         @OA\Property(property="images", type="array", @OA\Items(type="object",
     *             @OA\Property(property="image_path", type="string", example="https://api.dabapp.co/storage/marketplace/products/a1b2c3.jpg"),
     *             @OA\Property(property="is_cover", type="boolean", example=true),
     *             @OA\Property(property="order_position", type="integer", example=0))),
     *         @OA\Property(property="attributes", type="array", @OA\Items(type="object",
     *             @OA\Property(property="label", type="string", example="Width"), @OA\Property(property="label_ar", type="string", nullable=true),
     *             @OA\Property(property="value", type="string", example="120 mm"), @OA\Property(property="value_ar", type="string", nullable=true))),
     *         @OA\Property(property="compatibility", type="array", description="One row per bike it fits, or one row with is_universal=true for a part that fits everything",
     *             @OA\Items(type="object",
     *                 @OA\Property(property="moto_brand_id", type="integer", nullable=true, example=2),
     *                 @OA\Property(property="moto_model_id", type="integer", nullable=true, example=15),
     *                 @OA\Property(property="moto_year_id", type="integer", nullable=true, example=140),
     *                 @OA\Property(property="is_universal", type="boolean", example=false))),
     *         @OA\Property(property="variants", type="array", description="Up to 2 option dimensions (e.g. Color+Size, or Wheel Location+Tyre Size). Presence of even one row switches the product to variant pricing/stock.",
     *             @OA\Items(type="object", required={"sku","price"},
     *                 @OA\Property(property="sku", type="string", example="MP-00022-BLK-XS"),
     *                 @OA\Property(property="option1_name", type="string", nullable=true, example="Color"),
     *                 @OA\Property(property="option1_value", type="string", nullable=true, example="Black"),
     *                 @OA\Property(property="color_hex", type="string", nullable=true, example="#000000", description="Swatch colour, only when option1/2 is a colour"),
     *                 @OA\Property(property="option2_name", type="string", nullable=true, example="Size"),
     *                 @OA\Property(property="option2_value", type="string", nullable=true, example="XS"),
     *                 @OA\Property(property="price", type="number", example=34.99),
     *                 @OA\Property(property="compare_at_price", type="number", nullable=true, example=49.99),
     *                 @OA\Property(property="stock_quantity", type="integer", example=10),
     *                 @OA\Property(property="image_path", type="string", nullable=true),
     *                 @OA\Property(property="is_default", type="boolean", example=true, description="Pre-selected on the product page")))
     *     )),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Product created successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceProductDetail")
     *     )),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $validator = Validator::make($request->all(), $this->rules($request) + [
            'vendor_id'   => ['required', 'integer', Rule::exists('marketplace_vendors', 'id')->whereNull('deleted_at')],
            'category_id' => ['required', 'integer', Rule::exists('marketplace_categories', 'id')->whereNull('deleted_at')],
            'reference'   => ['required', 'string', 'max:255', Rule::unique('marketplace_products', 'reference')],
            'name'        => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = $request->input('status', 'draft');

        $product = DB::transaction(function () use ($request, $status) {
            $product = Product::create([
                'vendor_id'       => (int) $request->input('vendor_id'),
                'category_id'     => (int) $request->input('category_id'),
                'brand_id'        => $request->input('brand_id') ?: null,
                'reference'       => $request->input('reference'),
                'slug'            => $request->filled('slug') ? $request->input('slug') : $this->uniqueSlug(Product::class, $request->input('name')),
                'name'            => $request->input('name'),
                'name_ar'         => $request->input('name_ar'),
                'description'     => $request->input('description'),
                'description_ar'  => $request->input('description_ar'),
                'price'           => $request->input('price'),
                'compare_at_price' => $request->filled('compare_at_price') ? $request->input('compare_at_price') : null,
                'stock_quantity'  => (int) $request->input('stock_quantity', 0),
                'condition'       => $request->input('condition', 'new'),
                'status'          => $status,
                'status_reason'   => $request->input('status_reason'),
                'is_featured'     => $request->boolean('is_featured'),
                'published_at'    => $status === 'active' ? now() : null,
            ]);

            $this->syncChildren($product, $request);

            return $product;
        });

        return response()->json([
            'message' => 'Product created successfully',
            'data'    => $this->present($this->loadFull($product), true),
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/marketplace/products/{id}",
     *     summary="Update a product",
     *     description="Send only the fields to change. images / attributes / compatibility: send the array to REPLACE the full current set, or omit the key entirely to leave it untouched (send an empty array to clear it). Setting status to active for the first time stamps published_at.",
     *     tags={"Admin Marketplace - Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="vendor_id", type="integer"),
     *         @OA\Property(property="category_id", type="integer"),
     *         @OA\Property(property="brand_id", type="integer", nullable=true),
     *         @OA\Property(property="reference", type="string"),
     *         @OA\Property(property="slug", type="string"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="name_ar", type="string"),
     *         @OA\Property(property="description", type="string"),
     *         @OA\Property(property="description_ar", type="string"),
     *         @OA\Property(property="price", type="number"),
     *         @OA\Property(property="compare_at_price", type="number", nullable=true),
     *         @OA\Property(property="stock_quantity", type="integer"),
     *         @OA\Property(property="condition", type="string", enum={"new","used"}),
     *         @OA\Property(property="status", type="string", enum={"draft","pending_review","active","inactive","rejected"}),
     *         @OA\Property(property="status_reason", type="string", nullable=true),
     *         @OA\Property(property="is_featured", type="boolean"),
     *         @OA\Property(property="images", type="array", @OA\Items(type="object",
     *             @OA\Property(property="image_path", type="string"), @OA\Property(property="is_cover", type="boolean"), @OA\Property(property="order_position", type="integer"))),
     *         @OA\Property(property="attributes", type="array", @OA\Items(type="object",
     *             @OA\Property(property="label", type="string"), @OA\Property(property="label_ar", type="string", nullable=true),
     *             @OA\Property(property="value", type="string"), @OA\Property(property="value_ar", type="string", nullable=true))),
     *         @OA\Property(property="compatibility", type="array", @OA\Items(type="object",
     *             @OA\Property(property="moto_brand_id", type="integer", nullable=true), @OA\Property(property="moto_model_id", type="integer", nullable=true),
     *             @OA\Property(property="moto_year_id", type="integer", nullable=true), @OA\Property(property="is_universal", type="boolean"))),
     *         @OA\Property(property="variants", type="array", description="Full replace, same shape as create. An empty array removes all variants and reverts the product to its own price/stock_quantity.",
     *             @OA\Items(type="object", required={"sku","price"},
     *                 @OA\Property(property="sku", type="string"), @OA\Property(property="option1_name", type="string", nullable=true), @OA\Property(property="option1_value", type="string", nullable=true),
     *                 @OA\Property(property="color_hex", type="string", nullable=true), @OA\Property(property="option2_name", type="string", nullable=true), @OA\Property(property="option2_value", type="string", nullable=true),
     *                 @OA\Property(property="price", type="number"), @OA\Property(property="compare_at_price", type="number", nullable=true),
     *                 @OA\Property(property="stock_quantity", type="integer"), @OA\Property(property="image_path", type="string", nullable=true), @OA\Property(property="is_default", type="boolean")))
     *     )),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Product updated successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceProductDetail")
     *     )),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $product = Product::find($id);
        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($request, $id) + [
            'vendor_id'   => ['sometimes', 'required', 'integer', Rule::exists('marketplace_vendors', 'id')->whereNull('deleted_at')],
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('marketplace_categories', 'id')->whereNull('deleted_at')],
            'reference'   => ['sometimes', 'required', 'string', 'max:255', Rule::unique('marketplace_products', 'reference')->ignore($id)],
            'name'        => 'sometimes|required|string|max:255',
            'price'       => 'sometimes|required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::transaction(function () use ($request, $product) {
            $data = $request->only([
                'brand_id', 'slug', 'name', 'name_ar', 'description', 'description_ar',
                'price', 'stock_quantity', 'condition', 'status_reason',
            ]);

            if ($request->has('compare_at_price')) {
                $data['compare_at_price'] = $request->filled('compare_at_price') ? $request->input('compare_at_price') : null;
            }
            if ($request->has('vendor_id')) {
                $data['vendor_id'] = (int) $request->input('vendor_id');
            }
            if ($request->has('category_id')) {
                $data['category_id'] = (int) $request->input('category_id');
            }
            if ($request->has('is_featured')) {
                $data['is_featured'] = $request->boolean('is_featured');
            }
            if ($request->has('status')) {
                $data['status'] = $request->input('status');
                if ($data['status'] === 'active' && ! $product->published_at) {
                    $data['published_at'] = now();
                }
            }

            $product->update($data);

            $this->syncChildren($product, $request);
        });

        return response()->json([
            'message' => 'Product updated successfully',
            'data'    => $this->present($this->loadFull($product->fresh()), true),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/marketplace/products/{id}",
     *     summary="Delete a product",
     *     description="Soft delete. Refused with 409 HAS_ORDERS once the product has sold something (set it to inactive instead).",
     *     tags={"Admin Marketplace - Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(@OA\Property(property="message", type="string", example="Product deleted successfully"))),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=409, description="Has orders", @OA\JsonContent(ref="#/components/schemas/ConflictError"))
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $product = Product::find($id);
        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        if (OrderItem::where('product_id', $id)->exists()) {
            return response()->json(['message' => 'Product has orders and cannot be deleted, set it to inactive instead', 'code' => 'HAS_ORDERS'], 409);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    // ------------------------------------------------------------------ internals

    private function rules(Request $request, ?int $ignoreId = null): array
    {
        // Variants are deleted and recreated on every sync (syncChildren), so re-sending an unchanged SKU for the
        // SAME product must not look like a collision: only another product's variant counts as a conflict.
        $variantSkuRule = Rule::unique('marketplace_product_variants', 'sku');
        if ($ignoreId) {
            $variantSkuRule = $variantSkuRule->where(fn ($q) => $q->where('product_id', '!=', $ignoreId));
        }

        return [
            'brand_id'         => ['nullable', 'integer', Rule::exists('marketplace_brands', 'id')->whereNull('deleted_at')],
            'slug'             => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('marketplace_products', 'slug')->ignore($ignoreId)],
            'name_ar'          => 'nullable|string|max:255',
            'description'      => 'nullable|string',
            'description_ar'   => 'nullable|string',
            'compare_at_price' => 'nullable|numeric|min:0',
            'stock_quantity'   => 'nullable|integer|min:0',
            'condition'        => ['nullable', Rule::in(self::CONDITIONS)],
            'status'           => ['nullable', Rule::in(self::STATUSES)],
            'status_reason'    => 'nullable|string|max:1000',
            'is_featured'      => 'nullable|boolean',

            'images'                   => 'nullable|array',
            'images.*.image_path'      => $this->imagePathRule(),
            'images.*.is_cover'        => 'nullable|boolean',
            'images.*.order_position'  => 'nullable|integer|min:0',

            'attributes'                  => 'nullable|array',
            'attributes.*.label'          => 'required_with:attributes|string|max:255',
            'attributes.*.label_ar'       => 'nullable|string|max:255',
            'attributes.*.value'          => 'required_with:attributes|string|max:255',
            'attributes.*.value_ar'       => 'nullable|string|max:255',
            'attributes.*.order_position' => 'nullable|integer|min:0',

            'compatibility'                     => 'nullable|array',
            'compatibility.*.moto_brand_id'      => ['nullable', 'integer', Rule::exists('motorcycle_brands', 'id')],
            'compatibility.*.moto_model_id'      => ['nullable', 'integer', Rule::exists('motorcycle_models', 'id')],
            'compatibility.*.moto_year_id'       => ['nullable', 'integer', Rule::exists('motorcycle_years', 'id')],
            'compatibility.*.is_universal'       => 'nullable|boolean',

            'variants'                        => 'nullable|array',
            'variants.*.sku'                  => ['required_with:variants', 'string', 'max:255', 'distinct', $variantSkuRule],
            'variants.*.option1_name'         => 'nullable|string|max:100',
            'variants.*.option1_value'        => 'nullable|string|max:100',
            'variants.*.color_hex'            => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'variants.*.option2_name'         => 'nullable|string|max:100',
            'variants.*.option2_value'        => 'nullable|string|max:100',
            'variants.*.price'                => 'required_with:variants|numeric|min:0',
            'variants.*.compare_at_price'      => 'nullable|numeric|min:0',
            'variants.*.stock_quantity'        => 'nullable|integer|min:0',
            'variants.*.image_path'            => $this->imagePathRule(),
            'variants.*.is_default'            => 'nullable|boolean',
        ];
    }

    /** images / attributes / compatibility are full-replace: present in the request = delete the old set, insert the new one. */
    private function syncChildren(Product $product, Request $request): void
    {
        if ($request->has('images')) {
            $product->images()->delete();

            $hasCover = false;
            foreach ($request->input('images', []) as $i => $img) {
                $isCover = (bool) ($img['is_cover'] ?? false);
                $hasCover = $hasCover || $isCover;

                $product->images()->create([
                    'image_path'     => $img['image_path'],
                    'is_cover'       => $isCover,
                    'order_position' => $img['order_position'] ?? $i,
                ]);
            }

            if (! $hasCover) {
                $product->images()->orderBy('order_position')->first()?->update(['is_cover' => true]);
            }
        }

        if ($request->has('attributes')) {
            $product->attributes()->delete();

            foreach ($request->input('attributes', []) as $i => $attr) {
                $product->attributes()->create([
                    'label'          => $attr['label'],
                    'label_ar'       => $attr['label_ar'] ?? null,
                    'value'          => $attr['value'],
                    'value_ar'       => $attr['value_ar'] ?? null,
                    'order_position' => $attr['order_position'] ?? $i,
                ]);
            }
        }

        if ($request->has('compatibility')) {
            $product->compatibility()->delete();

            foreach ($request->input('compatibility', []) as $c) {
                $product->compatibility()->create([
                    'moto_brand_id' => $c['moto_brand_id'] ?? null,
                    'moto_model_id' => $c['moto_model_id'] ?? null,
                    'moto_year_id'  => $c['moto_year_id'] ?? null,
                    'is_universal'  => (bool) ($c['is_universal'] ?? false),
                ]);
            }
        }

        if ($request->has('variants')) {
            // Hard delete, not soft: `sku` is DB-unique, and a full-replace means these rows are genuinely gone -
            // a soft-deleted row would still collide if the same sku is sent again on the very next update.
            $product->variants()->forceDelete();

            $hasDefault = false;
            foreach ($request->input('variants', []) as $i => $v) {
                $isDefault = (bool) ($v['is_default'] ?? false);
                $hasDefault = $hasDefault || $isDefault;

                $product->variants()->create([
                    'sku'              => $v['sku'],
                    'option1_name'     => $v['option1_name'] ?? null,
                    'option1_value'    => $v['option1_value'] ?? null,
                    'color_hex'        => $v['color_hex'] ?? null,
                    'option2_name'     => $v['option2_name'] ?? null,
                    'option2_value'    => $v['option2_value'] ?? null,
                    'price'            => $v['price'],
                    'compare_at_price' => $v['compare_at_price'] ?? null,
                    'stock_quantity'   => (int) ($v['stock_quantity'] ?? 0),
                    'image_path'       => $v['image_path'] ?? null,
                    'is_default'       => $isDefault,
                    'order_position'   => $i,
                ]);
            }

            if (! $hasDefault) {
                $product->variants()->orderBy('order_position')->first()?->update(['is_default' => true]);
            }
        }
    }

    private function loadFull(Product $product): Product
    {
        return $product->load([
            'vendor:id,slug,shop_name', 'category:id,slug,name', 'brand:id,name',
            'images', 'attributes', 'variants', 'compatibility.brand:id,name', 'compatibility.model:id,name', 'compatibility.year:id,year',
        ]);
    }

    /** Admin view of a product. $full adds images/attributes/compatibility (detail); the list only gets a cover thumbnail. */
    private function present(Product $product, bool $full = false): array
    {
        $pricing = $product->effectivePricing();

        $data = [
            'id'               => $product->id,
            'vendor'           => $product->vendor ? ['id' => $product->vendor->id, 'slug' => $product->vendor->slug, 'shop_name' => $product->vendor->shop_name] : null,
            'category'         => $product->category ? ['id' => $product->category->id, 'slug' => $product->category->slug, 'name' => $product->category->name] : null,
            'brand'            => $product->brand ? ['id' => $product->brand->id, 'name' => $product->brand->name] : null,
            'reference'        => $product->reference,
            'slug'             => $product->slug,
            'name'             => $product->name,
            'name_ar'          => $product->name_ar,
            'description'      => $product->description,
            'description_ar'   => $product->description_ar,
            'price'            => $pricing['price'],
            'price_max'        => $pricing['price_max'],
            'compare_at_price' => $pricing['compare_at_price'],
            'in_stock'         => $pricing['in_stock'],
            'stock_quantity'   => $product->stock_quantity,
            'has_variants'     => $product->relationLoaded('variants') ? $product->variants->isNotEmpty() : false,
            'condition'        => $product->condition,
            'status'           => $product->status,
            'status_reason'    => $product->status_reason,
            'is_featured'      => (bool) $product->is_featured,
            'published_at'     => $product->published_at,
            'rating_avg'       => (float) $product->rating_avg,
            'reviews_count'    => $product->reviews_count,
            'likes_count'      => $product->likes_count,
            'sales_count'      => $product->sales_count,
            'created_at'       => $product->created_at,
        ];

        if ($full) {
            $data['images'] = $product->images->map(fn ($i) => [
                'id' => $i->id, 'image_url' => $i->image_url, 'is_cover' => (bool) $i->is_cover, 'order_position' => $i->order_position,
            ])->values();
            $data['attributes'] = $product->attributes->map(fn ($a) => [
                'id' => $a->id, 'label' => $a->label, 'label_ar' => $a->label_ar, 'value' => $a->value, 'value_ar' => $a->value_ar,
            ])->values();
            $data['compatibility'] = $product->compatibility->map(fn ($c) => [
                'id' => $c->id, 'is_universal' => (bool) $c->is_universal,
                'moto_brand_id' => $c->moto_brand_id, 'brand' => $c->brand->name ?? null,
                'moto_model_id' => $c->moto_model_id, 'model' => $c->model->name ?? null,
                'moto_year_id'  => $c->moto_year_id, 'year' => $c->year->year ?? null,
            ])->values();
            $data['variants'] = $product->variants->map(fn (ProductVariant $v) => array_merge(['id' => $v->id], ProductController::variantRow($v)))->values();
        } else {
            $data['cover_image_url'] = $product->coverImage?->image_url;
        }

        return $data;
    }
}
