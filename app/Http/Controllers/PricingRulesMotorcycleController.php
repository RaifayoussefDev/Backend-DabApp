<?php

namespace App\Http\Controllers;

use App\Models\PricingRulesMotorcycle;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

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
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page. Leave empty to get all items.",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by motorcycle type name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="motorcycle_type_id",
     *         in="query",
     *         description="Filter by motorcycle type ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = PricingRulesMotorcycle::with('motorcycleType');

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->whereHas('motorcycleType', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('name_ar', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('motorcycle_type_id')) {
            $query->where('motorcycle_type_id', $request->motorcycle_type_id);
        }

        if ($request->filled('per_page')) {
            $perPage = $request->input('per_page', 15);
            $rules = $query->paginate($perPage);
        } else {
            $rules = $query->get();
        }

        return response()->json([
            'success' => true,
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
        // Log the incoming request
        \Illuminate\Support\Facades\Log::info("Attempting to update PricingRule ID: {$pricingRulesMotorcycle->id}", [
            'request_data' => $request->all(),
            'current_db_record' => $pricingRulesMotorcycle->toArray()
        ]);

        // Manual validation for uniqueness to debug the issue
        $existing = PricingRulesMotorcycle::where('motorcycle_type_id', $request->motorcycle_type_id)
            ->where('id', '!=', $pricingRulesMotorcycle->id)
            ->first();

        if ($existing) {
            \Illuminate\Support\Facades\Log::warning("Validation Conflict Detected", [
                'attempted_type_id' => $request->motorcycle_type_id,
                'conflicting_record_id' => $existing->id,
                'conflicting_record_data' => $existing->toArray()
            ]);

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'motorcycle_type_id' => [
                        "The motorcycle type id has already been taken by ID {$existing->id}."
                    ]
                ]
            ], 422);
        }

        $validated = $request->validate([
            'motorcycle_type_id' => 'required|exists:motorcycle_types,id',
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
