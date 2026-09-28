<?php

namespace App\Http\Controllers\Admin\Marketplace;

use App\Models\Marketplace\OrderItem;
use App\Models\Marketplace\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Admin Marketplace - Vendors",
 *     description="Shops: creation, branding (logo, cover, brand color), approval workflow, commission override. logo_path / cover_image_path are URLs from POST /api/marketplace/upload-image (type=vendor), not files: upload first, then send the strings here."
 * )
 */
class VendorAdminController extends MarketplaceAdminController
{
    private const STATUSES = ['pending', 'active', 'suspended', 'rejected'];

    /**
     * @OA\Get(
     *     path="/api/admin/marketplace/vendors",
     *     summary="List vendors (Admin)",
     *     description="All statuses. The IBAN is never in the list (open the detail). per_page empty = everything, no pagination.",
     *     tags={"Admin Marketplace - Vendors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, description="shop name, slug, or owner name / email / phone", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"pending","active","suspended","rejected"})),
     *     @OA\Parameter(name="is_featured", in="query", required=false, @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Parameter(name="country_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="city_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(type="string", enum={"newest","name","rating","products"}, default="newest")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Vendors", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdminMarketplaceVendor")),
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

        $query = Vendor::query()
            ->with('owner:id,first_name,last_name,email,phone')
            ->withCount('products')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->input('search') . '%';
                $q->where(fn ($w) => $w->where('shop_name', 'like', $term)
                    ->orWhere('shop_name_ar', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhereHas('owner', fn ($o) => $o->where('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('country_id'), fn ($q) => $q->where('country_id', (int) $request->input('country_id')))
            ->when($request->filled('city_id'), fn ($q) => $q->where('city_id', (int) $request->input('city_id')))
            ->when($request->filled('is_featured'), fn ($q) => $q->where('is_featured', $request->boolean('is_featured')));

        match ($request->input('sort')) {
            'name'     => $query->orderBy('shop_name'),
            'rating'   => $query->orderByDesc('rating_avg')->orderBy('shop_name'),
            'products' => $query->orderByDesc('products_count')->orderBy('shop_name'),
            default    => $query->orderByDesc('id'),
        };

        $perPage = $this->perPage($request);

        if ($perPage) {
            $page = $query->paginate($perPage);
            $page->getCollection()->transform(fn (Vendor $v) => $this->present($v));

            return response()->json($this->paginated($page));
        }

        return response()->json(['data' => $query->get()->map(fn (Vendor $v) => $this->present($v))->values()]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/marketplace/vendors/stats",
     *     summary="Vendor counters",
     *     description="Totals per status, for the tabs of the vendors screen.",
     *     tags={"Admin Marketplace - Vendors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Counters", @OA\JsonContent(
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="total", type="integer", example=3), @OA\Property(property="pending", type="integer", example=1),
     *             @OA\Property(property="active", type="integer", example=2), @OA\Property(property="suspended", type="integer", example=0),
     *             @OA\Property(property="rejected", type="integer", example=0))
     *     ))
     * )
     */
    public function stats(): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $counts = Vendor::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $data = ['total' => (int) $counts->sum()];
        foreach (self::STATUSES as $status) {
            $data[$status] = (int) ($counts[$status] ?? 0);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/marketplace/vendors/{id}",
     *     summary="Vendor detail (Admin)",
     *     description="Full record including bank name, IBAN and commission override.",
     *     tags={"Admin Marketplace - Vendors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Vendor", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceVendor"))),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $vendor = Vendor::with('owner:id,first_name,last_name,email,phone')->withCount('products')->find($id);

        return $vendor
            ? response()->json(['data' => $this->present($vendor, true)])
            : response()->json(['message' => 'Vendor not found'], 404);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/marketplace/vendors",
     *     summary="Create a vendor",
     *     description="A user can own one shop. status defaults to pending; creating it as active stamps approved_at / approved_by. slug is generated from shop_name when omitted. city_id must belong to country_id. For logo / cover_image, call POST /api/marketplace/upload-image (type=vendor) first and send the URLs it returns.",
     *     tags={"Admin Marketplace - Vendors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"user_id","shop_name"},
     *         @OA\Property(property="user_id", type="integer", example=42, description="Owner account"),
     *         @OA\Property(property="shop_name", type="string", example="Moto Parts Riyadh"),
     *         @OA\Property(property="shop_name_ar", type="string"),
     *         @OA\Property(property="slug", type="string"),
     *         @OA\Property(property="description", type="string"),
     *         @OA\Property(property="description_ar", type="string"),
     *         @OA\Property(property="logo_path", type="string", nullable=true, example="https://api.dabapp.co/storage/marketplace/vendors/aB3dE9fGh1JkLmN0pQrS.jpg"),
     *         @OA\Property(property="cover_image_path", type="string", nullable=true, example="https://api.dabapp.co/storage/marketplace/vendors/zY8xW7vU6tS5rQ4pO3nM.jpg"),
     *         @OA\Property(property="brand_color", type="string", example="#E63946", description="#RRGGBB"),
     *         @OA\Property(property="phone", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="country_id", type="integer"),
     *         @OA\Property(property="city_id", type="integer"),
     *         @OA\Property(property="status", type="string", enum={"pending","active","suspended","rejected"}),
     *         @OA\Property(property="is_featured", type="boolean", example=false, description="Shown on the Marketplace home Shops row and the Featured badge on All Shops"),
     *         @OA\Property(property="commission_override", type="number", example=12.5, description="0-100, empty = category or global rate"),
     *         @OA\Property(property="bank_name", type="string"),
     *         @OA\Property(property="iban", type="string")
     *     )),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Vendor created successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceVendor")
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
            'user_id'   => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at'), Rule::unique('marketplace_vendors', 'user_id')->whereNull('deleted_at')],
            'shop_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = $request->input('status', 'pending');

        $vendor = Vendor::create([
            'user_id'             => (int) $request->input('user_id'),
            'shop_name'           => $request->input('shop_name'),
            'shop_name_ar'        => $request->input('shop_name_ar'),
            'slug'                => $request->filled('slug') ? $request->input('slug') : $this->uniqueSlug(Vendor::class, $request->input('shop_name')),
            'description'         => $request->input('description'),
            'description_ar'      => $request->input('description_ar'),
            'logo_path'           => $request->input('logo_path'),
            'cover_image_path'    => $request->input('cover_image_path'),
            'brand_color'         => $request->input('brand_color'),
            'phone'               => $request->input('phone'),
            'email'               => $request->input('email'),
            'country_id'          => $request->input('country_id') ?: null,
            'city_id'             => $request->input('city_id') ?: null,
            'status'              => $status,
            'is_featured'         => $request->boolean('is_featured'),
            'approved_at'         => $status === 'active' ? now() : null,
            'approved_by'         => $status === 'active' ? Auth::id() : null,
            'commission_override' => $request->filled('commission_override') ? $request->input('commission_override') : null,
            'bank_name'           => $request->input('bank_name'),
            'iban'                => $request->input('iban'),
        ]);

        return response()->json([
            'message' => 'Vendor created successfully',
            'data'    => $this->present($vendor->load('owner:id,first_name,last_name,email,phone')->loadCount('products'), true),
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/marketplace/vendors/{id}",
     *     summary="Update a vendor",
     *     description="Send only the fields to change. Send logo_path / cover_image_path: null to remove that image, or omit it to leave it untouched. Status changes go through approve / suspend / reject, not this endpoint. Empty commission_override clears the override.",
     *     tags={"Admin Marketplace - Vendors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="user_id", type="integer", description="Transfer the shop to another account"),
     *         @OA\Property(property="shop_name", type="string"),
     *         @OA\Property(property="shop_name_ar", type="string"),
     *         @OA\Property(property="slug", type="string"),
     *         @OA\Property(property="description", type="string"),
     *         @OA\Property(property="description_ar", type="string"),
     *         @OA\Property(property="logo_path", type="string", nullable=true, description="URL from POST /api/marketplace/upload-image, or null to remove it"),
     *         @OA\Property(property="cover_image_path", type="string", nullable=true, description="URL from POST /api/marketplace/upload-image, or null to remove it"),
     *         @OA\Property(property="brand_color", type="string", example="#1D3557"),
     *         @OA\Property(property="phone", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="country_id", type="integer"),
     *         @OA\Property(property="city_id", type="integer"),
     *         @OA\Property(property="is_featured", type="boolean"),
     *         @OA\Property(property="commission_override", type="number"),
     *         @OA\Property(property="bank_name", type="string"),
     *         @OA\Property(property="iban", type="string")
     *     )),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Vendor updated successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceVendor")
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

        $vendor = Vendor::find($id);
        if (! $vendor) {
            return response()->json(['message' => 'Vendor not found'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($request, $id) + [
            'user_id'   => ['sometimes', 'required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at'), Rule::unique('marketplace_vendors', 'user_id')->whereNull('deleted_at')->ignore($id)],
            'shop_name' => 'sometimes|required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'user_id', 'shop_name', 'shop_name_ar', 'slug', 'description', 'description_ar',
            'brand_color', 'phone', 'email', 'country_id', 'city_id', 'bank_name', 'iban',
        ]);

        if ($request->has('commission_override')) {
            $data['commission_override'] = $request->filled('commission_override') ? $request->input('commission_override') : null;
        }
        if ($request->has('is_featured')) {
            $data['is_featured'] = $request->boolean('is_featured');
        }
        if ($request->has('logo_path')) {
            $data['logo_path'] = $request->input('logo_path');
        }
        if ($request->has('cover_image_path')) {
            $data['cover_image_path'] = $request->input('cover_image_path');
        }

        $vendor->update($data);

        return response()->json([
            'message' => 'Vendor updated successfully',
            'data'    => $this->present($vendor->fresh()->load('owner:id,first_name,last_name,email,phone')->loadCount('products'), true),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/marketplace/vendors/{id}/approve",
     *     summary="Approve a vendor",
     *     description="pending, rejected or suspended becomes active (reactivation). 409 INVALID_STATE if already active. Stamps approved_at / approved_by and clears the reason. Its active products become visible in the storefront.",
     *     tags={"Admin Marketplace - Vendors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Approved", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Vendor approved successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceVendor")
     *     )),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=409, description="Already active", @OA\JsonContent(ref="#/components/schemas/ConflictError"))
     * )
     */
    public function approve(int $id): JsonResponse
    {
        return $this->transition($id, ['pending', 'rejected', 'suspended'], 'active', null, 'Vendor approved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/admin/marketplace/vendors/{id}/suspend",
     *     summary="Suspend a vendor",
     *     description="active becomes suspended: the shop and all its products disappear from the storefront (orders already placed are not touched). A reason is required. 409 INVALID_STATE if not active.",
     *     tags={"Admin Marketplace - Vendors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"reason"}, @OA\Property(property="reason", type="string", example="Repeated complaints about fake parts"))),
     *     @OA\Response(response=200, description="Suspended", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Vendor suspended successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceVendor")
     *     )),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=409, description="Not active", @OA\JsonContent(ref="#/components/schemas/ConflictError")),
     *     @OA\Response(response=422, description="Reason missing", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function suspend(Request $request, int $id): JsonResponse
    {
        return $this->transition($id, ['active'], 'suspended', $request, 'Vendor suspended successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/admin/marketplace/vendors/{id}/reject",
     *     summary="Reject a vendor application",
     *     description="pending becomes rejected. A reason is required. 409 INVALID_STATE if not pending.",
     *     tags={"Admin Marketplace - Vendors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"reason"}, @OA\Property(property="reason", type="string", example="Missing commercial registration"))),
     *     @OA\Response(response=200, description="Rejected", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Vendor rejected successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceVendor")
     *     )),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=409, description="Not pending", @OA\JsonContent(ref="#/components/schemas/ConflictError")),
     *     @OA\Response(response=422, description="Reason missing", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        return $this->transition($id, ['pending'], 'rejected', $request, 'Vendor rejected successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/marketplace/vendors/{id}",
     *     summary="Delete a vendor",
     *     description="Soft delete. Refused with 409 HAS_ORDERS once the shop has sold something (suspend it instead). Its products leave the storefront.",
     *     tags={"Admin Marketplace - Vendors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(@OA\Property(property="message", type="string", example="Vendor deleted successfully"))),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=409, description="Has orders", @OA\JsonContent(ref="#/components/schemas/ConflictError"))
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $vendor = Vendor::find($id);
        if (! $vendor) {
            return response()->json(['message' => 'Vendor not found'], 404);
        }

        if (OrderItem::where('vendor_id', $id)->exists()) {
            return response()->json(['message' => 'Vendor has orders and cannot be deleted, suspend it instead', 'code' => 'HAS_ORDERS'], 409);
        }

        $vendor->delete();

        return response()->json(['message' => 'Vendor deleted successfully']);
    }

    // ------------------------------------------------------------------ internals

    private function rules(Request $request, ?int $ignoreId = null): array
    {
        return [
            'shop_name_ar'        => 'nullable|string|max:255',
            'slug'                => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('marketplace_vendors', 'slug')->ignore($ignoreId)],
            'description'         => 'nullable|string',
            'description_ar'      => 'nullable|string',
            'logo_path'           => $this->imagePathRule(),
            'cover_image_path'    => $this->imagePathRule(),
            'brand_color'         => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'phone'               => 'nullable|string|max:30',
            'email'               => 'nullable|email|max:255',
            'country_id'          => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'city_id'             => ['nullable', 'integer', Rule::exists('cities', 'id')->when($request->filled('country_id'), fn ($rule) => $rule->where('country_id', (int) $request->input('country_id')))],
            'status'              => ['nullable', Rule::in(self::STATUSES)],
            'is_featured'         => 'nullable|boolean',
            'commission_override' => 'nullable|numeric|min:0|max:100',
            'bank_name'           => 'nullable|string|max:255',
            'iban'                => 'nullable|string|max:64',
        ];
    }

    /** Status workflow. Reason is mandatory when leaving the happy path (suspend / reject). */
    private function transition(int $id, array $from, string $to, ?Request $request, string $message): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $vendor = Vendor::find($id);
        if (! $vendor) {
            return response()->json(['message' => 'Vendor not found'], 404);
        }

        if ($request) {
            $validator = Validator::make($request->all(), ['reason' => 'required|string|max:1000']);
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
        }

        if (! in_array($vendor->status, $from, true)) {
            return response()->json([
                'message' => "A {$vendor->status} vendor cannot become {$to}",
                'code'    => 'INVALID_STATE',
            ], 409);
        }

        $vendor->update($to === 'active'
            ? ['status' => 'active', 'status_reason' => null, 'approved_at' => now(), 'approved_by' => Auth::id()]
            : ['status' => $to, 'status_reason' => $request->input('reason')]);

        return response()->json([
            'message' => $message,
            'data'    => $this->present($vendor->fresh()->load('owner:id,first_name,last_name,email,phone')->loadCount('products'), true),
        ]);
    }

    /** Admin view of a vendor. The IBAN is only shown on the detail, never in lists. */
    private function present(Vendor $vendor, bool $full = false): Vendor
    {
        $vendor->makeVisible(['bank_name', 'commission_override']);

        if ($full) {
            $vendor->makeVisible('iban');
        }

        return $vendor;
    }
}
