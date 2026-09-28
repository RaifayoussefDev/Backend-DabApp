<?php

namespace App\Http\Controllers\Admin\Marketplace;

use App\Models\Marketplace\Vendor;
use App\Models\Marketplace\VendorShippingMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Admin Marketplace - Vendor shipping methods",
 *     description="What a vendor actually offers: one or more carriers from the global providers list, each with a flat rate. Carrier API keys are write-only, encrypted at rest and never returned (only has_credentials)."
 * )
 */
class VendorShippingMethodAdminController extends MarketplaceAdminController
{
    /**
     * @OA\Get(
     *     path="/api/admin/marketplace/vendors/{vendorId}/shipping-methods",
     *     summary="List a vendor's shipping methods",
     *     tags={"Admin Marketplace - Vendor shipping methods"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="vendorId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_active", in="query", required=false, @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Response(response=200, description="Methods", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdminMarketplaceVendorShippingMethod"))
     *     )),
     *     @OA\Response(response=404, description="Vendor not found")
     * )
     */
    public function index(Request $request, int $vendorId): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }
        if (! Vendor::whereKey($vendorId)->exists()) {
            return response()->json(['message' => 'Vendor not found'], 404);
        }

        $methods = VendorShippingMethod::with('provider:id,name,code')
            ->where('vendor_id', $vendorId)
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('id')->get();

        return response()->json(['data' => $methods]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/marketplace/vendors/{vendorId}/shipping-methods/{id}",
     *     summary="Shipping method detail",
     *     tags={"Admin Marketplace - Vendor shipping methods"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="vendorId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Method", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceVendorShippingMethod"))),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(int $vendorId, int $id): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $method = $this->find($vendorId, $id);

        return $method
            ? response()->json(['data' => $method])
            : response()->json(['message' => 'Shipping method not found'], 404);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/marketplace/vendors/{vendorId}/shipping-methods",
     *     summary="Add a shipping method to a vendor",
     *     description="A vendor can have a given provider only once. The provider must be active. uses_own_api=true requires credentials (key/value object, e.g. {api_key, account_number}); they are stored encrypted and never returned. Without an own API the flat_rate is used, no carrier call is made (v1).",
     *     tags={"Admin Marketplace - Vendor shipping methods"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="vendorId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"provider_id","flat_rate"},
     *         @OA\Property(property="provider_id", type="integer", example=1),
     *         @OA\Property(property="flat_rate", type="number", example=35),
     *         @OA\Property(property="uses_own_api", type="boolean", example=true),
     *         @OA\Property(property="credentials", type="object", example={"api_key": "xxxx", "account_number": "123456"}),
     *         @OA\Property(property="estimated_days_min", type="integer", example=2),
     *         @OA\Property(property="estimated_days_max", type="integer", example=4),
     *         @OA\Property(property="is_active", type="boolean", example=true)
     *     )),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Shipping method created successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceVendorShippingMethod")
     *     )),
     *     @OA\Response(response=404, description="Vendor not found"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(Request $request, int $vendorId): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }
        if (! Vendor::whereKey($vendorId)->exists()) {
            return response()->json(['message' => 'Vendor not found'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($request) + [
            'provider_id' => ['required', 'integer',
                Rule::exists('marketplace_shipping_providers', 'id')->where('is_active', true)->whereNull('deleted_at'),
                Rule::unique('marketplace_vendor_shipping_methods', 'provider_id')->where('vendor_id', $vendorId)->whereNull('deleted_at'),
            ],
            'flat_rate' => 'required|numeric|min:0|max:99999999',
        ]);

        $validator->after(function ($v) use ($request) {
            if ($request->boolean('uses_own_api') && empty($request->input('credentials'))) {
                $v->errors()->add('credentials', 'Credentials are required when the vendor uses its own carrier API.');
            }
        });

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $method = VendorShippingMethod::create([
            'vendor_id'          => $vendorId,
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
     *     path="/api/admin/marketplace/vendors/{vendorId}/shipping-methods/{id}",
     *     summary="Update a shipping method",
     *     description="Send only the fields to change. credentials replaces the stored keys when sent; clear_credentials=true removes them. Turning uses_own_api off also wipes the keys. The provider cannot be changed (delete and add another).",
     *     tags={"Admin Marketplace - Vendor shipping methods"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="vendorId", in="path", required=true, @OA\Schema(type="integer")),
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
     *         @OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceVendorShippingMethod")
     *     )),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(Request $request, int $vendorId, int $id): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $method = $this->find($vendorId, $id);
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
                $v->errors()->add('credentials', 'Credentials are required when the vendor uses its own carrier API.');
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
     *     path="/api/admin/marketplace/vendors/{vendorId}/shipping-methods/{id}",
     *     summary="Remove a shipping method",
     *     description="Soft delete. Orders already placed keep their shipping snapshot.",
     *     tags={"Admin Marketplace - Vendor shipping methods"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="vendorId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(@OA\Property(property="message", type="string", example="Shipping method deleted successfully"))),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(int $vendorId, int $id): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $method = $this->find($vendorId, $id);
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

    private function find(int $vendorId, int $id): ?VendorShippingMethod
    {
        return VendorShippingMethod::with('provider:id,name,code')->where('vendor_id', $vendorId)->find($id);
    }
}
