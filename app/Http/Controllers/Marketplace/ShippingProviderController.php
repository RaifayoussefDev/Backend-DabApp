<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\ShippingProvider;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Marketplace - Shipping providers",
 *     description="The global carrier list a vendor can pick from when setting up their own shipping methods. Public, no token needed."
 * )
 */
class ShippingProviderController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/marketplace/shipping-providers",
     *     summary="List shipping providers",
     *     description="Active carriers only (DHL, Aramex, SMSA, DabApp Shipping...). requires_api_key just flags whether this carrier normally needs its own credentials - vendors don't have to use it. Access: Public.",
     *     tags={"Marketplace - Shipping providers"},
     *     @OA\Response(response=200, description="Providers, A-Z", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(
     *             @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string"), @OA\Property(property="code", type="string"), @OA\Property(property="requires_api_key", type="boolean")
     *         )),
     *         example={"data": {
     *             {"id": 2, "name": "Aramex", "code": "aramex", "requires_api_key": true},
     *             {"id": 4, "name": "DabApp Shipping", "code": "dabapp", "requires_api_key": false},
     *             {"id": 1, "name": "DHL", "code": "dhl", "requires_api_key": true}
     *         }}
     *     ))
     * )
     */
    public function index(): JsonResponse
    {
        $providers = ShippingProvider::active()->orderBy('name')->get(['id', 'name', 'code', 'requires_api_key']);

        return response()->json([
            'data' => $providers->map(fn (ShippingProvider $p) => [
                'id'               => $p->id,
                'name'             => $p->name,
                'code'             => $p->code,
                'requires_api_key' => (bool) $p->requires_api_key,
            ])->values(),
        ]);
    }
}
