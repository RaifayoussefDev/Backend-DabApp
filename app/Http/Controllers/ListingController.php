<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Listing;
use App\Models\AuctionHistory;
use App\Models\LicensePlate;
use App\Models\Motorcycle;
use App\Models\SparePart;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Auth;

class ListingController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/listings/motorcycles",
     *     summary="Create a new motorcycle listing",
     *     tags={"Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "description", "price", "category_id", "brand_id", "model_id", "year_id"},
     *             @OA\Property(property="title", type="string", example="Yamaha MT-07 à vendre"),
     *             @OA\Property(property="description", type="string", example="Moto en très bon état"),
     *             @OA\Property(property="price", type="number", format="float", example=5500),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="brand_id", type="integer", example=2),
     *             @OA\Property(property="model_id", type="integer", example=3),
     *             @OA\Property(property="year_id", type="integer", example=2021),
     *             @OA\Property(property="type_id", type="integer", example=1),
     *             @OA\Property(property="engine", type="string", example="700cc"),
     *             @OA\Property(property="mileage", type="integer", example=15000),
     *             @OA\Property(property="body_condition", type="string", example="Très bon"),
     *             @OA\Property(property="modified", type="boolean", example=false),
     *             @OA\Property(property="insurance", type="boolean", example=true),
     *             @OA\Property(property="general_condition", type="string", example="Excellent"),
     *             @OA\Property(property="vehicle_care", type="string", example="Toujours entretenue chez Yamaha"),
     *             @OA\Property(property="transmission", type="string", example="Manuelle"),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string", example="https://url/image.jpg"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Motorcycle listing created"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid category_id"
     *     )
     * )
     *
     * @OA\Post(
     *     path="/api/listings/spareparts",
     *     summary="Create a new spare part listing",
     *     tags={"Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "description", "price", "category_id", "brand_id", "model_id", "year_id", "condition"},
     *             @OA\Property(property="title", type="string", example="Disque de frein"),
     *             @OA\Property(property="description", type="string", example="Disque avant compatible MT-07"),
     *             @OA\Property(property="price", type="number", format="float", example=120),
     *             @OA\Property(property="category_id", type="integer", example=2),
     *             @OA\Property(property="brand_id", type="integer", example=4),
     *             @OA\Property(property="model_id", type="integer", example=10),
     *             @OA\Property(property="year_id", type="integer", example=2020),
     *             @OA\Property(property="condition", type="string", example="used"),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string", example="https://url/image.jpg"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Spare part listing created"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid category_id"
     *     )
     * )
     *
     * @OA\Post(
     *     path="/api/listings/licenseplates",
     *     summary="Create a new license plate listing",
     *     tags={"Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "description", "price", "category_id", "characters", "type_id", "color_id", "digits_count"},
     *             @OA\Property(property="title", type="string", example="Plaque 123ABC75"),
     *             @OA\Property(property="description", type="string", example="Plaque ancienne d’Île-de-France"),
     *             @OA\Property(property="price", type="number", format="float", example=300),
     *             @OA\Property(property="category_id", type="integer", example=3),
     *             @OA\Property(property="characters", type="string", example="123ABC75"),
     *             @OA\Property(property="type_id", type="integer", example=1),
     *             @OA\Property(property="color_id", type="integer", example=2),
     *             @OA\Property(property="digits_count", type="integer", example=7),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string", example="https://url/image.jpg"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="License plate listing created"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid category_id"
     *     )
     * )
     */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            // Get the authenticated user's ID
            $sellerId = Auth::id();

            if (!$sellerId) {
                return response()->json([
                    'message' => 'Unauthorized. User must be logged in to create a listing.',
                ], 401);
            }

            // 1. Create the listing
            $listing = Listing::create([
                'title' => $request->title,
                'description' => $request->description,
                'price' => $request->price,
                'seller_id' => $sellerId, // Use authenticated user's ID
                'category_id' => $request->category_id,
                'country_id' => $request->country_id,
                'city_id' => $request->city_id,
                'status' => 'active',
                'auction_enabled' => $request->auction_enabled ?? false,
                'minimum_bid' => $request->minimum_bid,
                'allow_submission' => $request->allow_submission ?? false,
                'listing_type_id' => $request->listing_type_id,
            ]);

            // 2. Add images if provided
            if ($request->has('images') && is_array($request->images)) {
                foreach ($request->images as $imageUrl) {
                    $listing->images()->create([
                        'image_url' => $imageUrl
                    ]);
                }
            }

            // 3. Create AuctionHistory if needed
            if ($listing->auction_enabled) {
                AuctionHistory::create([
                    'listing_id' => $listing->id,
                    'seller_id' => $listing->seller_id,
                    'bid_amount' => $listing->minimum_bid,
                ]);
            }

            // 4. If category_id == 1 ➤ insert Motorcycle
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

                DB::commit();

                return response()->json([
                    'message' => 'Motorcycle added successfully',
                    'data' => $motorcycle,
                ], 201);
            }

            // 5. If category_id == 2 ➤ insert SparePart
            elseif ($listing->category_id == 2) {
                $sparePart = SparePart::create([
                    'listing_id' => $listing->id,
                    'brand_id' => $request->brand_id,
                    'model_id' => $request->model_id,
                    'year_id' => $request->year_id,
                    'condition' => $request->condition, // 'new' or 'used'
                ]);

                DB::commit();

                return response()->json([
                    'message' => 'Spare part added successfully',
                    'data' => $sparePart,
                ], 201);
            }

            // 6. If category_id == 3 ➤ insert LicensePlate
            elseif ($listing->category_id == 3) {
                $licensePlate = LicensePlate::create([
                    'listing_id' => $listing->id,
                    'characters' => $request->characters,
                    'country_id' => $request->country_id,
                    'type_id' => $request->type_id,
                    'color_id' => $request->color_id,
                    'digits_count' => $request->digits_count,
                ]);

                DB::commit();

                return response()->json([
                    'message' => 'License plate added successfully',
                    'data' => $licensePlate,
                ], 201);
            }

            // 7. Invalid category
            else {
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
}
