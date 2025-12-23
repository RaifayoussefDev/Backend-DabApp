<?php

namespace App\Http\Controllers;

use App\Models\PricingRulesMotorcycle;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @OA\Tag(
 *     name="Pricing Rules Motorcycle Management",
 *     description="API Endpoints for managing motorcycle pricing rules"
 * )
 */
class PricingRulesMotorcycleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/pricing-rules-motorcycle",
     *     summary="List all motorcycle pricing rules",
     *     tags={"Pricing Rules Motorcycle Management"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="motorcycle_type_id", type="integer", example=2),
     *                     @OA\Property(property="price", type="string", example="150.00"),
     *                     @OA\Property(property="created_at", type="string", example="2024-01-15T10:30:00.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", example="2024-01-15T10:30:00.000000Z"),
     *                     @OA\Property(property="motorcycle_type", type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="name_en", type="string", example="Sport"),
     *                         @OA\Property(property="name_ar", type="string", example="رياضية"),
     *                         @OA\Property(property="name_fr", type="string", example="Sport"),
     *                         @OA\Property(property="created_at", type="string", example="2024-01-10T08:00:00.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", example="2024-01-10T08:00:00.000000Z")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $rules = PricingRulesMotorcycle::with('motorcycleType')->get();

        return response()->json([
            'data' => $rules
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/pricing-rules-motorcycle",
     *     summary="Create a new motorcycle pricing rule",
     *     tags={"Pricing Rules Motorcycle Management"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"motorcycle_type_id", "price"},
     *             @OA\Property(property="motorcycle_type_id", type="integer", example=2, description="ID of motorcycle type"),
     *             @OA\Property(property="price", type="number", format="float", example=150.00, description="Price for this motorcycle type")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Pricing rule created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Pricing rule created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="motorcycle_type_id", type="integer", example=2),
     *                 @OA\Property(property="price", type="string", example="150.00"),
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
            'motorcycle_type_id' => 'required|exists:motorcycle_types,id|unique:pricing_rules_motorcycle,motorcycle_type_id',
            'price' => 'required|numeric|min:0',
        ]);

        $rule = PricingRulesMotorcycle::create($validated);

        return response()->json([
            'message' => 'Pricing rule created successfully',
            'data' => $rule,
        ], Response::HTTP_CREATED);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/pricing-rules-motorcycle/{id}",
     *     summary="Get a specific motorcycle pricing rule",
     *     tags={"Pricing Rules Motorcycle Management"},
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
     *                 @OA\Property(property="motorcycle_type_id", type="integer", example=2),
     *                 @OA\Property(property="price", type="string", example="150.00"),
     *                 @OA\Property(property="created_at", type="string", example="2024-01-15T10:30:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", example="2024-01-15T10:30:00.000000Z"),
     *                 @OA\Property(property="motorcycle_type", type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name_en", type="string", example="Sport"),
     *                     @OA\Property(property="name_ar", type="string", example="رياضية"),
     *                     @OA\Property(property="name_fr", type="string", example="Sport")
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
    public function show(PricingRulesMotorcycle $pricingRulesMotorcycle)
    {
        return response()->json([
            'data' => $pricingRulesMotorcycle->load('motorcycleType')
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/pricing-rules-motorcycle/{id}",
     *     summary="Update a motorcycle pricing rule",
     *     tags={"Pricing Rules Motorcycle Management"},
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
     *             required={"motorcycle_type_id", "price"},
     *             @OA\Property(property="motorcycle_type_id", type="integer", example=2),
     *             @OA\Property(property="price", type="number", format="float", example=200.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pricing rule updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Pricing rule updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="motorcycle_type_id", type="integer", example=2),
     *                 @OA\Property(property="price", type="string", example="200.00"),
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
    public function update(Request $request, PricingRulesMotorcycle $pricingRulesMotorcycle)
    {
        $validated = $request->validate([
            'motorcycle_type_id' => 'required|exists:motorcycle_types,id|unique:pricing_rules_motorcycle,motorcycle_type_id,' . $pricingRulesMotorcycle->id,
            'price' => 'required|numeric|min:0',
        ]);

        $pricingRulesMotorcycle->update($validated);

        return response()->json([
            'message' => 'Pricing rule updated successfully',
            'data' => $pricingRulesMotorcycle,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/pricing-rules-motorcycle/{id}",
     *     summary="Delete a motorcycle pricing rule",
     *     tags={"Pricing Rules Motorcycle Management"},
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
    public function destroy(PricingRulesMotorcycle $pricingRulesMotorcycle)
    {
        $pricingRulesMotorcycle->delete();

        return response()->json([
            'message' => 'Pricing rule deleted successfully',
        ]);
    }
}
