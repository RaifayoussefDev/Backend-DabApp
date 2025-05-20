<?php

namespace App\Http\Controllers;

use App\Models\PricingRulesMotorcycle;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PricingRulesMotorcycleController extends Controller
{
    // List all pricing rules
    public function index()
    {
        $rules = PricingRulesMotorcycle::with('motorcycleType')->get();

        return response()->json([
            'data' => $rules
        ]);
    }

    // Store a new pricing rule
    public function store(Request $request)
    {
        $validated = $request->validate([
            'motorcycle_type_id' => 'required|exists:motorcycle_types,id',
            'price' => 'required|numeric|min:0',
        ]);

        $rule = PricingRulesMotorcycle::create($validated);

        return response()->json([
            'message' => 'Pricing rule created successfully',
            'data' => $rule,
        ], Response::HTTP_CREATED);
    }

    // Show a specific pricing rule
    public function show(PricingRulesMotorcycle $pricingRulesMotorcycle)
    {
        return response()->json([
            'data' => $pricingRulesMotorcycle->load('motorcycleType')
        ]);
    }

    // Update a pricing rule
    public function update(Request $request, PricingRulesMotorcycle $pricingRulesMotorcycle)
    {
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

    // Delete a pricing rule
    public function destroy(PricingRulesMotorcycle $pricingRulesMotorcycle)
    {
        $pricingRulesMotorcycle->delete();

        return response()->json([
            'message' => 'Pricing rule deleted successfully',
        ]);
    }
}
