<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Listing;
use App\Models\AuctionHistory;
use App\Models\Motorcycle;
use Illuminate\Support\Facades\DB;

class ListingController extends Controller
{
    public function store(Request $request)
    {
        // On fait tout dans une transaction DB pour éviter les erreurs
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

            // 2. Si auction_enabled = true, on crée une première Auction History
            if ($listing->auction_enabled) {
                AuctionHistory::create([
                    'listing_id' => $listing->id,
                    'seller_id' => $listing->seller_id, // Utiliser seller_id du listing
                    'bid_amount' => $listing->minimum_bid,
                ]);
            }

            // 3. Si category_id == 1, on ajoute la moto
            if ($listing->category_id == 1) {
                // Vérification de la présence de toutes les données nécessaires avant l'insertion
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

                // Retourne une réponse de succès
                return response()->json([
                    'message' => 'Motorcycle added successfully',
                    'data' => $motorcycle,
                ], 201); // Code HTTP 201 pour une ressource créée avec succès
            } else {
                // Retourne une erreur 422 si category_id n'est pas égal à 1
                return response()->json([
                    'message' => 'Invalid category_id. Only category 1 is allowed for motorcycles.',
                ], 422); // Code HTTP 422 pour une erreur de validation
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create listing',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
