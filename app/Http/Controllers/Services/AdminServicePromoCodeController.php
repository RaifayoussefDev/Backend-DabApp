<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ServicePromoCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin Service Promo Codes",
 *     description="API endpoints for managing service promo codes (Admin)"
 * )
 */
class AdminServicePromoCodeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/service-promo-codes",
     *     summary="List all service promo codes (Admin)",
     *     operationId="adminGetPromoCodes",
     *     tags={"Admin Service Promo Codes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"active", "inactive", "expired"})),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $query = ServicePromoCode::with('serviceCategory');

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($request->status === 'expired') {
                $query->where('valid_until', '<', now());
            }
        }

        $perPage = $request->get('per_page', 15);
        $promoCodes = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $promoCodes,
            'message' => 'Service promo codes retrieved successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/service-promo-codes",
     *     summary="Create a new service promo code (Admin)",
     *     operationId="adminCreatePromoCode",
     *     tags={"Admin Service Promo Codes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=201, description="Promo code created")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:service_promo_codes,code|max:50',
            'discount_type' => 'required|in:fixed,percentage',
            'discount_value' => 'required|numeric|min:0',
            'min_booking_price' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'service_category_id' => 'nullable|exists:service_categories,id',
            'usage_limit' => 'nullable|integer|min:0',
            'per_user_limit' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $promoCode = ServicePromoCode::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $promoCode,
            'message' => 'Service promo code created successfully'
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/service-promo-codes/{id}",
     *     summary="Get service promo code details (Admin)",
     *     operationId="adminGetPromoCode",
     *     tags={"Admin Service Promo Codes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show($id)
    {
        $promoCode = ServicePromoCode::with('serviceCategory')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $promoCode,
            'message' => 'Service promo code details retrieved successfully'
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/service-promo-codes/{id}",
     *     summary="Update a service promo code (Admin)",
     *     operationId="adminUpdatePromoCode",
     *     tags={"Admin Service Promo Codes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function update(Request $request, $id)
    {
        $promoCode = ServicePromoCode::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'code' => 'string|max:50|unique:service_promo_codes,code,' . $id,
            'discount_type' => 'in:fixed,percentage',
            'discount_value' => 'numeric|min:0',
            'min_booking_price' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'service_category_id' => 'nullable|exists:service_categories,id',
            'usage_limit' => 'nullable|integer|min:0',
            'per_user_limit' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $promoCode->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $promoCode,
            'message' => 'Service promo code updated successfully'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/service-promo-codes/{id}",
     *     summary="Delete a service promo code (Admin)",
     *     operationId="adminDeletePromoCode",
     *     tags={"Admin Service Promo Codes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function destroy($id)
    {
        $promoCode = ServicePromoCode::findOrFail($id);
        $promoCode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service promo code deleted successfully'
        ]);
    }
}
