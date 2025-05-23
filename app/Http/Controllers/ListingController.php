<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Listing;
use App\Models\AuctionHistory;
use App\Models\CurrencyExchangeRate;
use App\Models\LicensePlate;
use App\Models\Motorcycle;
use App\Models\MotorcycleModel;
use App\Models\PricingRulesLicencePlate;
use App\Models\PricingRulesMotorcycle;
use App\Models\PricingRulesSparepart;
use App\Models\SparePart;
use App\Models\SparePartMotorcycle;
use CreatePricingRulesLicencePlateTable;
use CreatePricingRulesSparepartTable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Auth;
use Str;

class ListingController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/listings",
     *     summary="Créer une annonce",
     *     tags={"Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     required={"title", "description", "price", "category_id", "brand_id", "model_id", "year_id"},
     *                     @OA\Property(property="category_id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Yamaha MT-07"),
     *                     @OA\Property(property="description", type="string", example="Moto bien entretenue"),
     *                     @OA\Property(property="price", type="number", format="float", example=5000),
     *                     @OA\Property(property="country_id", type="integer", example=1),
     *                     @OA\Property(property="city_id", type="integer", example=10),
     *                     @OA\Property(property="auction_enabled", type="boolean", example=false),
     *                     @OA\Property(property="minimum_bid", type="number", example=null),
     *                     @OA\Property(property="allow_submission", type="boolean", example=false),
     *                     @OA\Property(property="listing_type_id", type="integer", example=1),
     *                     @OA\Property(property="contacting_channel", type="string", example="phone"),
     *                     @OA\Property(property="seller_type", type="string", example="owner"),
     *                     @OA\Property(property="images", type="array", @OA\Items(type="string", format="binary")),
     *                     @OA\Property(property="brand_id", type="integer", example=1),
     *                     @OA\Property(property="model_id", type="integer", example=2),
     *                     @OA\Property(property="year_id", type="integer", example=2020),
     *                     @OA\Property(property="type_id", type="integer", example=3),
     *                     @OA\Property(property="engine", type="string", example="700cc"),
     *                     @OA\Property(property="mileage", type="integer", example=15000),
     *                     @OA\Property(property="body_condition", type="string", example="Good"),
     *                     @OA\Property(property="modified", type="boolean", example=false),
     *                     @OA\Property(property="insurance", type="boolean", example=true),
     *                     @OA\Property(property="general_condition", type="string", example="Excellent"),
     *                     @OA\Property(property="vehicle_care", type="string", example="Regular maintenance"),
     *                     @OA\Property(property="transmission", type="string", example="Manual")
     *                 ),
     *                 @OA\Schema(
     *                     required={"title", "description", "price", "category_id", "condition"},
     *                     @OA\Property(property="category_id", type="integer", example=2),
     *                     @OA\Property(property="title", type="string", example="Pneu arrière"),
     *                     @OA\Property(property="description", type="string", example="Pneu en bon état"),
     *                     @OA\Property(property="price", type="number", format="float", example=200),
     *                     @OA\Property(property="country_id", type="integer", example=1),
     *                     @OA\Property(property="city_id", type="integer", example=5),
     *                     @OA\Property(property="auction_enabled", type="boolean", example=false),
     *                     @OA\Property(property="minimum_bid", type="number", example=null),
     *                     @OA\Property(property="allow_submission", type="boolean", example=false),
     *                     @OA\Property(property="listing_type_id", type="integer", example=2),
     *                     @OA\Property(property="contacting_channel", type="string", example="email"),
     *                     @OA\Property(property="seller_type", type="string", example="dealer"),
     *                     @OA\Property(property="images", type="array", @OA\Items(type="string", format="binary")),
     *                     @OA\Property(property="condition", type="string", example="used"),
     *                     @OA\Property(
     *                         property="motorcycles",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             required={"brand_id", "model_id", "year_id"},
     *                             @OA\Property(property="brand_id", type="integer", example=1),
     *                             @OA\Property(property="model_id", type="integer", example=2),
     *                             @OA\Property(property="year_id", type="integer", example=2020)
     *                         )
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     required={"title", "description", "price", "category_id", "characters", "digits_count", "color_id"},
     *                     @OA\Property(property="category_id", type="integer", example=3),
     *                     @OA\Property(property="title", type="string", example="Plaque personnalisée"),
     *                     @OA\Property(property="description", type="string", example="Plaque ABC123 rouge"),
     *                     @OA\Property(property="price", type="number", format="float", example=800),
     *                     @OA\Property(property="country_id", type="integer", example=1),
     *                     @OA\Property(property="city_id", type="integer", example=8),
     *                     @OA\Property(property="auction_enabled", type="boolean", example=true),
     *                     @OA\Property(property="minimum_bid", type="number", example=500),
     *                     @OA\Property(property="allow_submission", type="boolean", example=true),
     *                     @OA\Property(property="listing_type_id", type="integer", example=3),
     *                     @OA\Property(property="contacting_channel", type="string", example="whatsapp"),
     *                     @OA\Property(property="seller_type", type="string", example="owner"),
     *                     @OA\Property(property="images", type="array", @OA\Items(type="string", format="binary")),
     *                     @OA\Property(property="first_letter", type="string", example="A"),
     *                    @OA\Property(property="second_letter", type="string", example="B"),
     *                    @OA\Property(property="third_letter", type="string", example="C"),
     *                     @OA\Property(property="digits_count", type="integer", example=6),
     *                     @OA\Property(property="color_id", type="integer", example=1),
     *                     @OA\Property(property="type_id", type="integer", example=1)
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Annonce créée avec succès"
     *     )
     * )
     */

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $sellerId = Auth::id();

            if (!$sellerId) {
                return response()->json([
                    'message' => 'Unauthorized. User must be logged in to create a listing.',
                ], 401);
            }

            // Create the listing
            $listing = Listing::create([
                'title' => $request->title,
                'description' => $request->description,
                'price' => $request->price,
                'seller_id' => $sellerId,
                'category_id' => $request->category_id,
                'country_id' => $request->country_id,
                'city_id' => $request->city_id,
                'status' => 'active',
                'auction_enabled' => $request->auction_enabled ?? false,
                'minimum_bid' => $request->minimum_bid,
                'allow_submission' => $request->allow_submission ?? false,
                'listing_type_id' => $request->listing_type_id,
                'contacting_channel' => $request->contacting_channel,
                'seller_type' => $request->seller_type,
                'created_at' => now(),
            ]);

            // if ($request->hasFile('images')) {
            //     foreach ($request->file('images') as $image) {
            //         $path = $image->store('listings', 'public'); // stocke dans storage/app/public/listings
            //         $listing->images()->create([
            //             'image_url' => 'storage/' . $path
            //         ]);
            //     }
            // }
            if ($request->has('images')) {
                foreach ($request->images as $imageUrl) {
                    $listing->images()->create([
                        'image_url' => $imageUrl
                    ]);
                }
            }
            // Auction logic
            if ($listing->auction_enabled) {
                AuctionHistory::create([
                    'listing_id' => $listing->id,
                    'seller_id' => $listing->seller_id,
                    'bid_amount' => $listing->minimum_bid,
                ]);
            }

            // Category-specific logic
            if ($listing->category_id == 1) {
                // Récupérer le type_id depuis le model_id
                $model = MotorcycleModel::find($request->model_id);

                if (!$model) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Invalid model_id: Model not found.',
                    ], 422);
                }

                $motorcycle = Motorcycle::create([
                    'listing_id' => $listing->id,
                    'brand_id' => $request->brand_id,
                    'model_id' => $request->model_id,
                    'year_id' => $request->year_id,
                    'type_id' => $model->type_id, // ici on récupère depuis le model
                    'engine' => $request->engine,
                    'mileage' => $request->mileage,
                    'body_condition' => $request->body_condition,
                    'modified' => $request->has('modified') ? $request->modified : false,
                    'insurance' => $request->has('insurance') ? $request->insurance : false,
                    'general_condition' => $request->general_condition,
                    'vehicle_care' => $request->vehicle_care,
                    'transmission' => $request->transmission,
                ]);

                DB::commit();

                return response()->json([
                    'message' => 'Motorcycle added successfully',
                    'data' => $motorcycle,
                ], 201);
            } elseif ($listing->category_id == 2) {
                $sparePart = SparePart::create([
                    'listing_id' => $listing->id,
                    'condition' => $request->condition,
                    'bike_part_brand_id' => $request->bike_part_brand_id,
                    'bike_part_category_id' => $request->bike_part_category_id,
                ]);

                // Ajouter les associations moto
                if ($request->has('motorcycles')) {
                    foreach ($request->motorcycles as $moto) {
                        SparePartMotorcycle::create([
                            'spare_part_id' => $sparePart->id,
                            'brand_id' => $moto['brand_id'],
                            'model_id' => $moto['model_id'],
                            'year_id' => $moto['year_id'],
                        ]);
                    }
                }

                DB::commit();

                return response()->json([
                    'message' => 'Spare part added successfully',
                    'data' => $sparePart->load('motorcycleAssociations.brand', 'motorcycleAssociations.model', 'motorcycleAssociations.year'),
                ], 201);
            } elseif ($listing->category_id == 3) {
                // Vérifier si le type est 1
                $typeId = $request->type_id;
            
                $licensePlateData = [
                    'listing_id' => $listing->id,
                    'country_id' => $request->country_id_lp, // Utilisez country_id_lp pour la plaque
                    'type_id' => $typeId,
                    'digits_count' => $request->digits_count,
                    'city_id' => $request->city_id_lp ?? $request->city_id, // Utilisez city_id_lp si fourni, sinon city_id du listing
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            
                if ($typeId == 1) {
                    // Cas normal : on utilise les lettres
                    $licensePlateData['first_letter'] = $request->first_letter;
                    $licensePlateData['second_letter'] = $request->second_letter;
                    $licensePlateData['third_letter'] = $request->third_letter;
                    $licensePlateData['numbers'] = $request->numbers;
                } else {
                    // Cas spécial : type != 1 -> lettres nulles
                    $licensePlateData['first_letter'] = null;
                    $licensePlateData['second_letter'] = null;
                    $licensePlateData['third_letter'] = null;
                    $licensePlateData['numbers'] = $request->numbers;
                }
            
                $licensePlate = LicensePlate::create($licensePlateData);
            
                DB::commit();
            
                return response()->json([
                    'message' => 'License plate added successfully',
                    'data' => $licensePlate,
                ], 201);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Invalid category_id. Only categories 1, 2, or 3 are allowed.',
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

    /**
     * @OA\Get(
     *     path="/api/listings/country/{country_id}",
     *     summary="Get listings by country",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="country_id",
     *         in="path",
     *         required=true,
     *         description="Country ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of listings",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="price", type="number"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="city", type="string"),
     *                 @OA\Property(property="country", type="string"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="wishlist", type="boolean")
     *             )
     *         )
     *     )
     * )
     */
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
    /**
     * @OA\Get(
     *     path="/api/listings/category/{category_id}",
     *     summary="Get listings by category",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="category_id",
     *         in="path",
     *         required=true,
     *         description="Category ID (1=Motorcycle, 2=SparePart, 3=LicensePlate)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of listings",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="price", type="number"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="city", type="string"),
     *                 @OA\Property(property="country", type="string"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="wishlist", type="boolean")
     *             )
     *         )
     *     )
     * )
     */
    public function getByCategory($category_id)
    {
        $user = Auth::user();

        $listings = Listing::with(['images', 'city', 'country'])
            ->where('category_id', $category_id)
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
                    'city' => $listing->city?->name,
                    'country' => $listing->country?->name,
                    'images' => $listing->images->pluck('image_url'),
                    'wishlist' => $isInWishlist,
                ];
            });

        return response()->json($listings);
    }
    /**
     * @OA\Get(
     *     path="/api/listings/city/{city_id}",
     *     summary="Get listings by city",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="city_id",
     *         in="path",
     *         required=true,
     *         description="City ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of listings",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="price", type="number"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="city", type="string"),
     *                 @OA\Property(property="country", type="string"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="wishlist", type="boolean")
     *             )
     *         )
     *     )
     * )
     */

    public function getByCity($city_id)
    {
        $user = Auth::user();

        $listings = Listing::with(['images', 'city', 'country'])
            ->where('city_id', $city_id)
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
                    'city' => $listing->city?->name,
                    'country' => $listing->country?->name,
                    'images' => $listing->images->pluck('image_url'),
                    'wishlist' => $isInWishlist,
                ];
            });

        return response()->json($listings);
    }
    /**
     * @OA\Get(
     *     path="/api/listings/filter",
     *     summary="Filter listings",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="city_id",
     *         in="query",
     *         required=false,
     *         description="City ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         required=false,
     *         description="Country ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         required=false,
     *         description="Category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Filtered listings",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="price", type="number"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="city", type="string"),
     *                 @OA\Property(property="country", type="string"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="wishlist", type="boolean")
     *             )
     *         )
     *     )
     * )
     */
    public function filter(Request $request)
    {
        $user = Auth::user();

        $query = Listing::with(['images', 'city', 'country']);

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $listings = $query->get()->map(function ($listing) use ($user) {
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
                'city' => $listing->city?->name,
                'country' => $listing->country?->name,
                'images' => $listing->images->pluck('image_url'),
                'wishlist' => $isInWishlist,
            ];
        });

        return response()->json($listings);
    }
    /**
     * @OA\Get(
     *     path="/api/listings/latest/{city_id}",
     *     summary="Get latest listings by city",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="city_id",
     *         in="path",
     *         required=true,
     *         description="City ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Latest 10 listings",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="price", type="number"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="city", type="string"),
     *                 @OA\Property(property="country", type="string"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="wishlist", type="boolean")
     *             )
     *         )
     *     )
     * )
     */
    public function getLastByCity($city_id)
    {
        $user = Auth::user();

        $listings = Listing::with(['images', 'city', 'country'])
            ->where('city_id', $city_id)
            ->orderBy('created_at', 'desc')
            ->take(10)
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
                    'city' => $listing->city?->name,
                    'country' => $listing->country?->name,
                    'images' => $listing->images->pluck('image_url'),
                    'wishlist' => $isInWishlist,
                ];
            });

        return response()->json($listings);
    }

    /**
     * @OA\Get(
     *     path="/api/listings/{id}",
     *     summary="Get listing by ID",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Listing ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Listing details",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="price", type="number"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="country", type="string"),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="wishlist", type="boolean"),
     *             @OA\Property(property="category_id", type="integer"),
     *             @OA\Property(
     *                 property="motorcycle",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(property="engine", type="string"),
     *                 @OA\Property(property="mileage", type="integer"),
     *                 @OA\Property(property="body_condition", type="string"),
     *                 @OA\Property(property="modified", type="boolean"),
     *                 @OA\Property(property="insurance", type="boolean"),
     *                 @OA\Property(property="general_condition", type="string"),
     *                 @OA\Property(property="vehicle_care", type="string"),
     *                 @OA\Property(property="transmission", type="string"),
     *                 @OA\Property(property="brand", type="string"),
     *                 @OA\Property(property="model", type="string"),
     *                 @OA\Property(property="year", type="integer"),
     *                 @OA\Property(property="type", type="string")
     *             ),
     *             @OA\Property(
     *                 property="license_plate",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(property="characters", type="string"),
     *                 @OA\Property(property="digits_count", type="integer"),
     *                 @OA\Property(property="country_id", type="integer"),
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(property="color", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Listing not found"
     *     )
     * )
     */
    public function getById($id)
    {
        $user = Auth::user();

        $listing = Listing::with(['images', 'city', 'country'])->find($id);

        if (!$listing) {
            return response()->json(['message' => 'Listing not found'], 404);
        }

        $isInWishlist = false;
        if ($user) {
            $isInWishlist = DB::table('wishlists')
                ->where('user_id', $user->id)
                ->where('listing_id', $listing->id)
                ->exists();
        }

        $data = [
            'id' => $listing->id,
            'title' => $listing->title,
            'description' => $listing->description,
            'price' => $listing->price,
            'created_at' => $listing->created_at->format('Y-m-d H:i:s'),
            'city' => $listing->city?->name,
            'country' => $listing->country?->name,
            'images' => $listing->images->pluck('image_url'),
            'wishlist' => $isInWishlist,
            'category_id' => $listing->category_id,
        ];

        // If Motorcycle
        if ($listing->category_id == 1) {
            $motorcycle = Motorcycle::with(['brand', 'model', 'year', 'type'])
                ->where('listing_id', $listing->id)
                ->first();

            if ($motorcycle) {
                $data['motorcycle'] = [
                    'engine' => $motorcycle->engine,
                    'mileage' => $motorcycle->mileage,
                    'body_condition' => $motorcycle->body_condition,
                    'modified' => $motorcycle->modified,
                    'insurance' => $motorcycle->insurance,
                    'general_condition' => $motorcycle->general_condition,
                    'vehicle_care' => $motorcycle->vehicle_care,
                    'transmission' => $motorcycle->transmission,
                    'brand' => $motorcycle->brand?->name,
                    'model' => $motorcycle->model?->name,
                    'year' => $motorcycle->year?->year,
                    'type' => $motorcycle->type?->name,
                ];
            }
        }

        // If License Plate
        if ($listing->category_id == 3) {
            $plate = LicensePlate::with(['type', 'color'])
                ->where('listing_id', $listing->id)
                ->first();

            if ($plate) {
                $data['license_plate'] = [
                    'characters' => $plate->characters,
                    'digits_count' => $plate->digits_count,
                    'country_id' => $plate->country_id,
                    'type' => $plate->type?->name,
                    'color' => $plate->color?->name,
                ];
            }
        }

        return response()->json($data);
    }
    /**
     * @OA\Get(
     *     path="/api/listings",
     *     summary="Get all listings with pagination",
     *     tags={"Listings"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of listings",
     *         @OA\JsonContent(
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="last_page", type="integer"),
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="price", type="number"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="city", type="string"),
     *                     @OA\Property(property="country", type="string"),
     *                     @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="wishlist", type="boolean"),
     *                     @OA\Property(property="category_id", type="integer"),
     *                     @OA\Property(
     *                         property="motorcycle",
     *                         type="object",
     *                         nullable=true
     *                     ),
     *                     @OA\Property(
     *                         property="license_plate",
     *                         type="object",
     *                         nullable=true
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */

    public function getAll(Request $request)
    {
        $user = Auth::user();
        $perPage = 10;

        $listings = Listing::with(['images', 'city', 'country'])
            ->paginate($perPage);

        $data = $listings->map(function ($listing) use ($user) {
            $isInWishlist = false;

            if ($user) {
                $isInWishlist = DB::table('wishlists')
                    ->where('user_id', $user->id)
                    ->where('listing_id', $listing->id)
                    ->exists();
            }

            $item = [
                'id' => $listing->id,
                'title' => $listing->title,
                'description' => $listing->description,
                'price' => $listing->price,
                'created_at' => $listing->created_at->format('Y-m-d H:i:s'),
                'city' => $listing->city?->name,
                'country' => $listing->country?->name,
                'images' => $listing->images->pluck('image_url'),
                'wishlist' => $isInWishlist,
                'category_id' => $listing->category_id,
            ];

            if ($listing->category_id == 1) {
                $motorcycle = Motorcycle::with(['brand', 'model', 'year', 'type'])
                    ->where('listing_id', $listing->id)
                    ->first();

                if ($motorcycle) {
                    $item['motorcycle'] = [
                        'engine' => $motorcycle->engine,
                        'mileage' => $motorcycle->mileage,
                        'body_condition' => $motorcycle->body_condition,
                        'modified' => $motorcycle->modified,
                        'insurance' => $motorcycle->insurance,
                        'general_condition' => $motorcycle->general_condition,
                        'vehicle_care' => $motorcycle->vehicle_care,
                        'transmission' => $motorcycle->transmission,
                        'brand' => $motorcycle->brand?->name,
                        'model' => $motorcycle->model?->name,
                        'year' => $motorcycle->year?->year,
                        'type' => $motorcycle->type?->name,
                    ];
                }
            }

            if ($listing->category_id == 3) {
                $plate = LicensePlate::with(['type', 'color'])
                    ->where('listing_id', $listing->id)
                    ->first();

                if ($plate) {
                    $item['license_plate'] = [
                        'characters' => $plate->characters,
                        'digits_count' => $plate->digits_count,
                        'country_id' => $plate->country_id,
                        'type' => $plate->type?->name,
                        'color' => $plate->color?->name,
                    ];
                }
            }

            return $item;
        });

        return response()->json([
            'current_page' => $listings->currentPage(),
            'last_page' => $listings->lastPage(),
            'total' => $listings->total(),
            'per_page' => $listings->perPage(),
            'data' => $data,
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/my-listing",
     *     summary="Récupérer les annonces de l'utilisateur connecté avec les détails selon la catégorie",
     *     tags={"Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des annonces de l'utilisateur",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function my_listing()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $listings = Listing::where('seller_id', $user->id)
            ->with([
                'images',
                'country',
                'city',
                'listingType',
                'motorcycle',       // relation hasOne
                'sparePart.motorcycleAssociations.brand',
                'sparePart.motorcycleAssociations.model',
                'sparePart.motorcycleAssociations.year',
                'licensePlate'
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($listing) {
                $data = [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'description' => $listing->description,
                    'price' => $listing->price,
                    'category_id' => $listing->category_id,
                    'status' => $listing->status,
                    'created_at' => $listing->created_at,
                    'images' => $listing->images,
                    'country' => $listing->country,
                    'city' => $listing->city,
                    'listing_type' => $listing->listingType,
                ];

                // Ajouter les détails spécifiques à la catégorie
                if ($listing->category_id == 1 && $listing->motorcycle) {
                    $data['details'] = $listing->motorcycle;
                } elseif ($listing->category_id == 2 && $listing->sparePart) {
                    $data['details'] = $listing->sparePart;
                } elseif ($listing->category_id == 3 && $listing->licensePlate) {
                    $data['details'] = $listing->licensePlate;
                } else {
                    $data['details'] = null;
                }

                return $data;
            });

        return response()->json($listings, 200);
    }


    /**
     * @OA\Get(
     *     path="/api/pricing",
     *     summary="Get price by model ID",
     *     tags={"Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="model_id",
     *         in="query",
     *         required=true,
     *         description="Model ID"
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         required=true,
     *         description="Category ID"
     *     ),
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         required=true,
     *         description="Country ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Price details"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found"
     *     )
     * )
     */


     public function getPriceByModelId(Request $request)
     {
         $modelId = $request->input('model_id');
         $categoryId = $request->input('category_id');
         $countryId = $request->input('country_id');
     
         $currency = CurrencyExchangeRate::where('country_id', $countryId)->first();
     
         if (!$currency) {
             return response()->json([
                 'message' => 'No exchange rate found for this country'
             ], 404);
         }
     
         $exchangeRate = $currency->exchange_rate;
         $currencySymbol = $currency->currency_symbol;
     
         if ($categoryId == 1 && $modelId) {
             $model = MotorcycleModel::find($modelId);
     
             if (!$model) {
                 return response()->json([
                     'message' => 'No motorcycle model found with this ID'
                 ], 404);
             }
     
             $typeId = $model->type_id;
     
             $pricingRule = PricingRulesMotorcycle::where('motorcycle_type_id', $typeId)->first();
     
             if (!$pricingRule) {
                 return response()->json([
                     'message' => 'No pricing rule found for this motorcycle type'
                 ], 404);
             }
     
             $priceConverted = round($pricingRule->price * $exchangeRate, 2);
     
             return response()->json([
                 'model_id' => $modelId,
                 'motorcycle_type_id' => $typeId,
                 'price_sar' => $pricingRule->price,
                 'converted_price' => $priceConverted,
                 'currency_symbol' => $currencySymbol
             ]);
         }
     
         elseif ($categoryId == 2 && $modelId) {
             $pricingRule = PricingRulesSparepart::where('bike_part_category_id', $modelId)->first();
     
             if (!$pricingRule) {
                 return response()->json([
                     'message' => 'No pricing rule found for this bike part category'
                 ], 404);
             }
     
             $priceConverted = round($pricingRule->price * $exchangeRate, 2);
     
             return response()->json([
                 'bike_part_category_id' => $modelId,
                 'price_sar' => $pricingRule->price,
                 'converted_price' => $priceConverted,
                 'currency_symbol' => $currencySymbol
             ]);
         }
     
         // 👇 Ajout pour category_id == 3 (plaque)
         elseif ($categoryId == 3) {
            // Just fetch the first pricing rule from the table
            $pricingRule = PricingRulesLicencePlate::first();
        
            if (!$pricingRule) {
                return response()->json([
                    'message' => 'No pricing rule found for licence plates'
                ], 404);
            }
        
            $priceConverted = round($pricingRule->price * $exchangeRate, 2);
        
            return response()->json([
                'licence_plate_rule_id' => $pricingRule->id,
                'price_sar' => $pricingRule->price,
                'converted_price' => $priceConverted,
                'currency_symbol' => $currencySymbol
            ]);
        }
        
     }
     



}
