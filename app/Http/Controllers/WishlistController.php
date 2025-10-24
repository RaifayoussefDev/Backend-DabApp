<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Wishlist",
 *     description="Operations related to wishlists"
 * )
 */
class WishlistController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/wishlists",
     *     tags={"Wishlist"},
     *     summary="Get user's wishlist (auth required)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by category (1=Motorcycle, 2=Spare Part, 3=License Plate)",
     *         required=false,
     *         @OA\Schema(type="integer", enum={1, 2, 3}, example=1)
     *     ),
     *     @OA\Response(response=200, description="Wishlist retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $perPage = $request->get('per_page', 10);
            $perPage = min($perPage, 50); // Limit to 50 items per page

            // Get category filter
            $categoryFilter = $request->get('category');

            $wishlists = Wishlist::with([
                'listing' => function ($query) {
                    $query->where('status', 'published')->with([
                        'images' => function ($q) {
                            $q->select('listing_id', 'image_url')->limit(1);
                        },
                        'category:id,name',
                        'country:id,name',
                        'city:id,name',
                        'country.currencyExchangeRate:id,country_id,currency_symbol',
                    ]);
                }
            ])
                ->where('user_id', $userId)
                ->whereHas('listing', function ($query) use ($categoryFilter) {
                    $query->where('status', 'published');

                    // Apply category filter if provided
                    if ($categoryFilter && in_array($categoryFilter, [1, 2, 3])) {
                        $query->where('category_id', $categoryFilter);
                    }
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Get the collection of wishlisted items
            $wishlistCollection = $wishlists->getCollection();

            // Separate listings by category for conditional eager loading
            $listingIds = $wishlistCollection->pluck('listing_id')->filter();

            // Load category-specific relations
            $motorcycleListingIds = Listing::whereIn('id', $listingIds)->where('category_id', 1)->pluck('id');
            $sparePartListingIds = Listing::whereIn('id', $listingIds)->where('category_id', 2)->pluck('id');
            $licensePlateListingIds = Listing::whereIn('id', $listingIds)->where('category_id', 3)->pluck('id');

            // Load motorcycle data
            if ($motorcycleListingIds->isNotEmpty()) {
                $wishlistCollection->each(function ($wishlist) use ($motorcycleListingIds) {
                    if ($wishlist->listing && $motorcycleListingIds->contains($wishlist->listing->id)) {
                        $wishlist->listing->load([
                            'motorcycle' => function ($query) {
                                $query->select('id', 'listing_id', 'brand_id', 'model_id', 'year_id', 'type_id', 'engine', 'mileage', 'body_condition', 'modified', 'insurance', 'general_condition', 'vehicle_care', 'transmission')
                                    ->with([
                                        'brand:id,name',
                                        'model:id,name',
                                        'year:id,year',
                                        'type:id,name'
                                    ]);
                            }
                        ]);
                    }
                });
            }

            // Load spare part data
            if ($sparePartListingIds->isNotEmpty()) {
                $wishlistCollection->each(function ($wishlist) use ($sparePartListingIds) {
                    if ($wishlist->listing && $sparePartListingIds->contains($wishlist->listing->id)) {
                        $wishlist->listing->load([
                            'sparePart' => function ($query) {
                                $query->with([
                                    'bikePartBrand:id,name',
                                    'bikePartCategory:id,name',
                                    'motorcycleAssociations.brand:id,name',
                                    'motorcycleAssociations.model:id,name',
                                    'motorcycleAssociations.year:id,year'
                                ]);
                            }
                        ]);
                    }
                });
            }

            // Load license plate data
            if ($licensePlateListingIds->isNotEmpty()) {
                $wishlistCollection->each(function ($wishlist) use ($licensePlateListingIds) {
                    if ($wishlist->listing && $licensePlateListingIds->contains($wishlist->listing->id)) {
                        $wishlist->listing->load([
                            'licensePlate.format',
                            'licensePlate.city',
                            'licensePlate.country',
                            'licensePlate.fieldValues.formatField'
                        ]);
                    }
                });
            }

            // Get current bids for auctions
            $currentBids = DB::table('auction_histories')
                ->whereIn('listing_id', $listingIds)
                ->select('listing_id', DB::raw('MAX(bid_amount) as current_bid'))
                ->groupBy('listing_id')
                ->pluck('current_bid', 'listing_id');

            // Format the listings
            $formattedWishlists = $wishlistCollection->map(function ($wishlist) use ($currentBids) {
                $listing = $wishlist->listing;

                if (!$listing) {
                    return null;
                }

                // Determine the price to display
                $displayPrice = $listing->price;
                $isAuction = false;
                $currentBid = $currentBids[$listing->id] ?? null;

                if (!$displayPrice && $listing->auction_enabled) {
                    $displayPrice = $currentBid ?: $listing->minimum_bid;
                    $isAuction = true;
                }

                // Get currency symbol
                $currencySymbol = $listing->country?->currencyExchangeRate?->currency_symbol ?? 'MAD';

                // Price to show
                $priceToShow = $listing->price ?? $listing->minimum_bid;

                $baseData = [
                    'wishlist_id' => $wishlist->id,
                    'added_at' => $wishlist->created_at,
                    'listing' => [
                        'id' => $listing->id,
                        'title' => $listing->title,
                        'description' => $listing->description,
                        'price' => $priceToShow,
                        'category_id' => $listing->category_id,
                        'auction_enabled' => $listing->auction_enabled,
                        'minimum_bid' => $listing->minimum_bid,
                        'allow_submission' => $listing->allow_submission,
                        'listing_type_id' => $listing->listing_type_id,
                        'contacting_channel' => $listing->contacting_channel,
                        'seller_type' => $listing->seller_type,
                        'status' => $listing->status,
                        'created_at' => $listing->created_at->format('Y-m-d H:i:s'),
                        'city' => $listing->city?->name,
                        'country' => $listing->country?->name,
                        'images' => $listing->images->pluck('image_url'),
                        'wishlist' => true, // Always true since this is the wishlist endpoint

                        // New columns
                        'display_price' => $displayPrice,
                        'is_auction' => $isAuction,
                        'current_bid' => $currentBid,
                        'currency' => $currencySymbol,
                    ]
                ];

                // Add category-specific data
                if ($listing->category_id == 1 && $listing->motorcycle) {
                    // Motorcycle data
                    $baseData['listing']['motorcycle'] = [
                        'brand' => $listing->motorcycle->brand?->name ?? null,
                        'model' => $listing->motorcycle->model?->name ?? null,
                        'year' => $listing->motorcycle->year?->year ?? null,
                        'type' => $listing->motorcycle->type?->name ?? null,
                        'engine' => $listing->motorcycle->engine,
                        'mileage' => $listing->motorcycle->mileage,
                        'body_condition' => $listing->motorcycle->body_condition,
                        'modified' => $listing->motorcycle->modified,
                        'insurance' => $listing->motorcycle->insurance,
                        'general_condition' => $listing->motorcycle->general_condition,
                        'vehicle_care' => $listing->motorcycle->vehicle_care,
                        'transmission' => $listing->motorcycle->transmission,
                    ];
                } elseif ($listing->category_id == 2 && $listing->sparePart) {
                    // Spare part data
                    $baseData['listing']['spare_part'] = [
                        'condition' => $listing->sparePart->condition,
                        'brand' => $listing->sparePart->bikePartBrand?->name ?? null,
                        'category' => $listing->sparePart->bikePartCategory?->name ?? null,
                        'compatible_motorcycles' => $listing->sparePart->motorcycleAssociations->map(function ($association) {
                            return [
                                'brand' => $association->brand?->name ?? null,
                                'model' => $association->model?->name ?? null,
                                'year' => $association->year?->year ?? null,
                            ];
                        })->toArray(),
                    ];
                } elseif ($listing->category_id == 3 && $listing->licensePlate) {
                    // License plate data
                    $licensePlate = $listing->licensePlate;

                    $baseData['listing']['license_plate'] = [
                        'plate_format' => [
                            'id' => $licensePlate->format?->id ?? null,
                            'name' => $licensePlate->format?->name ?? null,
                            'pattern' => $licensePlate->format?->pattern ?? null,
                            'country' => $licensePlate->format?->country ?? null,
                        ],
                        'city' => $licensePlate->city?->name ?? null,
                        'country' => $licensePlate->country?->name ?? null,
                        'country_id' => $licensePlate->country_id,
                        'fields' => $licensePlate->fieldValues->map(function ($fieldValue) {
                            return [
                                'field_id' => $fieldValue->formatField?->id ?? null,
                                'field_name' => $fieldValue->formatField?->field_name ?? null,
                                'field_position' => $fieldValue->formatField?->position ?? null,
                                'field_type' => $fieldValue->formatField?->field_type ?? null,
                                'field_label' => $fieldValue->formatField?->field_label ?? null,
                                'is_required' => $fieldValue->formatField?->is_required ?? null,
                                'max_length' => $fieldValue->formatField?->max_length ?? null,
                                'validation_pattern' => $fieldValue->formatField?->validation_pattern ?? null,
                                'value' => $fieldValue->field_value,
                            ];
                        })->toArray(),
                    ];
                }

                return $baseData;
            })->filter()->values(); // Remove null entries and reset keys

            return response()->json([
                'message' => 'Wishlist retrieved successfully',
                'data' => $formattedWishlists,
                'pagination' => [
                    'current_page' => $wishlists->currentPage(),
                    'last_page' => $wishlists->lastPage(),
                    'per_page' => $wishlists->perPage(),
                    'total' => $wishlists->total(),
                    'from' => $wishlists->firstItem(),
                    'to' => $wishlists->lastItem()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve wishlist',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/wishlists",
     *     tags={"Wishlist"},
     *     summary="Add listing to wishlist (auth required)",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"listing_id"},
     *             @OA\Property(property="listing_id", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Added to wishlist"),
     *     @OA\Response(response=409, description="Already in wishlist"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'listing_id' => 'required|exists:listings,id',
        ]);

        $user = Auth::user(); // Récupère l'utilisateur connecté via token

        // Vérifie s'il existe déjà un wishlist pour ce user + listing
        $exists = Wishlist::where('user_id', $user->id)
            ->where('listing_id', $request->listing_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already in wishlist'], 409);
        }

        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'listing_id' => $request->listing_id
        ]);

        return response()->json($wishlist, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/wishlists/{id}",
     *     tags={"Wishlist"},
     *     summary="Get a wishlist by ID",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Wishlist details"),
     *     @OA\Response(response=404, description="Wishlist not found")
     * )
     */
    public function show($id)
    {
        $wishlist = Wishlist::with(['user', 'listing'])->find($id);
        if (!$wishlist) return response()->json(['message' => 'Not found'], 404);
        return $wishlist;
    }

    /**
     * @OA\Put(
     *     path="/api/wishlists/{id}",
     *     tags={"Wishlist"},
     *     summary="Update a wishlist",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="listing_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Wishlist updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $wishlist = Wishlist::findOrFail($id);
        $wishlist->update($request->only('user_id', 'listing_id'));
        return $wishlist;
    }

    /**
     * @OA\Delete(
     *     path="/api/wishlists/{listing_id}",
     *     tags={"Wishlist"},
     *     summary="Remove listing from wishlist (auth required)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="listing_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Removed from wishlist"),
     *     @OA\Response(response=404, description="Wishlist item not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */

    public function destroy($listing_id)
    {
        $user = Auth::user();

        $wishlist = Wishlist::where('user_id', $user->id)
            ->where('listing_id', $listing_id)
            ->first();

        if (!$wishlist) {
            return response()->json(['message' => 'Wishlist item not found'], 404);
        }

        $wishlist->delete();

        return response()->json(['message' => 'Removed from wishlist'], 200);
    }
}
