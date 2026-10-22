<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketplace\Concerns\ManagesProductChildren;
use App\Http\Controllers\Marketplace\Concerns\RequiresActiveVendor;
use App\Models\Marketplace\OrderItem;
use App\Models\Marketplace\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Marketplace - Vendor products",
 *     description="A vendor manages their own catalog from the app. Only an active shop can sell: a pending/rejected/suspended shop gets a structured error instead (same codes as the vendor account endpoints). An already-approved shop is trusted to publish directly: a new product defaults to status=active unless the vendor explicitly asks for draft or pending_review instead (e.g. to prepare it before going live, or to request an extra admin look). An admin can still set inactive/rejected at any time from the Admin Panel (pre- or post-publish moderation). vendor_id and is_featured are never settable here. images / attributes / compatibility / variants follow the same full-replace convention as the admin endpoint: each array sent REPLACES the product's current set. Access: User (Bearer JWT)."
 * )
 */
class VendorProductController extends Controller
{
    use ManagesProductChildren;
    use RequiresActiveVendor;

    private const CONDITIONS = ['new', 'used'];
    private const VENDOR_STATUSES = ['draft', 'pending_review', 'active'];

    /**
     * @OA\Get(
     *     path="/api/marketplace/vendor/products",
     *     summary="List my products",
     *     description="Every status, own shop only (the storefront's GET /marketplace/products only ever shows active ones). per_page empty = everything, no pagination.",
     *     tags={"Marketplace - Vendor products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, description="name / name_ar / reference contains", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"draft","pending_review","active","inactive","rejected"})),
     *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(type="string", enum={"newest","name","price_low","price_high"}, default="newest")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="My products", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/VendorMarketplaceProduct")),
     *         @OA\Property(property="meta", type="object", description="Only when paginated")
     *     )),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, description="Shop not active", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict")),
     *     @OA\Response(response=404, description="No shop", @OA\JsonContent(example={"message": "You do not have a vendor account", "code": "NO_VENDOR"}))
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $vendor = $this->myActiveVendorOrError();
        if ($vendor instanceof JsonResponse) {
            return $vendor;
        }

        $query = Product::query()
            ->where('vendor_id', $vendor->id)
            ->with(['category:id,slug,name', 'brand:id,name', 'coverImage', 'variants'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->input('search') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('name_ar', 'like', $term)->orWhere('reference', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')));

        match ($request->input('sort')) {
            'name'       => $query->orderBy('name'),
            'price_low'  => $query->orderBy('price'),
            'price_high' => $query->orderByDesc('price'),
            default      => $query->orderByDesc('id'),
        };

        $perPage = $request->input('per_page');
        $perPage = ($perPage === null || $perPage === '') ? null : min(max((int) $perPage, 1), 100);

        if ($perPage) {
            $page = $query->paginate($perPage);
            $page->getCollection()->transform(fn (Product $p) => $this->presentProduct($p));

            return response()->json([
                'data' => $page->items(),
                'meta' => [
                    'current_page' => $page->currentPage(), 'per_page' => $page->perPage(),
                    'last_page' => $page->lastPage(), 'total' => $page->total(),
                ],
            ]);
        }

        return response()->json(['data' => $query->get()->map(fn (Product $p) => $this->presentProduct($p))->values()]);
    }

    /**
     * @OA\Get(
     *     path="/api/marketplace/vendor/products/{id}",
     *     summary="My product detail",
     *     tags={"Marketplace - Vendor products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/VendorMarketplaceProductDetail"))),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, description="Shop not active", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict")),
     *     @OA\Response(response=404, description="No shop, or not your product")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $vendor = $this->myActiveVendorOrError();
        if ($vendor instanceof JsonResponse) {
            return $vendor;
        }

        $product = Product::where('vendor_id', $vendor->id)->find($id);
        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json(['data' => $this->presentProduct($this->loadProductFull($product), true)]);
    }

    /**
     * @OA\Post(
     *     path="/api/marketplace/vendor/products",
     *     summary="Create a product",
     *     description="reference is the SKU (one product = no variants) - generated from your product name when omitted (uppercased, -2/-3... appended on a collision), same idea as slug. status defaults to active (an approved shop publishes directly) - send draft to hold it back, or pending_review to request an admin look first; inactive/rejected are refused here (422), only an admin sets those. To add photos, call POST /api/marketplace/upload-image (type=product) first and send the URLs it returns in images[].image_path.",
     *     tags={"Marketplace - Vendor products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"category_id","name","price"},
     *         @OA\Property(property="category_id", type="integer", example=3),
     *         @OA\Property(property="brand_id", type="integer", nullable=true, example=4, description="Parts brand (Michelin), not the bike's manufacturer"),
     *         @OA\Property(property="reference", type="string", example="MP-00021", description="SKU, unique. Generated from name when omitted."),
     *         @OA\Property(property="slug", type="string"),
     *         @OA\Property(property="name", type="string", example="Michelin Pilot Road 5 120/70-17"),
     *         @OA\Property(property="name_ar", type="string"),
     *         @OA\Property(property="description", type="string"),
     *         @OA\Property(property="description_ar", type="string"),
     *         @OA\Property(property="price", type="number", example=420),
     *         @OA\Property(property="compare_at_price", type="number", nullable=true),
     *         @OA\Property(property="stock_quantity", type="integer", example=14),
     *         @OA\Property(property="condition", type="string", enum={"new","used"}, example="new"),
     *         @OA\Property(property="status", type="string", enum={"draft","pending_review","active"}, example="active", description="Defaults to active when omitted"),
     *         @OA\Property(property="images", type="array", @OA\Items(type="object",
     *             @OA\Property(property="image_path", type="string"), @OA\Property(property="is_cover", type="boolean"), @OA\Property(property="order_position", type="integer"))),
     *         @OA\Property(property="attributes", type="array", @OA\Items(type="object",
     *             @OA\Property(property="label", type="string"), @OA\Property(property="label_ar", type="string", nullable=true),
     *             @OA\Property(property="value", type="string"), @OA\Property(property="value_ar", type="string", nullable=true))),
     *         @OA\Property(property="compatibility", type="array", @OA\Items(type="object",
     *             @OA\Property(property="moto_brand_id", type="integer", nullable=true), @OA\Property(property="bike_type_id", type="integer", nullable=true),
     *             @OA\Property(property="moto_model_id", type="integer", nullable=true), @OA\Property(property="moto_year_id", type="integer", nullable=true),
     *             @OA\Property(property="is_universal", type="boolean"))),
     *         @OA\Property(property="variants", type="array", @OA\Items(type="object", required={"sku","price"},
     *             @OA\Property(property="sku", type="string"), @OA\Property(property="option1_name", type="string", nullable=true), @OA\Property(property="option1_value", type="string", nullable=true),
     *             @OA\Property(property="color_hex", type="string", nullable=true), @OA\Property(property="option2_name", type="string", nullable=true), @OA\Property(property="option2_value", type="string", nullable=true),
     *             @OA\Property(property="price", type="number"), @OA\Property(property="compare_at_price", type="number", nullable=true),
     *             @OA\Property(property="stock_quantity", type="integer"), @OA\Property(property="image_path", type="string", nullable=true), @OA\Property(property="is_default", type="boolean")))
     *     )),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Product created and published"),
     *         @OA\Property(property="data", ref="#/components/schemas/VendorMarketplaceProductDetail")
     *     )),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, description="Shop not active", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict")),
     *     @OA\Response(response=422, description="Validation error (also used for status=inactive/rejected)", @OA\JsonContent(ref="#/components/schemas/MarketplaceValidationError"))
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $vendor = $this->myActiveVendorOrError();
        if ($vendor instanceof JsonResponse) {
            return $vendor;
        }

        $validator = Validator::make($request->all(), $this->rules() + [
            'category_id' => ['required', 'integer', Rule::exists('marketplace_categories', 'id')->whereNull('deleted_at')],
            'reference'   => ['nullable', 'string', 'max:255', Rule::unique('marketplace_products', 'reference')],
            'name'        => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // An already-approved shop is trusted to publish directly — new products go live right
        // away unless the vendor deliberately asks for draft/pending_review instead.
        $status = $request->input('status', 'active');

        $product = DB::transaction(function () use ($request, $status, $vendor) {
            $product = Product::create([
                'vendor_id'        => $vendor->id,
                'category_id'      => (int) $request->input('category_id'),
                'brand_id'         => $request->input('brand_id') ?: null,
                'reference'        => $request->filled('reference') ? $request->input('reference') : $this->uniqueReference($request->input('name')),
                'slug'             => $request->filled('slug') ? $request->input('slug') : $this->uniqueSlug($request->input('name')),
                'name'             => $request->input('name'),
                'name_ar'          => $request->input('name_ar'),
                'description'      => $request->input('description'),
                'description_ar'   => $request->input('description_ar'),
                'price'            => $request->input('price'),
                'compare_at_price' => $request->filled('compare_at_price') ? $request->input('compare_at_price') : null,
                'stock_quantity'   => (int) $request->input('stock_quantity', 0),
                'condition'        => $request->input('condition', 'new'),
                'status'           => $status,
            ]);

            $this->syncProductChildren($product, $request);

            return $product;
        });

        $message = match ($status) {
            'pending_review' => 'Product created, waiting for review',
            'draft'           => 'Product created as draft',
            default           => 'Product created and published',
        };

        return response()->json([
            'message' => $message,
            'data'    => $this->presentProduct($this->loadProductFull($product), true),
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/marketplace/vendor/products/{id}",
     *     summary="Update my product",
     *     description="Send only the fields to change. images / attributes / compatibility / variants: send the array to REPLACE the full current set, or omit the key to leave it untouched. status (if sent) accepts draft/pending_review/active - e.g. resubmit a REJECTED product by sending status=pending_review (sending status=active directly on a rejected product is refused, 422: it needs a fresh admin look first), it also clears status_reason. Editing an already-active product's other fields (price, stock...) does not unpublish it: only touch status when you mean to change it.",
     *     tags={"Marketplace - Vendor products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Product updated successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/VendorMarketplaceProductDetail")
     *     )),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, description="Shop not active", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict")),
     *     @OA\Response(response=404, description="No shop, or not your product"),
     *     @OA\Response(response=422, description="Validation error (also used for status=inactive/rejected, or status=active on a rejected product)", @OA\JsonContent(ref="#/components/schemas/MarketplaceValidationError"))
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $vendor = $this->myActiveVendorOrError();
        if ($vendor instanceof JsonResponse) {
            return $vendor;
        }

        $product = Product::where('vendor_id', $vendor->id)->find($id);
        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($id) + [
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('marketplace_categories', 'id')->whereNull('deleted_at')],
            'reference'   => ['sometimes', 'required', 'string', 'max:255', Rule::unique('marketplace_products', 'reference')->ignore($id)],
            'name'        => 'sometimes|required|string|max:255',
            'price'       => 'sometimes|required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // A rejected product still needs a fresh admin look before it can go live again — a
        // vendor can resubmit it (status=pending_review), not silently republish it themselves.
        if ($product->status === 'rejected' && $request->input('status') === 'active') {
            return response()->json(['errors' => ['status' => ['A rejected product must be resubmitted for review (status=pending_review), not republished directly.']]], 422);
        }

        DB::transaction(function () use ($request, $product) {
            $data = $request->only([
                'category_id', 'brand_id', 'slug', 'name', 'name_ar', 'description', 'description_ar',
                'price', 'stock_quantity', 'condition',
            ]);

            if ($request->has('compare_at_price')) {
                $data['compare_at_price'] = $request->filled('compare_at_price') ? $request->input('compare_at_price') : null;
            }
            if ($request->has('status')) {
                $data['status'] = $request->input('status');
                $data['status_reason'] = null;
            }

            $product->update($data);

            $this->syncProductChildren($product, $request);
        });

        return response()->json([
            'message' => 'Product updated successfully',
            'data'    => $this->presentProduct($this->loadProductFull($product->fresh()), true),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/marketplace/vendor/products/{id}",
     *     summary="Delete my product",
     *     description="Soft delete. Refused with 409 HAS_ORDERS once the product has sold something.",
     *     tags={"Marketplace - Vendor products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(@OA\Property(property="message", type="string", example="Product deleted successfully"))),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, description="Shop not active", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict")),
     *     @OA\Response(response=404, description="No shop, or not your product"),
     *     @OA\Response(response=409, description="Has orders", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict"))
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $vendor = $this->myActiveVendorOrError();
        if ($vendor instanceof JsonResponse) {
            return $vendor;
        }

        $product = Product::where('vendor_id', $vendor->id)->find($id);
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

    private function rules(?int $ignoreId = null): array
    {
        return $this->childRules($ignoreId) + [
            'brand_id'         => ['nullable', 'integer', Rule::exists('marketplace_brands', 'id')->whereNull('deleted_at')],
            'slug'             => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('marketplace_products', 'slug')->ignore($ignoreId)],
            'name_ar'          => 'nullable|string|max:255',
            'description'      => 'nullable|string',
            'description_ar'   => 'nullable|string',
            'compare_at_price' => 'nullable|numeric|min:0',
            'stock_quantity'   => 'nullable|integer|min:0',
            'condition'        => ['nullable', Rule::in(self::CONDITIONS)],
            'status'           => ['nullable', Rule::in(self::VENDOR_STATUSES)],
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;

        for ($n = 1; Product::withTrashed()->where('slug', $slug)->exists(); $n++) {
            $slug = $base . '-' . $n;
        }

        return $slug;
    }
}
