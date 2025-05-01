<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Listing;
use App\Models\AuctionHistory;
use App\Models\Motorcycle;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Auth;

class ListingController extends Controller
{
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            // 1. Créer le Listing
            $listing = Listing::create([
                'title' => $request->title,
                'description' => $request->description,
                'price' => $request->price,
                'seller_id' => $request->seller_id,
                'category_id' => $request->category_id,
                'country_id' => $request->country_id,
                'city_id' => $request->city_id,
                'status' => 'active',
                'auction_enabled' => $request->auction_enabled ?? false,
                'minimum_bid' => $request->minimum_bid,
                'allow_submission' => $request->allow_submission ?? false,
                'listing_type_id' => $request->listing_type_id,
            ]);
            if ($request->has('images') && is_array($request->images)) {
                foreach ($request->images as $imageUrl) {
                    $listing->images()->create([
                        'image_url' => $imageUrl
                    ]);
                }
            }


            // 2. Créer une AuctionHistory si nécessaire
            if ($listing->auction_enabled) {
                AuctionHistory::create([
                    'listing_id' => $listing->id,
                    'seller_id' => $listing->seller_id,
                    'bid_amount' => $listing->minimum_bid,
                ]);
            }

            // 3. Créer la moto si category_id == 1
            if ($listing->category_id == 1) {
                $motorcycle = Motorcycle::create([
                    'listing_id' => $listing->id,
                    'brand_id' => $request->brand_id,
                    'model_id' => $request->model_id,
                    'year_id' => $request->year_id,
                    'type_id' => $request->type_id,
                    'engine' => $request->engine,
                    'mileage' => $request->mileage,
                    'body_condition' => $request->body_condition,
                    'modified' => $request->has('modified') ? $request->modified : false,
                    'insurance' => $request->has('insurance') ? $request->insurance : false,
                    'general_condition' => $request->general_condition,
                    'vehicle_care' => $request->vehicle_care,
                    'transmission' => $request->transmission,
                ]);

                // ✅ COMMIT ICI
                DB::commit();

                return response()->json([
                    'message' => 'Motorcycle added successfully',
                    'data' => $motorcycle,
                ], 201);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Invalid category_id. Only category 1 is allowed for motorcycles.',
                ], 422);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create listing',
                'details' => $e->getMessage()
            ], 500);
        }
    }



    public function getByCountry($country_id)
    {
        $user = Auth::user();

        $listings = Listing::with(['images', 'city', 'country'])
            ->where('country_id', $country_id)
            ->get()
            ->map(function ($listing) use ($user) {
                $isInWishlist = false;

                if ($user) {
                    $isInWishlist = DB::table('wishlists')
                        ->where('user_id', $user->id)
                        ->where('listing_id', $listing->id)
                        ->exists();
                }

                return [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'description' => $listing->description,
                    'price' => $listing->price,
                    'created_at' => $listing->created_at->format('Y-m-d H:i:s'),
                    'city' => $listing->city ? $listing->city->name : null,
                    'country' => $listing->country ? $listing->country->name : null,
                    'images' => $listing->images->pluck('image_url'),
                    'wishlist' => $isInWishlist,
                ];
            });

        return response()->json($listings);
    }
}
