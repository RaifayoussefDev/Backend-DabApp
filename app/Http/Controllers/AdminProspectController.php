<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Listing;
use App\Models\ListingImage; // ✅ Added
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Traits\CategoryDataTrait; // ✅ Added
use Illuminate\Support\Facades\Validator;

class AdminProspectController extends Controller
{
    use CategoryDataTrait;

    /**
     * @OA\Get(
     *     path="/api/admin/prospects",
     *     summary="List all prospects",
     *     tags={"Admin - Prospects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of prospects",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="first_name", type="string"),
     *                     @OA\Property(property="last_name", type="string"),
     *                     @OA\Property(property="email", type="string"),
     *                     @OA\Property(property="phone", type="string"),
     *                     @OA\Property(property="listings_count", type="integer")
     *                 )
     *             ),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);

        $prospects = User::where('first_name', 'Prospect')
            ->withCount('listings')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($prospects);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/prospects",
     *     summary="Create a new prospect",
     *     description="Creates a prospect user. Optionally creates a listing if listing details are provided.",
     *     tags={"Admin - Prospects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="phone", type="string", example="+212600000000"),
     *             @OA\Property(property="email", type="string", example="prospect@example.com"),
     *             @OA\Property(property="create_listing", type="boolean", example=true),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Prospect Bike"),
     *             @OA\Property(property="price", type="number", example=10000),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="city_id", type="integer", example=1),
     *             @OA\Property(property="brand_id", type="integer", example=7),
     *             @OA\Property(property="model_id", type="integer", example=5712),
     *             @OA\Property(property="year_id", type="integer", example=10671),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Prospect created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Prospect created successfully"),
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="listing", type="object")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'email' => 'nullable|email|unique:users,email',
            'create_listing' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Check if user exists (by phone) or create new
            $user = User::where('phone', $request->phone)->first();
            
            if (!$user) {
                $password = Str::random(10);
                $user = User::create([
                    'first_name' => 'Prospect',
                    'last_name' => substr($request->phone, -4),
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'password' => Hash::make($password),
                    'role_id' => 2,
                    'verified' => true,
                    'is_active' => true,
                ]);
            }

            $listing = null;

            // Optional Listing Creation
            if ($request->boolean('create_listing')) {
                 // Basic Listing Validation if creating listing
                 $request->validate([
                    'category_id' => 'required|integer',
                    'country_id' => 'required|integer',
                    'city_id' => 'required|integer',
                    'title' => 'required|string',
                 ]);

                $listing = Listing::create([
                    'seller_id' => $user->id,
                    'seller_type' => 'owner',
                    'status' => 'published',
                    'category_id' => $request->integer('category_id'),
                    'title' => $request->input('title'),
                    'description' => $request->input('description'),
                    'price' => $request->input('price'),
                    'country_id' => $request->integer('country_id'),
                    'city_id' => $request->integer('city_id'),
                    'contacting_channel' => 'phone',
                    'allow_submission' => false,
                    'auction_enabled' => false,
                ]);

                if ($request->has('images')) {
                    foreach ($request->input('images') as $imageUrl) {
                        $listing->images()->create(['image_url' => $imageUrl]);
                    }
                }

                $this->handleCategorySpecificData($listing, $request);
            }

            DB::commit();

            return response()->json([
                'message' => 'Prospect created successfully',
                'user' => $user,
                'listing' => $listing
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create prospect', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/prospects/{id}",
     *     summary="Get prospect details",
     *     tags={"Admin - Prospects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Prospect details",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function show($id)
    {
        $user = User::withCount('listings')->findOrFail($id);
        // Ensure strictly managing "Prospects" if desired, or all users? 
        // User requested "prospect", but usually admin can manage any user. 
        // We will return the user regardless but context is prospect.
        return response()->json($user);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/prospects/{id}",
     *     summary="Update prospect details",
     *     tags={"Admin - Prospects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="first_name", type="string"),
     *             @OA\Property(property="last_name", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Prospect updated",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $user->update($request->only(['phone', 'email', 'first_name', 'last_name', 'address', 'city_id']));

        return response()->json([
            'message' => 'Prospect updated successfully',
            'user' => $user
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/prospects/{id}",
     *     summary="Delete prospect",
     *     description="Deletes the prospect and their listings",
     *     tags={"Admin - Prospects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Prospect deleted")
     * )
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        // Logic to delete listings? User model handles soft deletes usually.
        // For forced cleanup:
        $user->listings()->delete(); // Soft delete listings
        $user->delete(); // Soft delete user

        return response()->json(['message' => 'Prospect deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/prospects/{id}/listings",
     *     summary="Get prospect listings",
     *     tags={"Admin - Prospects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="List of listings",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     )
     * )
     */
    public function listings($id)
    {
        $user = User::findOrFail($id);
        
        $listings = $user->listings()
            ->with([
                'images',
                'city',
                'country',
                'country.currencyExchangeRate',
                'seller',
                'motorcycle.brand',
                'motorcycle.model',
                'motorcycle.year',
                'sparePart.bikePartBrand',
                'sparePart.bikePartCategory',
                'sparePart.motorcycleAssociations.brand',
                'sparePart.motorcycleAssociations.model',
                'sparePart.motorcycleAssociations.year',
                'licensePlate.format',
                'licensePlate.city',
                'licensePlate.fieldValues.formatField'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedListings = $listings->map(function ($listing) {
            return $this->formatListing($listing);
        });

        return response()->json($formattedListings);
    }

    /**
     * Format listing data to match ListingController@getById
     */
    private function formatListing($listing)
    {
        // Déterminer le prix à afficher
        $displayPrice = $listing->price;
        $isAuction = false;
        $currentBid = null;

        if ($listing->auction_enabled) {
            $currentBid = $listing->auctionHistories()->max('bid_amount');
            if (!$displayPrice) {
                $displayPrice = $currentBid ?: $listing->minimum_bid;
                $isAuction = true;
            }
        }

        // Récupérer le symbole de devise
        $currencySymbol = $listing->country?->currencyExchangeRate?->currency_symbol ?? 'MAD';

        $data = [
            'id' => $listing->id,
            'title' => $listing->title,
            'description' => $listing->description,
            'created_at' => $listing->created_at->format('Y-m-d H:i:s'),
            'city' => $listing->city?->name,
            'country' => $listing->country?->name,
            'images' => $listing->images->pluck('image_url'),
            'wishlist' => false, // Admin view
            'is_seller' => false, // Admin view
            'category_id' => $listing->category_id,
            'allow_submission' => $listing->allow_submission,
            'auction_enabled' => $listing->auction_enabled,
            'minimum_bid' => $listing->minimum_bid,
            'listing_type_id' => $listing->listing_type_id,
            'contacting_channel' => $listing->contacting_channel,
            'seller_type' => $listing->seller_type,
            'status' => $listing->status,
            'currency' => $currencySymbol,
            'display_price' => $displayPrice,
            'is_auction' => $isAuction,
            'current_bid' => $currentBid,
        ];

        if (!$listing->allow_submission) {
            $data['price'] = $listing->price ?? $listing->minimum_bid;
        }

        // Seller Info
        $data['seller'] = [
            'id' => $listing->seller?->id,
            'name' => $listing->seller?->first_name . ' ' . $listing->seller?->last_name,
            'email' => $listing->seller?->email,
            'phone' => $listing->seller?->phone,
            'address' => $listing->seller?->address,
            'profile_image' => $listing->seller?->profile_image,
            'verified' => (bool) $listing->seller?->verified,
            'member_since' => $listing->seller?->created_at->format('Y-m-d H:i:s'),
        ];

        // Motorcycle category
        if ($listing->category_id == 1 && $listing->motorcycle) {
            $data['motorcycle'] = [
                'brand' => $listing->motorcycle->brand?->name,
                'model' => $listing->motorcycle->model?->name,
                'year' => $listing->motorcycle->year?->year,
                'engine' => $listing->motorcycle->engine,
                'mileage' => $listing->motorcycle->mileage,
                'body_condition' => $listing->motorcycle->body_condition,
                'general_condition' => $listing->motorcycle->general_condition,
                'vehicle_care' => $listing->motorcycle->vehicle_care,
                'vehicle_care_other' => $listing->motorcycle->vehicle_care_other,
                'modified' => (bool) $listing->motorcycle->modified,
                'insurance' => (bool) $listing->motorcycle->insurance,
                'transmission' => $listing->motorcycle->transmission,
            ];
        }

        // Spare Part category
        if ($listing->category_id == 2 && $listing->sparePart) {
            $data['spare_part'] = [
                'type' => $listing->sparePart->bikePartCategory?->name,
                'brand' => $listing->sparePart->bikePartBrand?->name,
                'other_brand' => $listing->sparePart->brand_other,
                'condition' => $listing->sparePart->condition,
                'compatible_motorcycles' => $listing->sparePart->motorcycleAssociations->map(function ($assoc) {
                    return [
                        'brand' => $assoc->brand?->name,
                        'model' => $assoc->model?->name,
                        'year' => $assoc->year?->year,
                    ];
                }),
            ];
        }

        // License Plate category
        if ($listing->category_id == 3 && $listing->licensePlate) {
             // Formater les valeurs des champs dynamiques
             $fieldValues = $listing->licensePlate->fieldValues->map(function ($val) {
                return [
                    'field' => $val->formatField?->name_en, // Ou name_ar selon la locale
                    'value' => $val->value,
                    'is_ar' => $val->formatField?->is_ar
                ];
            });

            $data['license_plate'] = [
                'format' => $listing->licensePlate->format?->name,
                'city' => $listing->licensePlate->city?->name,
                'fields' => $fieldValues,
            ];
        }

        return $data;
    }

    /**
     * @OA\Put(
     *     path="/api/admin/prospects/{id}/listings/{listing_id}",
     *     summary="Update prospect listing",
     *     tags={"Admin - Prospects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="listing_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="price", type="number")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Listing updated")
     * )
     */
    public function updateListing(Request $request, $id, $listingId)
    {
        $user = User::findOrFail($id);
        
        // Ensure listing belongs to this prospect
        $listing = Listing::where('id', $listingId)
            ->where('seller_id', $user->id)
            ->firstOrFail();

        $listing->update($request->except(['id', 'seller_id', 'created_at', 'images']));

        if ($request->has('images')) {
            $listing->images()->delete();
            foreach ($request->images as $imageUrl) {
                $listing->images()->create(['image_url' => $imageUrl]);
            }
        }

        // Handle Category Specific Data (using Trait)
        if (method_exists($this, 'handleCategorySpecificData')) {
            $this->handleCategorySpecificData($listing, $request);
        }

        return response()->json(['message' => 'Listing updated', 'listing' => $listing->fresh()]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/prospects/{id}/listings/{listing_id}",
     *     summary="Delete prospect listing",
     *     tags={"Admin - Prospects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="listing_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Listing deleted")
     * )
     */
    public function destroyListing($id, $listingId)
    {
        $user = User::findOrFail($id);
        
        $listing = Listing::where('id', $listingId)
            ->where('seller_id', $user->id)
            ->firstOrFail();

        $listing->delete();

        return response()->json(['message' => 'Listing deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/prospects/stats",
     *     summary="Get prospect statistics",
     *     tags={"Admin - Prospects"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Prospect stats data",
     *         @OA\JsonContent(
     *             @OA\Property(property="total_prospects", type="integer"),
     *             @OA\Property(property="total_prospect_listings", type="integer")
     *         )
     *     )
     * )
     */
    public function stats()
    {
        $prospectCount = User::where('first_name', 'Prospect')->count();

        $prospectListingsCount = Listing::whereHas('seller', function($query) {
             $query->where('first_name', 'Prospect');
        })->count();

        return response()->json([
            'total_prospects' => $prospectCount,
            'total_prospect_listings' => $prospectListingsCount
        ]);
    }
}
