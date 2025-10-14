<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Listing;
use App\Models\Motorcycle;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Filters",
 *     description="API endpoints for filtering listings"
 * )
 */
class FilterController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/filter/motorcycles",
     *     summary="Filter motorcycles",
     *     description="Filter motorcycles by category, price, brand, model, year, condition, mileage, seller type and location",
     *     operationId="filterMotorcycles",
     *     tags={"Filters"},
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Category ID filter (1=motorcycles, 2=cars, etc.)",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="country",
     *         in="query",
     *         description="Filter by country name (supports partial matching). If no results found, shows all countries.",
     *         required=false,
     *         @OA\Schema(type="string", example="Morocco")
     *     ),
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="Minimum price filter (includes fixed price and minimum bid for auctions)",
     *         required=false,
     *         @OA\Schema(type="number", format="float", minimum=0)
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="Maximum price filter (includes fixed price and minimum bid for auctions)",
     *         required=false,
     *         @OA\Schema(type="number", format="float", minimum=0)
     *     ),
     *     @OA\Parameter(
     *         name="brands[]",
     *         in="query",
     *         description="Array of brand IDs to filter",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer"))
     *     ),
     *     @OA\Parameter(
     *         name="models[]",
     *         in="query",
     *         description="Array of model IDs to filter",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer"))
     *     ),
     *     @OA\Parameter(
     *         name="types[]",
     *         in="query",
     *         description="Array of motorcycle type IDs to filter",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer"))
     *     ),
     *     @OA\Parameter(
     *         name="years[]",
     *         in="query",
     *         description="Array of year IDs to filter",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer"))
     *     ),
     *     @OA\Parameter(
     *         name="condition",
     *         in="query",
     *         description="Motorcycle condition",
     *         required=false,
     *         @OA\Schema(type="string", enum={"new", "used", "excellent", "good", "fair", "poor"})
     *     ),
     *     @OA\Parameter(
     *         name="min_mileage",
     *         in="query",
     *         description="Minimum mileage filter",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=0)
     *     ),
     *     @OA\Parameter(
     *         name="max_mileage",
     *         in="query",
     *         description="Maximum mileage filter",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=0)
     *     ),
     *     @OA\Parameter(
     *         name="seller_type",
     *         in="query",
     *         description="Type of seller",
     *         required=false,
     *         @OA\Schema(type="string", enum={"individual", "professional", "dealer"})
     *     ),
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         description="Country ID filter (alternative to country name)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="city_id",
     *         in="query",
     *         description="City ID filter",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", minimum=1),
     *         example=1
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page (default: 15, max: 100)",
     *         @OA\Schema(type="integer", minimum=1, maximum=100),
     *         example=15
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Showing listings for 'Morocco'."),
     *             @OA\Property(property="searched_country", type="string", example="Morocco", nullable=true),
     *             @OA\Property(property="showing_all_countries", type="boolean", example=false),
     *             @OA\Property(property="total_listings", type="integer", example=25),
     *             @OA\Property(property="current_page", type="integer", example=1, description="Only present when pagination is used"),
     *             @OA\Property(property="per_page", type="integer", example=15, description="Only present when pagination is used"),
     *             @OA\Property(property="last_page", type="integer", example=2, description="Only present when pagination is used"),
     *             @OA\Property(property="from", type="integer", example=1, description="Only present when pagination is used"),
     *             @OA\Property(property="to", type="integer", example=15, description="Only present when pagination is used"),
     *             @OA\Property(
     *                 property="motorcycles",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Honda CBR600RR"),
     *                     @OA\Property(property="description", type="string", example="Excellent condition motorcycle"),
     *                     @OA\Property(property="price", type="number", format="float", example=8500.00),
     *                     @OA\Property(property="is_auction", type="boolean", example=false),
     *                     @OA\Property(property="minimum_bid", type="number", format="float", example=5000.00, nullable=true),
     *                     @OA\Property(property="current_bid", type="number", format="float", example=6500.00, nullable=true),
     *                     @OA\Property(property="currency", type="string", example="MAD"),
     *                     @OA\Property(property="category", type="string", example="Motorcycles"),
     *                     @OA\Property(property="brand", type="string", example="Honda"),
     *                     @OA\Property(property="model", type="string", example="CBR600RR"),
     *                     @OA\Property(property="year", type="integer", example=2020),
     *                     @OA\Property(property="type", type="string", example="Sport"),
     *                     @OA\Property(property="listing_date", type="string", format="date-time", example="2024-01-15 10:30:00"),
     *                     @OA\Property(property="seller_type", type="string", example="individual"),
     *                     @OA\Property(
     *                         property="location",
     *                         type="object",
     *                         @OA\Property(property="country", type="string", example="Morocco"),
     *                         @OA\Property(property="city", type="string", example="Casablanca")
     *                     ),
     *                     @OA\Property(property="image", type="string", format="url", example="https://example.com/image.jpg", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function filterMotorcycles(Request $request)
    {
        $query = Listing::query();

        $countryName = $request->get('country');
        $showingAllCountries = false;
        $message = '';

        // Pagination parameters
        $page = $request->get('page');
        $perPage = $request->get('per_page', 15);
        $perPage = min($perPage, 100);
        $usePagination = !is_null($page);

        // Filtre de catégorie (par défaut = 1 pour motos)
        $categoryId = $request->input('category_id', 1);
        $query->where('category_id', $categoryId)->where('status', 'published');

        // Filtre de prix : prix fixe OU minimum_bid pour les enchères
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');

        if ($minPrice !== null || $maxPrice !== null) {
            $query->where(function ($q) use ($minPrice, $maxPrice) {
                $q->where(function ($subQ) use ($minPrice, $maxPrice) {
                    $subQ->whereNotNull('price');

                    if ($minPrice !== null && $maxPrice !== null) {
                        $subQ->whereBetween('price', [(float)$minPrice, (float)$maxPrice]);
                    } elseif ($minPrice !== null) {
                        $subQ->where('price', '>=', (float)$minPrice);
                    } elseif ($maxPrice !== null) {
                        $subQ->where('price', '<=', (float)$maxPrice);
                    }
                });

                $q->orWhere(function ($subQ) use ($minPrice, $maxPrice) {
                    $subQ->whereNull('price')
                        ->where('auction_enabled', true);

                    if ($minPrice !== null && $maxPrice !== null) {
                        $subQ->whereBetween('minimum_bid', [(float)$minPrice, (float)$maxPrice]);
                    } elseif ($minPrice !== null) {
                        $subQ->where('minimum_bid', '>=', (float)$minPrice);
                    } elseif ($maxPrice !== null) {
                        $subQ->where('minimum_bid', '<=', (float)$maxPrice);
                    }
                });
            });
        }

        // Filtrer sur la relation motorcycle
        $query->whereHas('motorcycle', function ($q) use ($request) {
            if ($request->has('brands')) {
                $brands = $request->input('brands');
                if (is_array($brands) && count($brands) > 0) {
                    $q->whereIn('brand_id', $brands);
                }
            }

            if ($request->has('models')) {
                $models = $request->input('models');
                if (is_array($models) && count($models) > 0) {
                    $q->whereIn('model_id', $models);
                }
            }

            if ($request->has('types')) {
                $types = $request->input('types');
                if (is_array($types) && count($types) > 0) {
                    $q->whereIn('type_id', $types);
                }
            }

            if ($request->has('years')) {
                $years = $request->input('years');
                if (is_array($years) && count($years) > 0) {
                    $q->whereIn('year_id', $years);
                }
            }

            if ($request->has('condition')) {
                $condition = $request->input('condition');
                $q->where('general_condition', $condition);
            }

            $minMileage = $request->input('min_mileage');
            $maxMileage = $request->input('max_mileage');

            if ($minMileage !== null && $maxMileage !== null) {
                $q->whereBetween('mileage', [(int)$minMileage, (int)$maxMileage]);
            } elseif ($minMileage !== null) {
                $q->where('mileage', '>=', (int)$minMileage);
            } elseif ($maxMileage !== null) {
                $q->where('mileage', '<=', (int)$maxMileage);
            }
        });

        // Filtres sur la table listings
        if ($request->has('seller_type')) {
            $query->where('seller_type', $request->input('seller_type'));
        }

        if ($request->has('country_id')) {
            $query->where('country_id', $request->input('country_id'));
        }

        if ($request->has('city_id')) {
            $query->where('city_id', $request->input('city_id'));
        }

        // Filtre par nom de pays
        if ($countryName) {
            $countryFilteredQuery = clone $query;
            $countryFilteredQuery->whereHas('country', function ($q) use ($countryName) {
                $q->where('name', 'LIKE', '%' . $countryName . '%');
            });

            $countCount = $countryFilteredQuery->count();

            if ($countCount === 0) {
                $showingAllCountries = true;
                $message = "No listings found for '{$countryName}'. Showing all countries instead.";
            } else {
                $query = $countryFilteredQuery;
                $message = "Showing listings for '{$countryName}'.";
            }
        }

        // Apply pagination if requested
        if ($usePagination) {
            $motorcycles = $query->select([
                'id',
                'title',
                'description',
                'price',
                'auction_enabled',
                'minimum_bid',
                'created_at',
                'seller_type',
                'country_id',
                'city_id',
                'category_id',
                DB::raw('(SELECT MAX(bid_amount) FROM auction_histories WHERE auction_histories.listing_id = listings.id) as current_bid')
            ])->paginate($perPage, ['*'], 'page', $page);
        } else {
            $motorcycles = $query->select([
                'id',
                'title',
                'description',
                'price',
                'auction_enabled',
                'minimum_bid',
                'created_at',
                'seller_type',
                'country_id',
                'city_id',
                'category_id',
                DB::raw('(SELECT MAX(bid_amount) FROM auction_histories WHERE auction_histories.listing_id = listings.id) as current_bid')
            ])->get();
        }

        // Get the collection of items
        $motorcyclesCollection = $usePagination ? $motorcycles->getCollection() : $motorcycles;

        // Load relations
        $motorcyclesCollection->load([
            'images' => function ($query) {
                $query->select('listing_id', 'image_url')->limit(1);
            },
            'motorcycle' => function ($query) {
                $query->select('id', 'listing_id', 'brand_id', 'model_id', 'year_id', 'type_id')
                    ->with([
                        'brand:id,name',
                        'model:id,name',
                        'year:id,year',
                        'type:id,name'
                    ]);
            },
            'country:id,name',
            'city:id,name',
            'category:id,name',
            'country.currencyExchangeRate:id,country_id,currency_symbol'
        ]);

        // Format results
        $formattedMotorcycles = $motorcyclesCollection->map(function ($listing) {
            $displayPrice = $listing->price;
            $isAuction = false;

            if (!$displayPrice && $listing->auction_enabled) {
                $displayPrice = $listing->current_bid ?: $listing->minimum_bid;
                $isAuction = true;
            }

            $currencySymbol = $listing->country?->currencyExchangeRate?->currency_symbol ?? 'MAD';

            return [
                'id' => $listing->id,
                'title' => $listing->title,
                'description' => $listing->description,
                'price' => $displayPrice,
                'is_auction' => $isAuction,
                'minimum_bid' => $listing->minimum_bid,
                'current_bid' => $listing->current_bid,
                'currency' => $currencySymbol,
                'category' => $listing->category?->name ?? null,
                'brand' => $listing->motorcycle?->brand?->name ?? null,
                'model' => $listing->motorcycle?->model?->name ?? null,
                'year' => $listing->motorcycle?->year?->year ?? null,
                'type' => $listing->motorcycle?->type?->name ?? null,
                'listing_date' => $listing->created_at?->format('Y-m-d H:i:s') ?? null,
                'image' => $listing->images->first()?->image_url ?? null,
                'seller_type' => $listing->seller_type,
                'location' => [
                    'country' => $listing->country?->name ?? null,
                    'city' => $listing->city?->name ?? null,
                ]
            ];
        });

        // Build response
        $response = [
            'message' => $message ?: 'Showing all listings.',
            'searched_country' => $countryName,
            'showing_all_countries' => $showingAllCountries,
            'total_listings' => $usePagination ? $motorcycles->total() : $formattedMotorcycles->count(),
        ];

        // Add pagination metadata only if pagination is used
        if ($usePagination) {
            $response['current_page'] = $motorcycles->currentPage();
            $response['per_page'] = $motorcycles->perPage();
            $response['last_page'] = $motorcycles->lastPage();
            $response['from'] = $motorcycles->firstItem();
            $response['to'] = $motorcycles->lastItem();
        }

        // Add motorcycles array
        $response['motorcycles'] = $formattedMotorcycles;

        return response()->json($response);
    }

    /**
     * @OA\Get(
     *     path="/api/filter/spare-parts",
     *     summary="Filter spare parts",
     *     description="Filter spare parts by price, brand, category, condition, location and country name",
     *     operationId="filterSpareParts",
     *     tags={"Filters"},
     *     @OA\Parameter(
     *         name="country",
     *         in="query",
     *         description="Filter by country name (supports partial matching). If no results found, shows all countries.",
     *         required=false,
     *         @OA\Schema(type="string", example="Morocco")
     *     ),
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="Minimum price filter (includes fixed price and minimum bid for auctions)",
     *         required=false,
     *         @OA\Schema(type="number", format="float", minimum=0)
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="Maximum price filter (includes fixed price and minimum bid for auctions)",
     *         required=false,
     *         @OA\Schema(type="number", format="float", minimum=0)
     *     ),
     *     @OA\Parameter(
     *         name="bike_part_brands[]",
     *         in="query",
     *         description="Array of bike part brand IDs to filter",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer"))
     *     ),
     *     @OA\Parameter(
     *         name="bike_part_categories[]",
     *         in="query",
     *         description="Array of bike part category IDs to filter",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer"))
     *     ),
     *     @OA\Parameter(
     *         name="condition",
     *         in="query",
     *         description="Spare part condition",
     *         required=false,
     *         @OA\Schema(type="string", enum={"new", "used", "excellent", "good", "fair", "poor"})
     *     ),
     *     @OA\Parameter(
     *         name="seller_type",
     *         in="query",
     *         description="Type of seller",
     *         required=false,
     *         @OA\Schema(type="string", enum={"individual", "professional", "dealer"})
     *     ),
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         description="Country ID filter (alternative to country name)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="city_id",
     *         in="query",
     *         description="City ID filter",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", minimum=1),
     *         example=1
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page (default: 15, max: 100)",
     *         @OA\Schema(type="integer", minimum=1, maximum=100),
     *         example=15
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Showing listings for 'Morocco'."),
     *             @OA\Property(property="searched_country", type="string", example="Morocco", nullable=true),
     *             @OA\Property(property="showing_all_countries", type="boolean", example=false),
     *             @OA\Property(property="total_listings", type="integer", example=15),
     *             @OA\Property(property="current_page", type="integer", example=1, description="Only present when pagination is used"),
     *             @OA\Property(property="per_page", type="integer", example=15, description="Only present when pagination is used"),
     *             @OA\Property(property="last_page", type="integer", example=2, description="Only present when pagination is used"),
     *             @OA\Property(property="from", type="integer", example=1, description="Only present when pagination is used"),
     *             @OA\Property(property="to", type="integer", example=15, description="Only present when pagination is used"),
     *             @OA\Property(
     *                 property="spare_parts",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Brake Pads Set"),
     *                     @OA\Property(property="description", type="string", example="High quality brake pads"),
     *                     @OA\Property(property="price", type="number", format="float", example=45.99),
     *                     @OA\Property(property="is_auction", type="boolean", example=false),
     *                     @OA\Property(property="minimum_bid", type="number", format="float", example=30.00, nullable=true),
     *                     @OA\Property(property="current_bid", type="number", format="float", example=35.00, nullable=true),
     *                     @OA\Property(property="currency", type="string", example="MAD"),
     *                     @OA\Property(property="brand", type="string", example="Brembo"),
     *                     @OA\Property(property="category", type="string", example="Brakes"),
     *                     @OA\Property(property="condition", type="string", example="new"),
     *                     @OA\Property(property="listing_date", type="string", format="date-time", example="2024-01-15 10:30:00"),
     *                     @OA\Property(property="seller_type", type="string", example="professional"),
     *                     @OA\Property(
     *                         property="location",
     *                         type="object",
     *                         @OA\Property(property="country", type="string", example="Morocco"),
     *                         @OA\Property(property="city", type="string", example="Casablanca")
     *                     ),
     *                     @OA\Property(property="image", type="string", format="url", example="https://example.com/image.jpg", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function filterSpareParts(Request $request)
    {
        $query = Listing::query();

        $countryName = $request->get('country');
        $showingAllCountries = false;
        $message = '';

        // Pagination parameters
        $page = $request->get('page');
        $perPage = $request->get('per_page', 15);
        $perPage = min($perPage, 100);
        $usePagination = !is_null($page);

        $query->where('category_id', 2)->where('status', 'published');

        // Filtre de prix : prix fixe OU minimum_bid pour les enchères
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');

        if ($minPrice !== null || $maxPrice !== null) {
            $query->where(function ($q) use ($minPrice, $maxPrice) {
                $q->where(function ($subQ) use ($minPrice, $maxPrice) {
                    $subQ->whereNotNull('price');

                    if ($minPrice !== null && $maxPrice !== null) {
                        $subQ->whereBetween('price', [(float)$minPrice, (float)$maxPrice]);
                    } elseif ($minPrice !== null) {
                        $subQ->where('price', '>=', (float)$minPrice);
                    } elseif ($maxPrice !== null) {
                        $subQ->where('price', '<=', (float)$maxPrice);
                    }
                });

                $q->orWhere(function ($subQ) use ($minPrice, $maxPrice) {
                    $subQ->whereNull('price')
                        ->where('auction_enabled', true);

                    if ($minPrice !== null && $maxPrice !== null) {
                        $subQ->whereBetween('minimum_bid', [(float)$minPrice, (float)$maxPrice]);
                    } elseif ($minPrice !== null) {
                        $subQ->where('minimum_bid', '>=', (float)$minPrice);
                    } elseif ($maxPrice !== null) {
                        $subQ->where('minimum_bid', '<=', (float)$maxPrice);
                    }
                });
            });
        }

        if ($request->filled('bike_part_brands')) {
            $query->whereHas('sparePart', function ($q) use ($request) {
                $q->whereIn('bike_part_brand_id', $request->bike_part_brands);
            });
        }

        if ($request->filled('bike_part_categories')) {
            $query->whereHas('sparePart', function ($q) use ($request) {
                $q->whereIn('bike_part_category_id', $request->bike_part_categories);
            });
        }

        if ($request->filled('condition')) {
            $query->whereHas('sparePart', function ($q) use ($request) {
                $q->where('condition', $request->condition);
            });
        }

        if ($request->filled('seller_type')) {
            $query->where('seller_type', $request->seller_type);
        }

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        // Filtre par nom de pays
        if ($countryName) {
            $countryFilteredQuery = clone $query;
            $countryFilteredQuery->whereHas('country', function ($q) use ($countryName) {
                $q->where('name', 'LIKE', '%' . $countryName . '%');
            });

            $countCount = $countryFilteredQuery->count();

            if ($countCount === 0) {
                $showingAllCountries = true;
                $message = "No listings found for '{$countryName}'. Showing all countries instead.";
            } else {
                $query = $countryFilteredQuery;
                $message = "Showing listings for '{$countryName}'.";
            }
        }

        // Apply pagination if requested
        if ($usePagination) {
            $spareParts = $query->select([
                'id',
                'title',
                'description',
                'price',
                'auction_enabled',
                'minimum_bid',
                'created_at',
                'seller_type',
                'country_id',
                'city_id',
                'category_id',
                DB::raw('(SELECT MAX(bid_amount) FROM auction_histories WHERE auction_histories.listing_id = listings.id) as current_bid')
            ])->paginate($perPage, ['*'], 'page', $page);
        } else {
            $spareParts = $query->select([
                'id',
                'title',
                'description',
                'price',
                'auction_enabled',
                'minimum_bid',
                'created_at',
                'seller_type',
                'country_id',
                'city_id',
                'category_id',
                DB::raw('(SELECT MAX(bid_amount) FROM auction_histories WHERE auction_histories.listing_id = listings.id) as current_bid')
            ])->get();
        }

        // Get the collection of items
        $sparePartsCollection = $usePagination ? $spareParts->getCollection() : $spareParts;

        // Load relations
        $sparePartsCollection->load([
            'images' => function ($query) {
                $query->select('listing_id', 'image_url')->limit(1);
            },
            'sparePart' => function ($query) {
                $query->select('id', 'listing_id', 'bike_part_brand_id', 'bike_part_category_id', 'condition')
                    ->with([
                        'bikePartBrand:id,name',
                        'bikePartCategory:id,name'
                    ]);
            },
            'country:id,name',
            'city:id,name',
            'category:id,name',
            'country.currencyExchangeRate:id,country_id,currency_symbol'
        ]);

        // Format results
        $formattedSpareParts = $sparePartsCollection->map(function ($listing) {
            $displayPrice = $listing->price;
            $isAuction = false;

            if (!$displayPrice && $listing->auction_enabled) {
                $displayPrice = $listing->current_bid ?: $listing->minimum_bid;
                $isAuction = true;
            }

            $currencySymbol = $listing->country?->currencyExchangeRate?->currency_symbol ?? 'MAD';

            return [
                'id' => $listing->id,
                'title' => $listing->title,
                'description' => $listing->description,
                'price' => $displayPrice,
                'is_auction' => $isAuction,
                'minimum_bid' => $listing->minimum_bid,
                'current_bid' => $listing->current_bid,
                'currency' => $currencySymbol,
                'category' => $listing->category?->name ?? null,
                'brand' => $listing->sparePart?->bikePartBrand?->name ?? null,
                'part_category' => $listing->sparePart?->bikePartCategory?->name ?? null,
                'condition' => $listing->sparePart?->condition ?? null,
                'listing_date' => $listing->created_at?->format('Y-m-d H:i:s') ?? null,
                'image' => $listing->images->first()?->image_url ?? null,
                'seller_type' => $listing->seller_type,
                'location' => [
                    'country' => $listing->country?->name ?? null,
                    'city' => $listing->city?->name ?? null,
                ]
            ];
        });

        // Build response
        $response = [
            'message' => $message ?: 'Showing all listings.',
            'searched_country' => $countryName,
            'showing_all_countries' => $showingAllCountries,
            'total_listings' => $usePagination ? $spareParts->total() : $formattedSpareParts->count(),
        ];

        // Add pagination metadata only if pagination is used
        if ($usePagination) {
            $response['current_page'] = $spareParts->currentPage();
            $response['per_page'] = $spareParts->perPage();
            $response['last_page'] = $spareParts->lastPage();
            $response['from'] = $spareParts->firstItem();
            $response['to'] = $spareParts->lastItem();
        }

        // Add spare_parts array
        $response['spare_parts'] = $formattedSpareParts;

        return response()->json($response);
    }

    /**
     * @OA\Get(
     *     path="/api/filter/license-plates",
     *     summary="Filter license plates",
     *     description="Filter license plates by price, country, city, format, plate fields and country name",
     *     operationId="filterLicensePlates",
     *     tags={"Filters"},
     *     @OA\Parameter(
     *         name="country",
     *         in="query",
     *         description="Filter by country name (supports partial matching). If no results found, shows all countries.",
     *         required=false,
     *         @OA\Schema(type="string", example="Morocco")
     *     ),
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="Minimum price filter (includes fixed price and minimum bid for auctions)",
     *         required=false,
     *         @OA\Schema(type="number", format="float", minimum=0)
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="Maximum price filter (includes fixed price and minimum bid for auctions)",
     *         required=false,
     *         @OA\Schema(type="number", format="float", minimum=0)
     *     ),
     *     @OA\Parameter(
     *         name="seller_type",
     *         in="query",
     *         description="Type of seller",
     *         required=false,
     *         @OA\Schema(type="string", enum={"individual", "professional", "dealer"})
     *     ),
     *     @OA\Parameter(
     *         name="listing_country_id",
     *         in="query",
     *         description="Filter by listing country ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="listing_city_id",
     *         in="query",
     *         description="Filter by listing city ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="plate_country_id",
     *         in="query",
     *         description="Filter by plate country ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="plate_city_id",
     *         in="query",
     *         description="Filter by plate city ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="plate_format_id",
     *         in="query",
     *         description="Filter by license plate format ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="plate_search",
     *         in="query",
     *         description="Search value within license plate fields",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", minimum=1),
     *         example=1
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page (default: 15, max: 100)",
     *         @OA\Schema(type="integer", minimum=1, maximum=100),
     *         example=15
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Showing listings for 'Morocco'."),
     *             @OA\Property(property="searched_country", type="string", example="Morocco", nullable=true),
     *             @OA\Property(property="showing_all_countries", type="boolean", example=false),
     *             @OA\Property(property="total_listings", type="integer", example=10),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="current_page", type="integer", example=1, description="Only present when pagination is used"),
     *             @OA\Property(property="per_page", type="integer", example=15, description="Only present when pagination is used"),
     *             @OA\Property(property="last_page", type="integer", example=2, description="Only present when pagination is used"),
     *             @OA\Property(property="from", type="integer", example=1, description="Only present when pagination is used"),
     *             @OA\Property(property="to", type="integer", example=15, description="Only present when pagination is used"),
     *             @OA\Property(
     *                 property="results",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Premium License Plate"),
     *                     @OA\Property(property="description", type="string", example="Rare plate number"),
     *                     @OA\Property(property="price", type="number", format="float", example=5000.00),
     *                     @OA\Property(property="is_auction", type="boolean", example=false),
     *                     @OA\Property(property="minimum_bid", type="number", format="float", example=3000.00, nullable=true),
     *                     @OA\Property(property="current_bid", type="number", format="float", example=3500.00, nullable=true),
     *                     @OA\Property(property="currency", type="string", example="MAD"),
     *                     @OA\Property(property="listing_date", type="string", format="date-time", example="2024-01-15 10:30:00"),
     *                     @OA\Property(property="seller_type", type="string", example="individual"),
     *                     @OA\Property(
     *                         property="seller_location",
     *                         type="object",
     *                         @OA\Property(property="country", type="string", example="Morocco"),
     *                         @OA\Property(property="city", type="string", example="Casablanca")
     *                     ),
     *                     @OA\Property(property="image", type="string", format="url", nullable=true),
     *                     @OA\Property(
     *                         property="license_plate",
     *                         type="object",
     *                         @OA\Property(property="format", type="string", example="Standard"),
     *                         @OA\Property(
     *                             property="plate_location",
     *                             type="object",
     *                             @OA\Property(property="country", type="string"),
     *                             @OA\Property(property="city", type="string")
     *                         ),
     *                         @OA\Property(property="fields", type="array", @OA\Items(type="object"))
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function filterLicensePlates(Request $request)
    {
        $query = Listing::query();

        $countryName = $request->get('country');
        $showingAllCountries = false;
        $message = '';

        // Pagination parameters
        $page = $request->get('page');
        $perPage = $request->get('per_page', 15);
        $perPage = min($perPage, 100);
        $usePagination = !is_null($page);

        $query->where('category_id', 3)->where('status', 'published');

        // Filtre de prix : prix fixe OU minimum_bid pour les enchères
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');

        if ($minPrice !== null || $maxPrice !== null) {
            $query->where(function ($q) use ($minPrice, $maxPrice) {
                $q->where(function ($subQ) use ($minPrice, $maxPrice) {
                    $subQ->whereNotNull('price');

                    if ($minPrice !== null && $maxPrice !== null) {
                        $subQ->whereBetween('price', [(float)$minPrice, (float)$maxPrice]);
                    } elseif ($minPrice !== null) {
                        $subQ->where('price', '>=', (float)$minPrice);
                    } elseif ($maxPrice !== null) {
                        $subQ->where('price', '<=', (float)$maxPrice);
                    }
                });

                $q->orWhere(function ($subQ) use ($minPrice, $maxPrice) {
                    $subQ->whereNull('price')
                        ->where('auction_enabled', true);

                    if ($minPrice !== null && $maxPrice !== null) {
                        $subQ->whereBetween('minimum_bid', [(float)$minPrice, (float)$maxPrice]);
                    } elseif ($minPrice !== null) {
                        $subQ->where('minimum_bid', '>=', (float)$minPrice);
                    } elseif ($maxPrice !== null) {
                        $subQ->where('minimum_bid', '<=', (float)$maxPrice);
                    }
                });
            });
        }

        if ($request->filled('seller_type')) {
            $query->where('seller_type', $request->seller_type);
        }

        if ($request->filled('listing_country_id')) {
            $query->where('country_id', $request->listing_country_id);
        }

        if ($request->filled('listing_city_id')) {
            $query->where('city_id', $request->listing_city_id);
        }

        // License plate relation filters
        $query->whereHas('licensePlate', function ($q) use ($request) {
            if ($request->filled('plate_country_id')) {
                $q->where('country_id', $request->plate_country_id);
            }

            if ($request->filled('plate_city_id')) {
                $q->where('city_id', $request->plate_city_id);
            }

            if ($request->filled('plate_format_id')) {
                $q->where('plate_format_id', $request->plate_format_id);
            }

            if ($request->filled('plate_search')) {
                $q->whereHas('fieldValues', function ($q2) use ($request) {
                    $q2->where('field_value', 'like', '%' . $request->plate_search . '%');
                });
            }
        });

        // Filtre par nom de pays
        if ($countryName) {
            $countryFilteredQuery = clone $query;
            $countryFilteredQuery->whereHas('country', function ($q) use ($countryName) {
                $q->where('name', 'LIKE', '%' . $countryName . '%');
            });

            $countCount = $countryFilteredQuery->count();

            if ($countCount === 0) {
                $showingAllCountries = true;
                $message = "No listings found for '{$countryName}'. Showing all countries instead.";
            } else {
                $query = $countryFilteredQuery;
                $message = "Showing listings for '{$countryName}'.";
            }
        }

        // Apply pagination if requested
        if ($usePagination) {
            $results = $query->select([
                'id',
                'title',
                'description',
                'price',
                'auction_enabled',
                'minimum_bid',
                'created_at',
                'seller_type',
                'country_id',
                'city_id',
                'category_id',
                DB::raw('(SELECT MAX(bid_amount) FROM auction_histories WHERE auction_histories.listing_id = listings.id) as current_bid')
            ])->paginate($perPage, ['*'], 'page', $page);
        } else {
            $results = $query->select([
                'id',
                'title',
                'description',
                'price',
                'auction_enabled',
                'minimum_bid',
                'created_at',
                'seller_type',
                'country_id',
                'city_id',
                'category_id',
                DB::raw('(SELECT MAX(bid_amount) FROM auction_histories WHERE auction_histories.listing_id = listings.id) as current_bid')
            ])->get();
        }

        // Get the collection of items
        $resultsCollection = $usePagination ? $results->getCollection() : $results;

        // Load relations
        $resultsCollection->load([
            'images' => function ($q) {
                $q->select('listing_id', 'image_url')->limit(1);
            },
            'licensePlate.format',
            'licensePlate.city',
            'licensePlate.country',
            'licensePlate.fieldValues.formatField',
            'country:id,name',
            'city:id,name',
            'category:id,name',
            'country.currencyExchangeRate:id,country_id,currency_symbol'
        ]);

        // Format results
        $formattedResults = $resultsCollection->map(function ($listing) {
            $displayPrice = $listing->price;
            $isAuction = false;

            if (!$displayPrice && $listing->auction_enabled) {
                $displayPrice = $listing->current_bid ?: $listing->minimum_bid;
                $isAuction = true;
            }

            $currencySymbol = $listing->country?->currencyExchangeRate?->currency_symbol ?? 'MAD';

            return [
                'id' => $listing->id,
                'title' => $listing->title,
                'description' => $listing->description,
                'price' => $displayPrice,
                'is_auction' => $isAuction,
                'minimum_bid' => $listing->minimum_bid,
                'current_bid' => $listing->current_bid,
                'currency' => $currencySymbol,
                'category' => $listing->category?->name ?? null,
                'listing_date' => $listing->created_at?->format('Y-m-d H:i:s') ?? null,
                'seller_type' => $listing->seller_type,
                'seller_location' => [
                    'country' => $listing->country?->name ?? null,
                    'city' => $listing->city?->name ?? null,
                ],
                'image' => $listing->images->first()?->image_url ?? null,
                'license_plate' => [
                    'format' => $listing->licensePlate?->format?->name,
                    'plate_location' => [
                        'city' => $listing->licensePlate?->city?->name,
                        'country' => $listing->licensePlate?->country?->name,
                    ],
                    'fields' => $listing->licensePlate?->fieldValues->map(function ($fieldValue) {
                        return [
                            'field_id' => $fieldValue->formatField?->id,
                            'field_name' => $fieldValue->formatField?->field_name,
                            'value' => $fieldValue->field_value,
                        ];
                    })
                ]
            ];
        });

        // Build response
        $response = [
            'message' => $message ?: 'Showing all listings.',
            'searched_country' => $countryName,
            'showing_all_countries' => $showingAllCountries,
            'total_listings' => $usePagination ? $results->total() : $formattedResults->count(),
            'success' => true,
        ];

        // Add pagination metadata only if pagination is used
        if ($usePagination) {
            $response['current_page'] = $results->currentPage();
            $response['per_page'] = $results->perPage();
            $response['last_page'] = $results->lastPage();
            $response['from'] = $results->firstItem();
            $response['to'] = $results->lastItem();
        }

        // Add results array
        $response['results'] = $formattedResults;

        return response()->json($response);
    }

    /**
     * @OA\Get(
     *     path="/api/filter-options-license-plates",
     *     summary="Get license plate filter options",
     *     description="Returns available filter options (countries, cities, formats, fields, price range) for license plates",
     *     operationId="getLicensePlateFilterOptions",
     *     tags={"Filters"},
     *     @OA\Response(
     *         response=200,
     *         description="Filter options retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="filter_options", type="object",
     *                 @OA\Property(property="countries", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="cities", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="plate_formats", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="format_fields", type="object"),
     *                 @OA\Property(property="price_range", type="object",
     *                     @OA\Property(property="min", type="number", example=100),
     *                     @OA\Property(property="max", type="number", example=5000)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function getLicensePlateFilterOptions()
    {
        try {
            $countries = DB::table('countries')
                ->join('license_plates', 'countries.id', '=', 'license_plates.country_id')
                ->select('countries.id', 'countries.name', 'countries.code')
                ->distinct()
                ->orderBy('countries.name')
                ->get();

            $cities = DB::table('cities')
                ->join('license_plates', 'cities.id', '=', 'license_plates.city_id')
                ->select('cities.id', 'cities.name')
                ->distinct()
                ->orderBy('cities.name')
                ->get();

            $formats = DB::table('plate_formats')
                ->select('id', 'name', 'description')
                ->orderBy('name')
                ->get();

            $fields = DB::table('plate_format_fields')
                ->join('plate_formats', 'plate_format_fields.plate_format_id', '=', 'plate_formats.id')
                ->select(
                    'plate_format_fields.id',
                    'plate_format_fields.field_name',
                    'plate_format_fields.is_required',
                    'plate_formats.id as format_id',
                    'plate_formats.name as format_name'
                )
                ->orderBy('plate_formats.name')
                ->orderBy('plate_format_fields.field_name')
                ->get()
                ->groupBy('format_name');

            $priceRange = DB::table('listings')
                ->where('category_id', 3)
                ->selectRaw('MIN(price) as min, MAX(price) as max')
                ->first();

            return response()->json([
                'success' => true,
                'filter_options' => [
                    'countries' => $countries,
                    'cities' => $cities,
                    'plate_formats' => $formats,
                    'format_fields' => $fields,
                    'price_range' => [
                        'min' => $priceRange->min ?? 0,
                        'max' => $priceRange->max ?? 0,
                    ]
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des filtres.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
