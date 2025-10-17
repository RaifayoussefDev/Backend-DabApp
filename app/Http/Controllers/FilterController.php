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
     * Helper method to check if a parameter has a valid value
     */
    private function hasValue($value)
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && empty($value)) {
            return false;
        }

        return true;
    }

    /**
     * @OA\Get(
     *     path="/api/filter/motorcycles",
     *     summary="Filter motorcycles",
     *     description="Filter motorcycles by category, price, brand, model, year, condition, mileage, seller type and location",
     *     operationId="filterMotorcycles",
     *     tags={"Filters"},
     *     @OA\Parameter(name="category_id", in="query", description="Category ID filter (1=motorcycles, 2=cars, etc.)", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="country", in="query", description="Filter by country name (supports partial matching). If no results found, shows all countries.", required=false, @OA\Schema(type="string", example="Morocco")),
     *     @OA\Parameter(name="min_price", in="query", description="Minimum price filter (includes fixed price and minimum bid for auctions)", required=false, @OA\Schema(type="number", format="float", minimum=0)),
     *     @OA\Parameter(name="max_price", in="query", description="Maximum price filter (includes fixed price and minimum bid for auctions)", required=false, @OA\Schema(type="number", format="float", minimum=0)),
     *     @OA\Parameter(name="brands[]", in="query", description="Array of brand IDs to filter", required=false, @OA\Schema(type="array", @OA\Items(type="integer"))),
     *     @OA\Parameter(name="models[]", in="query", description="Array of model IDs to filter", required=false, @OA\Schema(type="array", @OA\Items(type="integer"))),
     *     @OA\Parameter(name="types[]", in="query", description="Array of motorcycle type IDs to filter", required=false, @OA\Schema(type="array", @OA\Items(type="integer"))),
     *     @OA\Parameter(name="years[]", in="query", description="Array of year IDs to filter", required=false, @OA\Schema(type="array", @OA\Items(type="integer"))),
     *     @OA\Parameter(name="condition", in="query", description="Motorcycle condition", required=false, @OA\Schema(type="string", enum={"new", "used", "excellent", "good", "fair", "poor"})),
     *     @OA\Parameter(name="min_mileage", in="query", description="Minimum mileage filter", required=false, @OA\Schema(type="integer", minimum=0)),
     *     @OA\Parameter(name="max_mileage", in="query", description="Maximum mileage filter", required=false, @OA\Schema(type="integer", minimum=0)),
     *     @OA\Parameter(name="seller_type", in="query", description="Type of seller", required=false, @OA\Schema(type="string", enum={"individual", "professional", "dealer"})),
     *     @OA\Parameter(name="country_id", in="query", description="Country ID filter (alternative to country name)", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="city_id", in="query", description="City ID filter", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", required=false, description="Page number for pagination", @OA\Schema(type="integer", minimum=1), example=1),
     *     @OA\Parameter(name="per_page", in="query", required=false, description="Number of items per page (default: 15, max: 100)", @OA\Schema(type="integer", minimum=1, maximum=100), example=15),
     *     @OA\Response(response=200, description="Successful operation"),
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

        $page = $request->get('page');
        $perPage = $request->get('per_page', 15);
        $perPage = min($perPage, 100);
        $usePagination = !is_null($page);

        $categoryId = $request->input('category_id', 1);
        $query->where('category_id', $categoryId)->where('status', 'published');

        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');

        if ($this->hasValue($minPrice) || $this->hasValue($maxPrice)) {
            $query->where(function ($q) use ($minPrice, $maxPrice) {
                $q->where(function ($subQ) use ($minPrice, $maxPrice) {
                    $subQ->whereNotNull('price');
                    if ($this->hasValue($minPrice) && $this->hasValue($maxPrice)) {
                        $subQ->whereBetween('price', [(float)$minPrice, (float)$maxPrice]);
                    } elseif ($this->hasValue($minPrice)) {
                        $subQ->where('price', '>=', (float)$minPrice);
                    } elseif ($this->hasValue($maxPrice)) {
                        $subQ->where('price', '<=', (float)$maxPrice);
                    }
                });
                $q->orWhere(function ($subQ) use ($minPrice, $maxPrice) {
                    $subQ->whereNull('price')->where('auction_enabled', true);
                    if ($this->hasValue($minPrice) && $this->hasValue($maxPrice)) {
                        $subQ->whereBetween('minimum_bid', [(float)$minPrice, (float)$maxPrice]);
                    } elseif ($this->hasValue($minPrice)) {
                        $subQ->where('minimum_bid', '>=', (float)$minPrice);
                    } elseif ($this->hasValue($maxPrice)) {
                        $subQ->where('minimum_bid', '<=', (float)$maxPrice);
                    }
                });
            });
        }

        $brands = $request->input('brands');
        $models = $request->input('models');
        $types = $request->input('types');
        $years = $request->input('years');
        $condition = $request->input('condition');
        $minMileage = $request->input('min_mileage');
        $maxMileage = $request->input('max_mileage');

        if ($this->hasValue($brands) || $this->hasValue($models) || $this->hasValue($types) ||
            $this->hasValue($years) || $this->hasValue($condition) ||
            $this->hasValue($minMileage) || $this->hasValue($maxMileage)) {

            $query->whereHas('motorcycle', function ($q) use ($brands, $models, $types, $years, $condition, $minMileage, $maxMileage) {
                if ($this->hasValue($brands)) {
                    $q->whereIn('brand_id', $brands);
                }
                if ($this->hasValue($models)) {
                    $q->whereIn('model_id', $models);
                }
                if ($this->hasValue($types)) {
                    $q->whereIn('type_id', $types);
                }
                if ($this->hasValue($years)) {
                    $q->whereIn('year_id', $years);
                }
                if ($this->hasValue($condition)) {
                    $q->where('general_condition', $condition);
                }
                if ($this->hasValue($minMileage) && $this->hasValue($maxMileage)) {
                    $q->whereBetween('mileage', [(int)$minMileage, (int)$maxMileage]);
                } elseif ($this->hasValue($minMileage)) {
                    $q->where('mileage', '>=', (int)$minMileage);
                } elseif ($this->hasValue($maxMileage)) {
                    $q->where('mileage', '<=', (int)$maxMileage);
                }
            });
        }

        $sellerType = $request->input('seller_type');
        if ($this->hasValue($sellerType)) {
            $query->where('seller_type', $sellerType);
        }

        $countryId = $request->input('country_id');
        if ($this->hasValue($countryId)) {
            $query->where('country_id', $countryId);
        }

        $cityId = $request->input('city_id');
        if ($this->hasValue($cityId)) {
            $query->where('city_id', $cityId);
        }

        if ($this->hasValue($countryName)) {
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

        if ($usePagination) {
            $motorcycles = $query->select(['id', 'title', 'description', 'price', 'auction_enabled', 'minimum_bid', 'created_at', 'seller_type', 'country_id', 'city_id', 'category_id', DB::raw('(SELECT MAX(bid_amount) FROM auction_histories WHERE auction_histories.listing_id = listings.id) as current_bid')])->paginate($perPage, ['*'], 'page', $page);
        } else {
            $motorcycles = $query->select(['id', 'title', 'description', 'price', 'auction_enabled', 'minimum_bid', 'created_at', 'seller_type', 'country_id', 'city_id', 'category_id', DB::raw('(SELECT MAX(bid_amount) FROM auction_histories WHERE auction_histories.listing_id = listings.id) as current_bid')])->get();
        }

        $motorcyclesCollection = $usePagination ? $motorcycles->getCollection() : $motorcycles;

        $motorcyclesCollection->load([
            'images' => function ($query) { $query->select('listing_id', 'image_url')->limit(1); },
            'motorcycle' => function ($query) {
                $query->select('id', 'listing_id', 'brand_id', 'model_id', 'year_id', 'type_id')
                    ->with(['brand:id,name', 'model:id,name', 'year:id,year', 'type:id,name']);
            },
            'country:id,name', 'city:id,name', 'category:id,name',
            'country.currencyExchangeRate:id,country_id,currency_symbol'
        ]);

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

        $response = [
            'message' => $message ?: 'Showing all listings.',
            'searched_country' => $countryName,
            'showing_all_countries' => $showingAllCountries,
            'total_listings' => $usePagination ? $motorcycles->total() : $formattedMotorcycles->count(),
        ];

        if ($usePagination) {
            $response['current_page'] = $motorcycles->currentPage();
            $response['per_page'] = $motorcycles->perPage();
            $response['last_page'] = $motorcycles->lastPage();
            $response['from'] = $motorcycles->firstItem();
            $response['to'] = $motorcycles->lastItem();
        }

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
     *     @OA\Parameter(name="country", in="query", description="Filter by country name", required=false, @OA\Schema(type="string", example="Morocco")),
     *     @OA\Parameter(name="min_price", in="query", description="Minimum price filter", required=false, @OA\Schema(type="number", format="float", minimum=0)),
     *     @OA\Parameter(name="max_price", in="query", description="Maximum price filter", required=false, @OA\Schema(type="number", format="float", minimum=0)),
     *     @OA\Parameter(name="bike_part_brands[]", in="query", description="Array of bike part brand IDs", required=false, @OA\Schema(type="array", @OA\Items(type="integer"))),
     *     @OA\Parameter(name="bike_part_categories[]", in="query", description="Array of bike part category IDs", required=false, @OA\Schema(type="array", @OA\Items(type="integer"))),
     *     @OA\Parameter(name="condition", in="query", description="Spare part condition", required=false, @OA\Schema(type="string", enum={"new", "used", "excellent", "good", "fair", "poor"})),
     *     @OA\Parameter(name="seller_type", in="query", description="Type of seller", required=false, @OA\Schema(type="string", enum={"individual", "professional", "dealer"})),
     *     @OA\Parameter(name="country_id", in="query", description="Country ID filter", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="city_id", in="query", description="City ID filter", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", required=false, description="Page number", @OA\Schema(type="integer", minimum=1), example=1),
     *     @OA\Parameter(name="per_page", in="query", required=false, description="Items per page", @OA\Schema(type="integer", minimum=1, maximum=100), example=15),
     *     @OA\Response(response=200, description="Successful operation"),
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

        $page = $request->get('page');
        $perPage = $request->get('per_page', 15);
        $perPage = min($perPage, 100);
        $usePagination = !is_null($page);

        $query->where('category_id', 2)->where('status', 'published');

        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');

        if ($this->hasValue($minPrice) || $this->hasValue($maxPrice)) {
            $query->where(function ($q) use ($minPrice, $maxPrice) {
                $q->where(function ($subQ) use ($minPrice, $maxPrice) {
                    $subQ->whereNotNull('price');
                    if ($this->hasValue($minPrice) && $this->hasValue($maxPrice)) {
                        $subQ->whereBetween('price', [(float)$minPrice, (float)$maxPrice]);
                    } elseif ($this->hasValue($minPrice)) {
                        $subQ->where('price', '>=', (float)$minPrice);
                    } elseif ($this->hasValue($maxPrice)) {
                        $subQ->where('price', '<=', (float)$maxPrice);
                    }
                });
                $q->orWhere(function ($subQ) use ($minPrice, $maxPrice) {
                    $subQ->whereNull('price')->where('auction_enabled', true);
                    if ($this->hasValue($minPrice) && $this->hasValue($maxPrice)) {
                        $subQ->whereBetween('minimum_bid', [(float)$minPrice, (float)$maxPrice]);
                    } elseif ($this->hasValue($minPrice)) {
                        $subQ->where('minimum_bid', '>=', (float)$minPrice);
                    } elseif ($this->hasValue($maxPrice)) {
                        $subQ->where('minimum_bid', '<=', (float)$maxPrice);
                    }
                });
            });
        }

        $bikePartBrands = $request->input('bike_part_brands');
        $bikePartCategories = $request->input('bike_part_categories');
        $condition = $request->input('condition');

        if ($this->hasValue($bikePartBrands) || $this->hasValue($bikePartCategories) || $this->hasValue($condition)) {
            $query->whereHas('sparePart', function ($q) use ($bikePartBrands, $bikePartCategories, $condition) {
                if ($this->hasValue($bikePartBrands)) {
                    $q->whereIn('bike_part_brand_id', $bikePartBrands);
                }
                if ($this->hasValue($bikePartCategories)) {
                    $q->whereIn('bike_part_category_id', $bikePartCategories);
                }
                if ($this->hasValue($condition)) {
                    $q->where('condition', $condition);
                }
            });
        }

        $sellerType = $request->input('seller_type');
        if ($this->hasValue($sellerType)) {
            $query->where('seller_type', $sellerType);
        }

        $countryId = $request->input('country_id');
        if ($this->hasValue($countryId)) {
            $query->where('country_id', $countryId);
        }

        $cityId = $request->input('city_id');
        if ($this->hasValue($cityId)) {
            $query->where('city_id', $cityId);
        }

        if ($this->hasValue($countryName)) {
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

        if ($usePagination) {
            $spareParts = $query->select(['id', 'title', 'description', 'price', 'auction_enabled', 'minimum_bid', 'created_at', 'seller_type', 'country_id', 'city_id', 'category_id', DB::raw('(SELECT MAX(bid_amount) FROM auction_histories WHERE auction_histories.listing_id = listings.id) as current_bid')])->paginate($perPage, ['*'], 'page', $page);
        } else {
            $spareParts = $query->select(['id', 'title', 'description', 'price', 'auction_enabled', 'minimum_bid', 'created_at', 'seller_type', 'country_id', 'city_id', 'category_id', DB::raw('(SELECT MAX(bid_amount) FROM auction_histories WHERE auction_histories.listing_id = listings.id) as current_bid')])->get();
        }

        $sparePartsCollection = $usePagination ? $spareParts->getCollection() : $spareParts;

        $sparePartsCollection->load([
            'images' => function ($query) { $query->select('listing_id', 'image_url')->limit(1); },
            'sparePart' => function ($query) {
                $query->select('id', 'listing_id', 'bike_part_brand_id', 'bike_part_category_id', 'condition')
                    ->with(['bikePartBrand:id,name', 'bikePartCategory:id,name']);
            },
            'country:id,name', 'city:id,name', 'category:id,name',
            'country.currencyExchangeRate:id,country_id,currency_symbol'
        ]);

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

        $response = [
            'message' => $message ?: 'Showing all listings.',
            'searched_country' => $countryName,
            'showing_all_countries' => $showingAllCountries,
            'total_listings' => $usePagination ? $spareParts->total() : $formattedSpareParts->count(),
        ];

        if ($usePagination) {
            $response['current_page'] = $spareParts->currentPage();
            $response['per_page'] = $spareParts->perPage();
            $response['last_page'] = $spareParts->lastPage();
            $response['from'] = $spareParts->firstItem();
            $response['to'] = $spareParts->lastItem();
        }

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
     *     @OA\Parameter(name="country", in="query", description="Filter by country name", required=false, @OA\Schema(type="string", example="Morocco")),
     *     @OA\Parameter(name="min_price", in="query", description="Minimum price filter", required=false, @OA\Schema(type="number", format="float", minimum=0)),
     *     @OA\Parameter(name="max_price", in="query", description="Maximum price filter", required=false, @OA\Schema(type="number", format="float", minimum=0)),
     *     @OA\Parameter(name="seller_type", in="query", description="Type of seller", required=false, @OA\Schema(type="string", enum={"individual", "professional", "dealer"})),
     *     @OA\Parameter(name="listing_country_id", in="query", description="Filter by listing country ID", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="listing_city_id", in="query", description="Filter by listing city ID", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="plate_country_id", in="query", description="Filter by plate country ID", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="plate_city_id", in="query", description="Filter by plate city ID", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="plate_format_id", in="query", description="Filter by license plate format ID", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="plate_search", in="query", description="Search value within license plate fields", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", required=false, description="Page number", @OA\Schema(type="integer", minimum=1), example=1),
     *     @OA\Parameter(name="per_page", in="query", required=false, description="Items per page", @OA\Schema(type="integer", minimum=1, maximum=100), example=15),
     *     @OA\Response(response=200, description="Successful response"),
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

        $page = $request->get('page');
        $perPage = $request->get('per_page', 15);
        $perPage = min($perPage, 100);
        $usePagination = !is_null($page);

        $query->where('category_id', 3)->where('status', 'published');

        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');

        if ($this->hasValue($minPrice) || $this->hasValue($maxPrice)) {
            $query->where(function ($q) use ($minPrice, $maxPrice) {
                $q->where(function ($subQ) use ($minPrice, $maxPrice) {
                    $subQ->whereNotNull('price');
                    if ($this->hasValue($minPrice) && $this->hasValue($maxPrice)) {
                        $subQ->whereBetween('price', [(float)$minPrice, (float)$maxPrice]);
                    } elseif ($this->hasValue($minPrice)) {
                        $subQ->where('price', '>=', (float)$minPrice);
                    } elseif ($this->hasValue($maxPrice)) {
                        $subQ->where('price', '<=', (float)$maxPrice);
                    }
                });
                $q->orWhere(function ($subQ) use ($minPrice, $maxPrice) {
                    $subQ->whereNull('price')->where('auction_enabled', true);
                    if ($this->hasValue($minPrice) && $this->hasValue($maxPrice)) {
                        $subQ->whereBetween('minimum_bid', [(float)$minPrice, (float)$maxPrice]);
                    } elseif ($this->hasValue($minPrice)) {
                        $subQ->where('minimum_bid', '>=', (float)$minPrice);
                    } elseif ($this->hasValue($maxPrice)) {
                        $subQ->where('minimum_bid', '<=', (float)$maxPrice);
                    }
                });
            });
        }

        $sellerType = $request->input('seller_type');
        if ($this->hasValue($sellerType)) {
            $query->where('seller_type', $sellerType);
        }

        $listingCountryId = $request->input('listing_country_id');
        if ($this->hasValue($listingCountryId)) {
            $query->where('country_id', $listingCountryId);
        }

        $listingCityId = $request->input('listing_city_id');
        if ($this->hasValue($listingCityId)) {
            $query->where('city_id', $listingCityId);
        }

        $plateCountryId = $request->input('plate_country_id');
        $plateCityId = $request->input('plate_city_id');
        $plateFormatId = $request->input('plate_format_id');
        $plateSearch = $request->input('plate_search');

        if ($this->hasValue($plateCountryId) || $this->hasValue($plateCityId) ||
            $this->hasValue($plateFormatId) || $this->hasValue($plateSearch)) {

            $query->whereHas('licensePlate', function ($q) use ($plateCountryId, $plateCityId, $plateFormatId, $plateSearch) {
                if ($this->hasValue($plateCountryId)) {
                    $q->where('country_id', $plateCountryId);
                }
                if ($this->hasValue($plateCityId)) {
                    $q->where('city_id', $plateCityId);
                }
                if ($this->hasValue($plateFormatId)) {
                    $q->where('plate_format_id', $plateFormatId);
                }
                if ($this->hasValue($plateSearch)) {
                    $q->whereHas('fieldValues', function ($q2) use ($plateSearch) {
                        $q2->where('field_value', 'like', '%' . $plateSearch . '%');
                    });
                }
            });
        }

        if ($this->hasValue($countryName)) {
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

        if ($usePagination) {
            $results = $query->select(['id', 'title', 'description', 'price', 'auction_enabled', 'minimum_bid', 'created_at', 'seller_type', 'country_id', 'city_id', 'category_id', DB::raw('(SELECT MAX(bid_amount) FROM auction_histories WHERE auction_histories.listing_id = listings.id) as current_bid')])->paginate($perPage, ['*'], 'page', $page);
        } else {
            $results = $query->select(['id', 'title', 'description', 'price', 'auction_enabled', 'minimum_bid', 'created_at', 'seller_type', 'country_id', 'city_id', 'category_id', DB::raw('(SELECT MAX(bid_amount) FROM auction_histories WHERE auction_histories.listing_id = listings.id) as current_bid')])->get();
        }

        $resultsCollection = $usePagination ? $results->getCollection() : $results;

        $resultsCollection->load([
            'images' => function ($q) { $q->select('listing_id', 'image_url')->limit(1); },
            'licensePlate.format',
            'licensePlate.city',
            'licensePlate.country',
            'licensePlate.fieldValues.formatField',
            'country:id,name',
            'city:id,name',
            'category:id,name',
            'country.currencyExchangeRate:id,country_id,currency_symbol'
        ]);

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

        $response = [
            'message' => $message ?: 'Showing all listings.',
            'searched_country' => $countryName,
            'showing_all_countries' => $showingAllCountries,
            'total_listings' => $usePagination ? $results->total() : $formattedResults->count(),
            'success' => true,
        ];

        if ($usePagination) {
            $response['current_page'] = $results->currentPage();
            $response['per_page'] = $results->perPage();
            $response['last_page'] = $results->lastPage();
            $response['from'] = $results->firstItem();
            $response['to'] = $results->lastItem();
        }

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
     *     @OA\Response(response=200, description="Filter options retrieved successfully"),
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
