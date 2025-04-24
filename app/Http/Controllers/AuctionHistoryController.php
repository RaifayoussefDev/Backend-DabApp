<?php

namespace App\Http\Controllers;

use App\Models\AuctionHistory;
use Illuminate\Http\Request;

class AuctionHistoryController extends Controller
{
    public function index()
    {
        return AuctionHistory::with(['listing', 'buyer', 'seller'])->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'listing_id' => 'required|exists:listings,id',
            'seller_id'  => 'required|exists:users,id',
            'buyer_id'   => 'required|exists:users,id',
            'bid_amount' => 'required|numeric|min:0',
            'bid_date'   => 'required|date',
        ]);

        $history = AuctionHistory::create($validated);
        return response()->json($history, 201);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    public function show($id)
    {
        return AuctionHistory::with(['listing', 'buyer', 'seller'])->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $history = AuctionHistory::findOrFail($id);

        $validated = $request->validate([
            'validated' => 'boolean',
            'validated_at' => 'nullable|date',
            'validator_id' => 'nullable|exists:users,id'
        ]);

        $history->update($validated);
        return response()->json($history);
    }

    public function destroy($id)
    {
        AuctionHistory::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
