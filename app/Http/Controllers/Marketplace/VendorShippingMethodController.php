<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketplace\Concerns\RequiresActiveVendor;
use App\Models\Marketplace\Vendor;
use App\Models\Marketplace\VendorShippingMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Marketplace - Vendor shipping methods",
 *     description="A vendor sets up their own shipping (carrier + flat rate) from the app, same idea as vendor products. Only an active shop can do this - a pending/rejected/suspended shop gets a structured error instead (same codes as the vendor account/products endpoints). uses_own_api / credentials exist for a vendor who runs their own carrier API, but have no app UI yet - leave them out to just use a flat rate, no carrier call (v1). Credentials are write-only, encrypted at rest, never returned (only has_credentials). Access: User (Bearer JWT)."
 * )
 */
class VendorShippingMethodController extends Controller
{
    use RequiresActiveVendor;

    /**
     * @OA\Get(
     *     path="/api/marketplace/vendor/shipping-methods",
     *     summary="List my shipping methods",
     *     tags={"Marketplace - Vendor shipping methods"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="is_active", in="query", required=false, @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Response(response=200, description="Methods", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/VendorMarketplaceShippingMethod"))
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

        $methods = VendorShippingMethod::with('provider:id,name,code')
            ->where('vendor_id', $vendor->id)
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('id')->get();

        return response()->json(['data' => $methods]);
    }

    /**
     * @OA\Get(
     *     path="/api/marketplace/vendor/shipping-methods/{id}",
     *     summary="My shipping method detail",
     *     tags={"Marketplace - Vendor shipping methods"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Method", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/VendorMarketplaceShippingMethod"))),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, description="Shop not active", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict")),
     *     @OA\Response(response=404, description="No shop, or not your method")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $vendor = $this->myActiveVendorOrError();
        if ($vendor instanceof JsonResponse) {
            return $vendor;
        }

        $method = $this->find($vendor, $id);

        return $method
            ? response()->json(['data' => $method])
            : response()->json(['message' => 'Shipping method not found'], 404);
    }

    /**
     * @OA\Post(
     *     path="/api/marketplace/vendor/shipping-methods",
     *     summary="Add a shipping method",
     *     description="You can offer a given provider only once. The provider must be active. uses_own_api=true requires credentials (key/value object, e.g. {api_key, account_number}); they are stored encrypted and never returned. Without an own API the flat_rate is used, no carrier call is made (v1).",
     *     tags={"Marketplace - Vendor shipping methods"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"provider_id","flat_rate"},
     *         @OA\Property(property="provider_id", type="integer", example=1),
     *         @OA\Property(property="flat_rate", type="number", example=35),
     *         @OA\Property(property="uses_own_api", type="boolean", example=false),
     *         @OA\Property(property="credentials", type="object", example={"api_key": "xxxx", "account_number": "123456"}),
     *         @OA\Property(property="estimated_days_min", type="integer", example=2),
     *         @OA\Property(property="estimated_days_max", type="integer", example=4),
     *         @OA\Property(property="is_active", type="boolean", example=true)
     *     )),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Shipping method created successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/VendorMarketplaceShippingMethod")
     *     )),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, description="Shop not active", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict")),
     *     @OA\Response(response=404, description="No shop", @OA\JsonContent(example={"message": "You do not have a vendor account", "code": "NO_VENDOR"})),
     *     @OA\Response(response=422, description="Validation error (also: provider already added)", @OA\JsonContent(ref="#/components/schemas/MarketplaceValidationError"))
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $vendor = $this->myActiveVendorOrError();
        if ($vendor instanceof JsonResponse) {
            return $vendor;
        }

        $validator = Validator::make($request->all(), $this->rules($request) + [
            'provider_id' => ['required', 'integer',
                Rule::exists('marketplace_shipping_providers', 'id')->where('is_active', true)->whereNull('deleted_at'),
                Rule::unique('marketplace_vendor_shipping_methods', 'provider_id')->where('vendor_id', $vendor->id)->whereNull('deleted_at'),
            ],
            'flat_rate' => 'required|numeric|min:0|max:99999999',
        ]);

        $validator->after(function ($v) use ($request) {
            if ($request->boolean('uses_own_api') && empty($request->input('credentials'))) {
                $v->errors()->add('credentials', 'Credentials are required when you use your own carrier API.');
            }
        });

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $method = VendorShippingMethod::create([
            'vendor_id'          => $vendor->id,
            'provider_id'        => (int) $request->input('provider_id'),
            'uses_own_api'       => $request->boolean('uses_own_api'),
            'credentials'        => $request->boolean('uses_own_api') ? $request->input('credentials') : null,
            'flat_rate'          => $request->input('flat_rate'),
            'estimated_days_min' => $request->input('estimated_days_min'),
            'estimated_days_max' => $request->input('estimated_days_max'),
            'is_active'          => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return response()->json([
            'message' => 'Shipping method created successfully',
            'data'    => $method->load('provider:id,name,code'),
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/marketplace/vendor/shipping-methods/{id}",
     *     summary="Update a shipping method",
     *     description="Send only the fields to change. credentials replaces the stored keys when sent; clear_credentials=true removes them. Turning uses_own_api off also wipes the keys. The provider cannot be changed (delete and add another).",
     *     tags={"Marketplace - Vendor shipping methods"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="flat_rate", type="number"),
     *         @OA\Property(property="uses_own_api", type="boolean"),
     *         @OA\Property(property="credentials", type="object"),
     *         @OA\Property(property="clear_credentials", type="boolean"),
     *         @OA\Property(property="estimated_days_min", type="integer", nullable=true),
     *         @OA\Property(property="estimated_days_max", type="integer", nullable=true),
     *         @OA\Property(property="is_active", type="boolean")
     *     )),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Shipping method updated successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/VendorMarketplaceShippingMethod")
     *     )),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, description="Shop not active", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict")),
     *     @OA\Response(response=404, description="No shop, or not your method"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/MarketplaceValidationError"))
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $vendor = $this->myActiveVendorOrError();
        if ($vendor instanceof JsonResponse) {
            return $vendor;
        }

        $method = $this->find($vendor, $id);
        if (! $method) {
            return response()->json(['message' => 'Shipping method not found'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($request) + [
            'flat_rate'         => 'sometimes|required|numeric|min:0|max:99999999',
            'clear_credentials' => 'nullable|boolean',
        ]);

        $usesOwnApi = $request->has('uses_own_api') ? $request->boolean('uses_own_api') : $method->uses_own_api;
        $validator->after(function ($v) use ($request, $method, $usesOwnApi) {
            $willHaveKeys = $request->has('credentials') ? ! empty($request->input('credentials'))
                : ($request->boolean('clear_credentials') ? false : $method->has_credentials);

            if ($usesOwnApi && ! $willHaveKeys) {
                $v->errors()->add('credentials', 'Credentials are required when you use your own carrier API.');
            }
        });

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['flat_rate', 'estimated_days_min', 'estimated_days_max']);

        foreach (['uses_own_api', 'is_active'] as $flag) {
            if ($request->has($flag)) {
                $data[$flag] = $request->boolean($flag);
            }
        }

        if (! $usesOwnApi || $request->boolean('clear_credentials')) {
            $data['credentials'] = null;
        }
        if ($usesOwnApi && $request->has('credentials') && ! $request->boolean('clear_credentials')) {
            $data['credentials'] = $request->input('credentials');
        }

        $method->update($data);

        return response()->json([
            'message' => 'Shipping method updated successfully',
            'data'    => $method->fresh()->load('provider:id,name,code'),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/marketplace/vendor/shipping-methods/{id}",
     *     summary="Remove a shipping method",
     *     description="Soft delete. Orders already placed keep their shipping snapshot.",
     *     tags={"Marketplace - Vendor shipping methods"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(@OA\Property(property="message", type="string", example="Shipping method deleted successfully"))),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, description="Shop not active", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict")),
     *     @OA\Response(response=404, description="No shop, or not your method")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $vendor = $this->myActiveVendorOrError();
        if ($vendor instanceof JsonResponse) {
            return $vendor;
        }

        $method = $this->find($vendor, $id);
        if (! $method) {
            return response()->json(['message' => 'Shipping method not found'], 404);
        }

        $method->delete();

        return response()->json(['message' => 'Shipping method deleted successfully']);
    }

    private function rules(Request $request): array
    {
        return [
            'uses_own_api'       => 'nullable|boolean',
            'credentials'        => 'nullable|array',
            'credentials.*'      => 'nullable|string|max:500',
            'estimated_days_min' => 'nullable|integer|min:0|max:365',
            'estimated_days_max' => 'nullable|integer|min:0|max:365' . ($request->filled('estimated_days_min') ? '|gte:estimated_days_min' : ''),
            'is_active'          => 'nullable|boolean',
        ];
    }

    private function find(Vendor $vendor, int $id): ?VendorShippingMethod
    {
        return VendorShippingMethod::with('provider:id,name,code')->where('vendor_id', $vendor->id)->find($id);
    }
}
