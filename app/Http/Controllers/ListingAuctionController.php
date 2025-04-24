<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Listing;
use App\Models\AuctionHistory;
use Illuminate\Support\Facades\DB;

class ListingAuctionController extends Controller
{
    // === LISTINGS CRUD ===

    public function listingsIndex() {
        return response()->json(Listing::all());
    }


    public function store(Request $request)
    {
        $request->validate([
            'listing.title' => 'required|string',
            'listing.price' => 'required|numeric',
            'listing.price_type' => 'required|string|in:fixed,auction',
            'listing.seller_id' => 'required|exists:users,id',
            'listing.product_state_id' => 'required|exists:product_states,id',
            // Ajoutez d'autres règles de validation pour les champs de listing
            // 'auction.buyer_id' => 'required|exists:users,id',
            'auction.bid_amount' => 'required|numeric',
            // 'auction.bid_date' => 'required|date',
            // Ajoutez d'autres règles de validation pour les champs d'enchère
        ]);

        DB::beginTransaction();

        try {
            $listingData = $request->input('listing');
            $listing = Listing::create($listingData);

            $auctionData = $request->input('auction');
            $auctionData['listing_id'] = $listing->id;
            $auctionData['seller_id'] = $listing->seller_id;
            $auction = AuctionHistory::create($auctionData);

            DB::commit();

            return response()->json([
                'message' => 'Listing and auction created successfully',
                'listing' => $listing,
                'auction' => $auction
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while creating listing and auction',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function listingsStore(Request $request) {
        $validated = $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'price_type' => 'required|string|in:fixed,auction',
            'seller_id' => 'required|exists:users,id',
            'category_id' => 'nullable|integer',
            'country_id' => 'nullable|integer',
            'city_id' => 'nullable|integer',
            'status' => 'nullable|string',
            'auction_enabled' => 'boolean',
            'minimum_bid' => 'nullable|numeric',
            'product_state_id' => 'required|exists:product_states,id',
            'finish_id' => 'nullable|exists:finishes,id',
            'color_id' => 'nullable|exists:colors,id',
            'allow_submission' => 'boolean',
            'listing_type_id' => 'nullable|exists:listing_types,id'
        ]);

        $listing = Listing::create($validated);
        return response()->json($listing, 201);
    }

    public function listingsShow($id) {
        return response()->json(Listing::findOrFail($id));
    }

    public function listingsUpdate(Request $request, $id) {
        $listing = Listing::findOrFail($id);
        $listing->update($request->all());
        return response()->json($listing);
    }

    public function listingsDestroy($id) {
        Listing::findOrFail($id)->delete();
        return response()->json(['message' => 'Listing deleted']);
    }

    public function myListings() {
        return response()->json(Listing::where('seller_id', Auth::id())->get());
    }

    // === AUCTIONS CRUD ===

    public function auctionsIndex() {
        return response()->json(AuctionHistory::all());
    }

    public function auctionsStore(Request $request) {
        $validated = $request->validate([
            'listing_id' => 'required|exists:listings,id',
            'seller_id' => 'required|exists:users,id',
            'buyer_id' => 'required|exists:users,id',
            'bid_amount' => 'required|numeric',
            'bid_date' => 'required|date',
            'validated' => 'boolean',
            'validated_at' => 'nullable|date',
            'validator_id' => 'nullable|exists:users,id',
        ]);

        $validated['created_at'] = now();
        $validated['updated_at'] = now();

        $auction = AuctionHistory::create($validated);
        return response()->json($auction, 201);
    }

    public function auctionsShow($id) {
        return response()->json(AuctionHistory::findOrFail($id));
    }

    public function auctionsUpdate(Request $request, $id) {
        $auction = AuctionHistory::findOrFail($id);
        $auction->update($request->all());
        return response()->json($auction);
    }

    public function auctionsDestroy($id) {
        AuctionHistory::findOrFail($id)->delete();
        return response()->json(['message' => 'Auction deleted']);
    }

    public function myAuctions() {
        return response()->json(AuctionHistory::where('buyer_id', Auth::id())->get());
    }
}
