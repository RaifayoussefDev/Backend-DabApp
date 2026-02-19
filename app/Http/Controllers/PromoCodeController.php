<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PromoCode;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\PromoCodeUsage;

/**
 * @OA\Tag(
 *     name="Promo Code - Management",
 *     description="API Endpoints for managing Promo Codes"
 * )
 */
class PromoCodeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/promo-codes",
     *     summary="List all promo codes",
     *     tags={"Promo Code - Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of promo codes",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="WELCOME10"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_value", type="number", example=10),
                @OA\Property(property="is_active", type="boolean", example=true),
                @OA\Property(property="display", type="boolean", example=true)
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        return response()->json(PromoCode::all());
    }

    /**
     * @OA\Post(
     *     path="/api/admin/promo-codes",
     *     summary="Create a new promo code",
     *     tags={"Promo Code - Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "discount_type", "discount_value"},
     *             @OA\Property(property="code", type="string", example="SAVE20"),
     *             @OA\Property(property="description", type="string", example="Get 20% off"),
     *             @OA\Property(property="discount_type", type="string", enum={"percentage", "fixed"}),
     *             @OA\Property(property="discount_value", type="number", format="float"),
     *             @OA\Property(property="max_discount", type="number", format="float"),
     *             @OA\Property(property="min_listing_price", type="number", format="float"),
     *             @OA\Property(property="usage_limit", type="integer"),
     *             @OA\Property(property="per_user_limit", type="integer", default=1),
     *             @OA\Property(property="valid_from", type="string", format="date-time"),
     *             @OA\Property(property="valid_until", type="string", format="date-time"),
     *             @OA\Property(property="is_active", type="boolean", default=true),
            @OA\Property(property="display", type="boolean", default=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Promo code created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Promo code created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:promo_codes',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_listing_price' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_user_limit' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
            'display' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $promo = PromoCode::create($request->all());

        return response()->json([
            'message' => 'Promo code created successfully',
            'data' => $promo
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/promo-codes/{id}",
     *     summary="Get promo code details",
     *     tags={"Promo Code - Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Promo code details",
     *         @OA\JsonContent(
            @OA\Property(property="id", type="integer", example=1),
            @OA\Property(property="code", type="string", example="SAVE20"),
            @OA\Property(property="discount_type", type="string", example="percentage"),
            @OA\Property(property="discount_value", type="number", example=20),
            @OA\Property(property="is_active", type="boolean", example=true),
            @OA\Property(property="display", type="boolean", example=true)
        )
     *     ),
     *     @OA\Response(response=404, description="Promo code not found")
     * )
     */
    public function show($id)
    {
        $promo = PromoCode::find($id);
        if (!$promo) {
            return response()->json(['message' => 'Promo code not found'], 404);
        }
        return response()->json($promo);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/promo-codes/{id}",
     *     summary="Update a promo code",
     *     tags={"Promo Code - Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="discount_type", type="string", enum={"percentage", "fixed"}),
     *             @OA\Property(property="discount_value", type="number", format="float"),
     *             @OA\Property(property="is_active", type="boolean"),
            @OA\Property(property="display", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Promo code updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Promo code updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Promo code not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $promo = PromoCode::find($id);
        if (!$promo) {
            return response()->json(['message' => 'Promo code not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|unique:promo_codes,code,' . $id,
            'discount_type' => 'sometimes|in:percentage,fixed',
            'discount_value' => 'sometimes|numeric|min:0',
            'is_active' => 'boolean',
            'display' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $promo->update($request->all());

        return response()->json([
            'message' => 'Promo code updated successfully',
            'data' => $promo
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/promo-codes/{id}",
     *     summary="Delete a promo code",
     *     tags={"Promo Code - Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Promo code deleted successfully"),
     *     @OA\Response(response=404, description="Promo code not found")
     * )
     */
    public function destroy($id)
    {
        $promo = PromoCode::find($id);
        if (!$promo) {
            return response()->json(['message' => 'Promo code not found'], 404);
        }
        $promo->delete();
        return response()->json(['message' => 'Promo code deleted successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/promo/check",
     *     summary="Check promo code validity and calculate discount",
     *     description="Validates a promo code and calculates the new price after applying the discount.",
     *     operationId="checkPromo",
     *     tags={"Promo Codes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "total_price"},
     *             @OA\Property(property="code", type="string", example="WELCOME10"),
     *             @OA\Property(property="total_price", type="number", format="float", example=100.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Promo code is valid and discount applied",
     *         @OA\JsonContent(
     *             @OA\Property(property="old_price", type="number", format="float", example=100.00),
     *             @OA\Property(property="new_price", type="number", format="float", example=80.00),
     *             @OA\Property(property="discount", type="number", format="float", example=20.00),
     *             @OA\Property(property="discount_type", type="string", example="percentage"),
     *             @OA\Property(property="discount_value", type="number", format="float", example=20),
     *             @OA\Property(property="description", type="string", example="20% off on your next purchase"),
     *             @OA\Property(property="usage_count", type="integer", example=1),
     *             @OA\Property(property="total_usage_count", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Promo code is not valid (time, price, usage limit)",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Promo code is not valid at this time.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Promo code not found or inactive",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Promo code not found or inactive.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="code", type="array", @OA\Items(type="string", example="The code field is required.")),
     *                 @OA\Property(property="total_price", type="array", @OA\Items(type="string", example="The total price must be at least 0."))
     *             )
     *         )
     *     )
     * )
     */

    public function checkPromo(Request $request, \App\Services\PromoCodeService $promoService)
    {
        $request->validate([
            'code' => 'required|string',
            'total_price' => 'required|numeric|min:0',
        ]);

        $result = $promoService->validatePromoCode(
            $request->code,
            Auth::user(),
            $request->total_price
        );

        if (!$result['valid']) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            'old_price' => number_format($result['old_price'], 2, '.', ''),
            'new_price' => number_format($result['new_price'], 2, '.', ''),
            'discount' => number_format($result['discount'], 2, '.', ''),
            'discount_type' => $result['promo']->discount_type,
            'discount_value' => number_format($result['promo']->discount_value, 2, '.', ''),
            'description' => $result['promo']->description,
            'usage_count' => (string) $result['user_usages'],
            'total_usage_count' => (string) $result['total_usages'],
        ]);
    }
    /**
     * @OA\Get(
     *     path="/api/admin/promo-codes/usages",
     *     summary="List all promo code usages",
     *     description="Returns a list of all promo code usage records with associated user and listing details.",
     *     tags={"Promo Code - Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of promo code usages",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="promo_code_id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=5),
     *                 @OA\Property(property="listing_id", type="integer", example=10),
     *                 @OA\Property(property="used_at", type="string", format="date-time"),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="email", type="string")
     *                 ),
     *                 @OA\Property(
     *                     property="listing",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="title", type="string")
     *                 ),
     *                 @OA\Property(
     *                     property="promo_code",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="code", type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function usages()
    {
        $usages = PromoCodeUsage::with(['user:id,name,email', 'listing:id,title', 'promoCode:id,code'])
            ->orderBy('used_at', 'desc')
            ->get();

        return response()->json($usages);
    }

    /**
     * @OA\Get(
     *     path="/api/promo-codes/displayed",
     *     summary="List displayed promo codes (Public)",
     *     description="Get list of promo codes marked for display (e.g. for mobile app).",
     *     tags={"Promo Codes"},
     *     @OA\Response(
     *         response=200,
     *         description="List of public promo codes",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="code", type="string", example="WELCOME10"),
     *                 @OA\Property(property="description", type="string", example="Get 10% off"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_value", type="number", example=10)
     *             )
     *         )
     *     )
     * )
     */
    public function getDisplayed()
    {
        $promos = PromoCode::where('display', true)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            })
            ->select('code', 'description', 'discount_type', 'discount_value', 'min_listing_price', 'valid_until')
            ->get();

        return response()->json($promos);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/promo-codes/{id}/toggle-display",
     *     summary="Toggle promo code display status",
     *     description="Enable or disable the display of a promo code in public listings.",
     *     tags={"Promo Code - Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"display"},
     *             @OA\Property(property="display", type="boolean", example=true, description="New display status")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Display status updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Promo code display status updated"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="WELCOME10"),
     *                 @OA\Property(property="display", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Promo code not found")
     * )
     */
    public function toggleDisplay(Request $request, $id)
    {
        $promo = PromoCode::find($id);
        if (!$promo) {
            return response()->json(['message' => 'Promo code not found'], 404);
        }

        $request->validate([
            'display' => 'required|boolean'
        ]);

        $promo->display = $request->display;
        $promo->save();

        return response()->json([
            'message' => 'Promo code display status updated',
            'data' => $promo
        ]);
    }
}
