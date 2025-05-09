<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Listing;
use App\Models\AuctionHistory;
use Illuminate\Support\Facades\DB;

class ListingAuctionController extends Controller
{
    // /**
    //  * @OA\Get(
    //  *     path="/api/listings",
    //  *     summary="Get all listings",
    //  *     tags={"Listings"},
    //  *     @OA\Response(
    //  *         response=200,
    //  *         description="List of listings"
    //  *     )
    //  * )
    //  */
    public function listingsIndex() {
        return response()->json(Listing::all());
    }

    // /**
    //  * @OA\Post(
    //  *     path="/api/listings/with-auction",
    //  *     summary="Create a listing with an auction",
    //  *     tags={"Listings", "Auctions"},
    //  *     @OA\RequestBody(
    //  *         required=true,
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="listing", type="object",
    //  *                 @OA\Property(property="title", type="string"),
    //  *                 @OA\Property(property="price", type="number"),
    //  *                 @OA\Property(property="price_type", type="string"),
    //  *                 @OA\Property(property="seller_id", type="integer"),
    //  *                 @OA\Property(property="product_state_id", type="integer")
    //  *             ),
    //  *             @OA\Property(property="auction", type="object",
    //  *                 @OA\Property(property="bid_amount", type="number")
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=201,
    //  *         description="Listing and auction created"
    //  *     )
    //  * )
    //  */
    public function store(Request $request)
    {
        $request->validate([
            'listing.title' => 'required|string',
            'listing.price' => 'required|numeric',
            'listing.price_type' => 'required|string|in:fixed,auction',
            'listing.seller_id' => 'required|exists:users,id',
            'listing.product_state_id' => 'required|exists:product_states,id',
            'auction.bid_amount' => 'required|numeric',
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

    // /**
    //  * @OA\Post(
    //  *     path="/api/listings",
    //  *     summary="Create a listing",
    //  *     tags={"Listings"},
    //  *     @OA\RequestBody(
    //  *         required=true,
    //  *         @OA\JsonContent(
    //  *             required={"title", "price", "price_type", "seller_id", "product_state_id"},
    //  *             @OA\Property(property="title", type="string"),
    //  *             @OA\Property(property="price", type="number"),
    //  *             @OA\Property(property="price_type", type="string"),
    //  *             @OA\Property(property="seller_id", type="integer"),
    //  *             @OA\Property(property="product_state_id", type="integer")
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=201,
    //  *         description="Listing created"
    //  *     )
    //  * )
    //  */
    public function listingsStore(Request $request) {
        $validated = $request->validate([
            'title' => 'required|string',
            'price' => 'required|numeric',
            'price_type' => 'required|string|in:fixed,auction',
            'seller_id' => 'required|exists:users,id',
            'product_state_id' => 'required|exists:product_states,id',
        ]);

        $listing = Listing::create($validated);
        return response()->json($listing, 201);
    }

    // /**
    //  * @OA\Get(
    //  *     path="/api/listings/{id}",
    //  *     summary="Get a listing by ID",
    //  *     tags={"Listings"},
    //  *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    //  *     @OA\Response(response=200, description="Listing found"),
    //  *     @OA\Response(response=404, description="Listing not found")
    //  * )
    //  */
    public function listingsShow($id) {
        return response()->json(Listing::findOrFail($id));
    }

    // /**
    //  * @OA\Put(
    //  *     path="/api/listings/{id}",
    //  *     summary="Update a listing",
    //  *     tags={"Listings"},
    //  *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    //  *     @OA\RequestBody(@OA\JsonContent()),
    //  *     @OA\Response(response=200, description="Listing updated")
    //  * )
    //  */
    public function listingsUpdate(Request $request, $id) {
        $listing = Listing::findOrFail($id);
        $listing->update($request->all());
        return response()->json($listing);
    }

    // /**
    //  * @OA\Delete(
    //  *     path="/api/listings/{id}",
    //  *     summary="Delete a listing",
    //  *     tags={"Listings"},
    //  *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    //  *     @OA\Response(response=200, description="Listing deleted")
    //  * )
    //  */
    public function listingsDestroy($id) {
        Listing::findOrFail($id)->delete();
        return response()->json(['message' => 'Listing deleted']);
    }

    // /**
    //  * @OA\Get(
    //  *     path="/api/my-listings",
    //  *     summary="Get my listings",
    //  *     tags={"Listings"},
    //  *     @OA\Response(response=200, description="User listings")
    //  * )
    //  */
    public function myListings() {
        return response()->json(Listing::where('seller_id', Auth::id())->get());
    }

    // /**
    //  * @OA\Get(
    //  *     path="/api/auctions",
    //  *     summary="Get all auctions",
    //  *     tags={"Auctions"},
    //  *     @OA\Response(response=200, description="List of auctions")
    //  * )
    //  */
    public function auctionsIndex() {
        return response()->json(AuctionHistory::all());
    }

    // /**
    //  * @OA\Post(
    //  *     path="/api/auctions",
    //  *     summary="Create a new auction",
    //  *     tags={"Auctions"},
    //  *     @OA\RequestBody(@OA\JsonContent(
    //  *         required={"listing_id", "seller_id", "buyer_id", "bid_amount", "bid_date"},
    //  *         @OA\Property(property="listing_id", type="integer"),
    //  *         @OA\Property(property="seller_id", type="integer"),
    //  *         @OA\Property(property="buyer_id", type="integer"),
    //  *         @OA\Property(property="bid_amount", type="number"),
    //  *         @OA\Property(property="bid_date", type="string", format="date"),
    //  *         @OA\Property(property="validated", type="boolean"),
    //  *         @OA\Property(property="validated_at", type="string", format="date"),
    //  *         @OA\Property(property="validator_id", type="integer")
    //  *     )),
    //  *     @OA\Response(response=201, description="Auction created")
    //  * )
    //  */
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

    // /**
    //  * @OA\Get(
    //  *     path="/api/auctions/{id}",
    //  *     summary="Get auction by ID",
    //  *     tags={"Auctions"},
    //  *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    //  *     @OA\Response(response=200, description="Auction found")
    //  * )
    //  */
    public function auctionsShow($id) {
        return response()->json(AuctionHistory::findOrFail($id));
    }

    // /**
    //  * @OA\Put(
    //  *     path="/api/auctions/{id}",
    //  *     summary="Update an auction",
    //  *     tags={"Auctions"},
    //  *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    //  *     @OA\RequestBody(@OA\JsonContent()),
    //  *     @OA\Response(response=200, description="Auction updated")
    //  * )
    //  */
    public function auctionsUpdate(Request $request, $id) {
        $auction = AuctionHistory::findOrFail($id);
        $auction->update($request->all());
        return response()->json($auction);
    }

    // /**
    //  * @OA\Delete(
    //  *     path="/api/auctions/{id}",
    //  *     summary="Delete an auction",
    //  *     tags={"Auctions"},
    //  *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    //  *     @OA\Response(response=200, description="Auction deleted")
    //  * )
    //  */
    public function auctionsDestroy($id) {
        AuctionHistory::findOrFail($id)->delete();
        return response()->json(['message' => 'Auction deleted']);
    }

    // /**
    //  * @OA\Get(
    //  *     path="/api/my-auctions",
    //  *     summary="Get my auctions",
    //  *     tags={"Auctions"},
    //  *     @OA\Response(response=200, description="User auctions")
    //  * )
    //  */
    public function myAuctions() {
        return response()->json(AuctionHistory::where('buyer_id', Auth::id())->get());
    }
}
