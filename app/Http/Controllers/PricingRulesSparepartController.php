<?php

namespace App\Http\Controllers;

use App\Models\PricingRulesSparepart;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @OA\Tag(
 *     name="Pricing Rules Sparepart Management",
 *     description="API Endpoints for managing sparepart pricing rules"
 * )
 */
class PricingRulesSparepartController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/pricing-rules-sparepart",
     *     summary="List all sparepart pricing rules",
     *     tags={"Pricing Rules Sparepart Management"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="bike_part_category_id", type="integer", example=3),
     *                     @OA\Property(property="price", type="string", example="50.00"),
     *                     @OA\Property(property="created_at", type="string", example="2024-01-15T10:30:00.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", example="2024-01-15T10:30:00.000000Z"),
     *                     @OA\Property(property="category", type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name_en", type="string", example="Engine Parts"),
     *                         @OA\Property(property="name_ar", type="string", example="قطع المحرك"),
     *                         @OA\Property(property="name_fr", type="string", example="Pièces moteur")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $rules = PricingRulesSparepart::with('category')->get();

        return response()->json([
            'data' => $rules
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/pricing-rules-sparepart",
     *     summary="Create a new sparepart pricing rule",
     *     tags={"Pricing Rules Sparepart Management"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"bike_part_category_id", "price"},
     *             @OA\Property(property="bike_part_category_id", type="integer", example=3, description="ID of bike part category"),
     *             @OA\Property(property="price", type="number", format="float", example=50.00, description="Price for this category")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Pricing rule created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Pricing rule created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="bike_part_category_id", type="integer", example=3),
     *                 @OA\Property(property="price", type="string", example="50.00"),
     *                 @OA\Property(property="updated_at", type="string", example="2024-01-15T10:30:00.000000Z"),
     *                 @OA\Property(property="created_at", type="string", example="2024-01-15T10:30:00.000000Z"),
     *                 @OA\Property(property="id", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bike_part_category_id' => 'required|exists:bike_part_categories,id',
            'price' => 'required|numeric|min:0',
        ]);

        $rule = PricingRulesSparepart::create($validated);

        return response()->json([
            'message' => 'Pricing rule created successfully',
            'data' => $rule,
        ], Response::HTTP_CREATED);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/pricing-rules-sparepart/{id}",
     *     summary="Get a specific sparepart pricing rule",
     *     tags={"Pricing Rules Sparepart Management"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Pricing rule ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="bike_part_category_id", type="integer", example=3),
     *                 @OA\Property(property="price", type="string", example="50.00"),
     *                 @OA\Property(property="created_at", type="string", example="2024-01-15T10:30:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", example="2024-01-15T10:30:00.000000Z"),
     *                 @OA\Property(property="category", type="object",
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="name_en", type="string", example="Engine Parts"),
     *                     @OA\Property(property="name_ar", type="string", example="قطع المحرك")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Pricing rule not found"
     *     )
     * )
     */
    public function show(PricingRulesSparepart $pricingRulesSparepart)
    {
        return response()->json([
            'data' => $pricingRulesSparepart->load('category')
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/pricing-rules-sparepart/{id}",
     *     summary="Update a sparepart pricing rule",
     *     tags={"Pricing Rules Sparepart Management"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Pricing rule ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"bike_part_category_id", "price"},
     *             @OA\Property(property="bike_part_category_id", type="integer", example=3),
     *             @OA\Property(property="price", type="number", format="float", example=75.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pricing rule updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Pricing rule updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="bike_part_category_id", type="integer", example=3),
     *                 @OA\Property(property="price", type="string", example="75.00"),
     *                 @OA\Property(property="created_at", type="string", example="2024-01-15T10:30:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", example="2024-01-15T11:45:00.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Pricing rule not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request, PricingRulesSparepart $pricingRulesSparepart)
    {
        $validated = $request->validate([
            'bike_part_category_id' => 'required|exists:bike_part_categories,id',
            'price' => 'required|numeric|min:0',
        ]);

        $pricingRulesSparepart->update($validated);

        return response()->json([
            'message' => 'Pricing rule updated successfully',
            'data' => $pricingRulesSparepart,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/pricing-rules-sparepart/{id}",
     *     summary="Delete a sparepart pricing rule",
     *     tags={"Pricing Rules Sparepart Management"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Pricing rule ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pricing rule deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Pricing rule deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Pricing rule not found"
     *     )
     * )
     */
    public function destroy(PricingRulesSparepart $pricingRulesSparepart)
    {
        $pricingRulesSparepart->delete();

        return response()->json([
            'message' => 'Pricing rule deleted successfully',
        ]);
    }
}
