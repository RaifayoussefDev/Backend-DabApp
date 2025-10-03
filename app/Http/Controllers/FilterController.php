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
     *         description="Country ID filter",
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
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
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

        // ✅ Filtre de catégorie (par défaut = 1 pour motos)
        $categoryId = $request->input('category_id', 1);
        $query->where('category_id', $categoryId)->where('status', 'published');

        // ✅ Filtre de prix : prix fixe OU minimum_bid pour les enchères
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');

        if ($minPrice !== null || $maxPrice !== null) {
            $query->where(function ($q) use ($minPrice, $maxPrice) {
                // Pour les listings avec prix fixe
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

                // OU pour les listings aux enchères (vérifier minimum_bid)
                $q->orWhere(function ($subQ) use ($minPrice, $maxPrice) {
                    $subQ->whereNull('price')
                        ->where('auction_enabled', true);

                    // Filtrer par minimum_bid
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

        // ✅ Filtrer sur la relation motorcycle
        $query->whereHas('motorcycle', function ($q) use ($request) {
            // Filtre marques (brand_id) - plusieurs possibles
            if ($request->has('brands')) {
                $brands = $request->input('brands');
                if (is_array($brands) && count($brands) > 0) {
                    $q->whereIn('brand_id', $brands);
                }
            }

            // Filtre modèles (model_id) - plusieurs possibles
            if ($request->has('models')) {
                $models = $request->input('models');
                if (is_array($models) && count($models) > 0) {
                    $q->whereIn('model_id', $models);
                }
            }

            // Filtre types de moto (type_id) - plusieurs possibles
            if ($request->has('types')) {
                $types = $request->input('types');
                if (is_array($types) && count($types) > 0) {
                    $q->whereIn('type_id', $types);
                }
            }

            // Filtre années (year_id) - plusieurs possibles
            if ($request->has('years')) {
                $years = $request->input('years');
                if (is_array($years) && count($years) > 0) {
                    $q->whereIn('year_id', $years);
                }
            }

            // Filtre condition (ex: 'new', 'used')
            if ($request->has('condition')) {
                $condition = $request->input('condition');
                $q->where('general_condition', $condition);
            }

            // Filtre kilométrage
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

        // ✅ Filtres sur la table listings
        if ($request->has('seller_type')) {
            $query->where('seller_type', $request->input('seller_type'));
        }

        if ($request->has('country_id')) {
            $query->where('country_id', $request->input('country_id'));
        }

        if ($request->has('city_id')) {
            $query->where('city_id', $request->input('city_id'));
        }

        // ✅ Récupérer les résultats avec sous-requête pour les enchères
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
            // Sous-requête pour récupérer la dernière enchère
            DB::raw('(SELECT MAX(bid_amount) FROM auction_histories WHERE auction_histories.listing_id = listings.id) as current_bid')
        ])
            ->with([
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
                // ✅ Charger la relation currency_exchange_rate du country
                'country.currencyExchangeRate:id,country_id,currency_symbol'
            ])
            ->get()
            ->map(function ($listing) {
                // ✅ Déterminer le prix à afficher
                $displayPrice = $listing->price;
                $isAuction = false;

                if (!$displayPrice && $listing->auction_enabled) {
                    $displayPrice = $listing->current_bid ?: $listing->minimum_bid;
                    $isAuction = true;
                }

                // ✅ Récupérer le symbole de devise depuis currency_exchange_rates
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

        return response()->json([
            'motorcycles' => $motorcycles,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/filter/spare-parts",
     *     summary="Filter spare parts",
     *     description="Filter spare parts by price, brand, category, and condition",
     *     operationId="filterSpareParts",
     *     tags={"Filters"},
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="Minimum price filter",
     *         required=false,
     *         @OA\Schema(
     *             type="number",
     *             format="float",
     *             minimum=0
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="Maximum price filter",
     *         required=false,
     *         @OA\Schema(
     *             type="number",
     *             format="float",
     *             minimum=0
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="bike_part_brands[]",
     *         in="query",
     *         description="Array of bike part brand IDs to filter",
     *         required=false,
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(type="integer")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="bike_part_categories[]",
     *         in="query",
     *         description="Array of bike part category IDs to filter",
     *         required=false,
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(type="integer")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="condition",
     *         in="query",
     *         description="Spare part condition",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"new", "used", "excellent", "good", "fair", "poor"}
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Brake Pads Set"),
     *                 @OA\Property(property="description", type="string", example="High quality brake pads"),
     *                 @OA\Property(property="price", type="number", format="float", example=45.99),
     *                 @OA\Property(property="image", type="string", format="url", example="https://example.com/image.jpg", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function filterSpareParts(Request $request)
    {
        $query = Listing::where('category_id', 2)->where('status', 'published');

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float)$request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float)$request->max_price);
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

        // Filtres sur la table listings (similaire à filterMotorcycles)
        if ($request->filled('seller_type')) {
            $query->where('seller_type', $request->seller_type);
        }

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        // Récupérer les résultats avec les champs nécessaires et les relations
        $spareParts = $query->select('id', 'title', 'description', 'price', 'created_at', 'seller_type', 'country_id', 'city_id')
            ->with([
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
                'city:id,name'
            ])
            ->get()
            ->map(function ($listing) {
                return [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'description' => $listing->description,
                    'price' => $listing->price,
                    'currency' => config('paytabs.currency', 'MAD'), // Utilise la devise de config PayTabs
                    'brand' => $listing->sparePart?->bikePartBrand?->name ?? null,
                    'category' => $listing->sparePart?->bikePartCategory?->name ?? null,
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

        return response()->json([
            'spare_parts' => $spareParts,
        ]);
    }
    /**
     * @OA\Get(
     *     path="/api/filter/license-plates",
     *     summary="Filter license plates",
     *     description="Filter license plates by price, country, city, format and plate fields",
     *     operationId="filterLicensePlates",
     *     tags={"Filters"},
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="Minimum price filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float", minimum=0)
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="Maximum price filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float", minimum=0)
     *     ),
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         description="Filter by country ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="city_id",
     *         in="query",
     *         description="Filter by city ID",
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
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="price", type="number", format="float"),
     *                 @OA\Property(property="image", type="string", nullable=true),
     *                 @OA\Property(property="license_plate", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function getByCategory($category_id, Request $request)
    {
        $user = Auth::user();
        $countryName = $request->get('country');
        $plateSearch = $request->get('plate_search');
        $showingAllCountries = false;
        $message = '';
    
        // Build the base query
        $query = Listing::where('category_id', $category_id)
            ->where('status', 'published')
            ->orderBy('created_at', 'desc');
    
        // Add license plate field search for category 3 (license plates)
        if ($category_id == 3 && $plateSearch) {
            $query->whereHas('licensePlate', function ($licensePlateQuery) use ($plateSearch) {
                $licensePlateQuery->whereHas('fieldValues', function ($fieldQuery) use ($plateSearch) {
                    $fieldQuery->where('field_value', 'LIKE', '%' . $plateSearch . '%');
                });
            });
        }
    
        // Add country filter if provided
        if ($countryName) {
            $countryFilteredQuery = clone $query;
            $countryFilteredQuery->whereHas('country', function ($q) use ($countryName) {
                $q->where('name', 'LIKE', '%' . $countryName . '%');
            });
    
            $countryListings = $countryFilteredQuery->get();
    
            if ($countryListings->isEmpty()) {
                $listings = $query->get();
                $showingAllCountries = true;
                $message = "No listings found for '{$countryName}'. Showing all countries instead.";
            } else {
                $listings = $countryListings;
                $message = "Showing listings for '{$countryName}'.";
            }
        } else {
            $listings = $query->get();
    
            if ($category_id == 3 && $plateSearch) {
                $message = "Showing license plates containing '{$plateSearch}'.";
            } else {
                $message = "Showing all listings.";
            }
        }
    
        if ($countryName && $category_id == 3 && $plateSearch && !$showingAllCountries) {
            $message = "Showing license plates containing '{$plateSearch}' for '{$countryName}'.";
        }
    
        // ✅ Charger les relations nécessaires selon la catégorie
        $listings->load([
            'images' => function ($query) {
                $query->select('listing_id', 'image_url')->limit(1);
            },
            'category:id,name',
            'country:id,name',
            'city:id,name',
            'country.currencyExchangeRate:id,country_id,currency_symbol'
        ]);
    
        // Charger les relations spécifiques par catégorie
        if ($category_id == 1) {
            // Motorcycles
            $listings->load([
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
        } elseif ($category_id == 2) {
            // Spare parts
            $listings->load([
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
        } elseif ($category_id == 3) {
            // License plates - Chargement comme dans filterLicensePlates
            $listings->load([
                'licensePlate.format',
                'licensePlate.city',
                'licensePlate.country',
                'licensePlate.fieldValues.formatField'
            ]);
        }
    
        // ✅ Pour les enchères, charger la dernière enchère
        $listingIds = $listings->pluck('id');
        $currentBids = DB::table('auction_histories')
            ->whereIn('listing_id', $listingIds)
            ->select('listing_id', DB::raw('MAX(bid_amount) as current_bid'))
            ->groupBy('listing_id')
            ->pluck('current_bid', 'listing_id');
    
        // ✅ Formater les résultats selon le format de filterMotorcycles
        $formattedListings = $listings->map(function ($listing) use ($user, $currentBids) {
            $isInWishlist = false;
    
            if ($user) {
                $isInWishlist = DB::table('wishlists')
                    ->where('user_id', $user->id)
                    ->where('listing_id', $listing->id)
                    ->exists();
            }
    
            // ✅ Déterminer le prix à afficher (comme filterMotorcycles)
            $displayPrice = $listing->price;
            $isAuction = false;
            $currentBid = $currentBids[$listing->id] ?? null;
    
            if (!$displayPrice && $listing->auction_enabled) {
                $displayPrice = $currentBid ?: $listing->minimum_bid;
                $isAuction = true;
            }
    
            // ✅ Récupérer le symbole de devise
            $currencySymbol = $listing->country?->currencyExchangeRate?->currency_symbol ?? 'MAD';
    
            // ✅ Garder toutes les colonnes originales + ajouter les nouvelles
            $baseData = [
                'id' => $listing->id,
                'title' => $listing->title,
                'description' => $listing->description,
                'price' => $listing->price, // ✅ Prix original de la DB
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
                'wishlist' => $isInWishlist,
                
                // ✅ NOUVELLES COLONNES AJOUTÉES (comme filterMotorcycles)
                'display_price' => $displayPrice, // Prix à afficher (prix fixe ou enchère)
                'is_auction' => $isAuction, // Boolean pour identifier les enchères
                'current_bid' => $currentBid, // Montant de la dernière enchère
                'currency' => $currencySymbol, // Symbole de devise
            ];
    
            // ✅ Ajouter les données spécifiques par catégorie
            if ($listing->category_id == 1 && $listing->motorcycle) {
                // Motorcycle data
                $baseData['motorcycle'] = [
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
                $baseData['spare_part'] = [
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
    
                $baseData['license_plate'] = [
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
        });
    
        return response()->json([
            'message' => $message,
            'searched_country' => $countryName,
            'showing_all_countries' => $showingAllCountries,
            'total_listings' => $formattedListings->count(),
            'listings' => $formattedListings
        ]);
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
