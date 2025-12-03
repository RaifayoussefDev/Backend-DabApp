<?php

namespace App\Http\Controllers;

use App\Models\PricingRulesLicencePlate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @OA\Tag(
 *     name="Pricing Rules Licence Plate Management",
 *     description="API Endpoints for managing licence plate pricing (single global price)"
 * )
 */
class PricingRulesLicencePlateController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/pricing-rules-licence-plate",
     *     summary="Get the global licence plate price",
     *     tags={"Pricing Rules Licence Plate Management"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="price", type="string", example="100.00"),
     *                 @OA\Property(property="created_at", type="string", example="2024-01-15T10:30:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", example="2024-01-15T10:30:00.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Pricing not found"
     *     )
     * )
     */
    public function show()
    {
        $rule = PricingRulesLicencePlate::first();

        if (!$rule) {
            return response()->json([
                'message' => 'Licence plate pricing not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => $rule
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/pricing-rules-licence-plate",
     *     summary="Update the global licence plate price",
     *     tags={"Pricing Rules Licence Plate Management"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"price"},
     *             @OA\Property(property="price", type="number", format="float", example=120.00, description="New global price for licence plates")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pricing updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Licence plate price updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="price", type="string", example="120.00"),
     *                 @OA\Property(property="created_at", type="string", example="2024-01-15T10:30:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", example="2024-01-15T12:00:00.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Pricing not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
        ]);

        $rule = PricingRulesLicencePlate::first();

        if (!$rule) {
            // Si aucun enregistrement n'existe, on le crée
            $rule = PricingRulesLicencePlate::create($validated);

            return response()->json([
                'message' => 'Licence plate price created successfully',
                'data' => $rule,
            ], Response::HTTP_CREATED);
        }

        $rule->update($validated);

        return response()->json([
            'message' => 'Licence plate price updated successfully',
            'data' => $rule,
        ]);
    }
}
