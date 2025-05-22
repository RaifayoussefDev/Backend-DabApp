<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CurrencyExchangeRate;
use App\Models\Country;
use Illuminate\Http\Request;

class CurrencyExchangeRateController extends Controller
{
    public function index()
    {
        $rates = CurrencyExchangeRate::with('country')->get();
        return response()->json($rates);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'currency_code' => 'required|string|max:10',
            'currency_symbol' => 'required|string|max:5',
            'exchange_rate' => 'required|numeric|min:0',
        ]);

        $rate = CurrencyExchangeRate::create($validated);
        return response()->json($rate, 201);
    }

    public function show($id)
    {
        $rate = CurrencyExchangeRate::with('country')->findOrFail($id);
        return response()->json($rate);
    }

    public function update(Request $request, $id)
    {
        $rate = CurrencyExchangeRate::findOrFail($id);

        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'currency_code' => 'required|string|max:10',
            'currency_symbol' => 'required|string|max:5',
            'exchange_rate' => 'required|numeric|min:0',
        ]);

        $rate->update($validated);
        return response()->json($rate);
    }

    public function destroy($id)
    {
        $rate = CurrencyExchangeRate::findOrFail($id);
        $rate->delete();

        return response()->json(['message' => 'Taux supprimé avec succès']);
    }
}
