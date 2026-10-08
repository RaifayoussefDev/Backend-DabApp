<?php

namespace App\Http\Controllers\Admin\Marketplace;

use App\Models\Marketplace\ShippingProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Admin Marketplace - Shipping providers",
 *     description="Global list of carriers (DHL, Aramex, SMSA, DabApp Shipping, Manual). Vendors pick from this list."
 * )
 */
class ShippingProviderAdminController extends MarketplaceAdminController
{
    /**
     * @OA\Get(
     *     path="/api/admin/marketplace/shipping-providers",
     *     summary="List shipping providers (Admin)",
     *     description="Small list, never paginated.",
     *     tags={"Admin Marketplace - Shipping providers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="is_active", in="query", required=false, @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Response(response=200, description="Providers", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdminMarketplaceShippingProvider"))
     *     )),
     *     @OA\Response(response=403, description="Not an admin")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $providers = ShippingProvider::query()
            ->withCount(['vendorMethods as vendor_methods_count'])
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $providers]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/marketplace/shipping-providers/{id}",
     *     summary="Shipping provider detail (Admin)",
     *     tags={"Admin Marketplace - Shipping providers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Provider", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceShippingProvider"))),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $provider = ShippingProvider::withCount(['vendorMethods as vendor_methods_count'])->find($id);

        return $provider
            ? response()->json(['data' => $provider])
            : response()->json(['message' => 'Shipping provider not found'], 404);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/marketplace/shipping-providers",
     *     summary="Create a shipping provider",
     *     tags={"Admin Marketplace - Shipping providers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"name","code"},
     *         @OA\Property(property="name", type="string", example="Aramex"),
     *         @OA\Property(property="code", type="string", example="aramex", description="Unique, letters/digits/dash/underscore"),
     *         @OA\Property(property="requires_api_key", type="boolean", example=true),
     *         @OA\Property(property="is_active", type="boolean", example=true)
     *     )),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Shipping provider created successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceShippingProvider")
     *     )),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'name'             => 'required|string|max:255',
            'code'             => 'required|string|max:50|alpha_dash|unique:marketplace_shipping_providers,code',
            'requires_api_key' => 'nullable|boolean',
            'is_active'        => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $provider = ShippingProvider::create([
            'name'             => $request->input('name'),
            'code'             => strtolower($request->input('code')),
            'requires_api_key' => $request->boolean('requires_api_key'),
            'is_active'        => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return response()->json([
            'message' => 'Shipping provider created successfully',
            'data'    => $provider->loadCount(['vendorMethods as vendor_methods_count']),
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/marketplace/shipping-providers/{id}",
     *     summary="Update a shipping provider",
     *     description="Send only the fields to change. Deactivating a provider hides it from the vendor picker but keeps existing vendor methods.",
     *     tags={"Admin Marketplace - Shipping providers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="code", type="string"),
     *         @OA\Property(property="requires_api_key", type="boolean"),
     *         @OA\Property(property="is_active", type="boolean")
     *     )),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Shipping provider updated successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceShippingProvider")
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

        $provider = ShippingProvider::find($id);
        if (! $provider) {
            return response()->json(['message' => 'Shipping provider not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'             => 'sometimes|required|string|max:255',
            'code'             => ['sometimes', 'required', 'string', 'max:50', 'alpha_dash', Rule::unique('marketplace_shipping_providers', 'code')->ignore($id)],
            'requires_api_key' => 'nullable|boolean',
            'is_active'        => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['name']);
        if ($request->has('code')) {
            $data['code'] = strtolower($request->input('code'));
        }
        foreach (['requires_api_key', 'is_active'] as $flag) {
            if ($request->has($flag)) {
                $data[$flag] = $request->boolean($flag);
            }
        }

        $provider->update($data);

        return response()->json([
            'message' => 'Shipping provider updated successfully',
            'data'    => $provider->fresh()->loadCount(['vendorMethods as vendor_methods_count']),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/marketplace/shipping-providers/{id}",
     *     summary="Delete a shipping provider",
     *     description="Soft delete. Refused with 409 (IN_USE) while vendors still have a shipping method on it: deactivate it instead.",
     *     tags={"Admin Marketplace - Shipping providers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(@OA\Property(property="message", type="string", example="Shipping provider deleted successfully"))),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=409, description="Used by vendors", @OA\JsonContent(ref="#/components/schemas/ConflictError"))
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $provider = ShippingProvider::find($id);
        if (! $provider) {
            return response()->json(['message' => 'Shipping provider not found'], 404);
        }

        if ($provider->vendorMethods()->exists()) {
            return response()->json(['message' => 'Shipping provider is used by vendors and cannot be deleted', 'code' => 'IN_USE'], 409);
        }

        $provider->delete();

        return response()->json(['message' => 'Shipping provider deleted successfully']);
    }
}
