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
     *     description="Filter motorcycles by price, brand, and condition",
     *     operationId="filterMotorcycles",
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
     *         name="brands[]",
     *         in="query",
     *         description="Array of brand IDs to filter",
     *         required=false,
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(type="integer")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="condition",
     *         in="query",
     *         description="Motorcycle condition",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"new", "used", "excellent", "good", "fair", "poor"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="year_min",
     *         in="query",
     *         description="Minimum year filter",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             minimum=1900
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="year_max",
     *         in="query",
     *         description="Maximum year filter",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             minimum=1900
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="seller_type",
     *         in="query",
     *         description="Type of seller",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"individual", "professional", "dealer"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         description="Country ID filter",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="city_id",
     *         in="query",
     *         description="City ID filter",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         )
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
     *                     @OA\Property(property="currency", type="string", example="MAD"),
     *                     @OA\Property(property="brand", type="string", example="Honda"),
     *                     @OA\Property(property="model", type="string", example="CBR600RR"),
     *                     @OA\Property(property="year", type="integer", example=2020),
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
    public function filterMotorcycles(Request $request)
    {
        $query = Listing::query();

        // On ne veut que les listings de catégorie 1 (motos)
        $query->where('category_id', 1)->where('status', 'published');

        // Filtre prix entre min_price et max_price sur la table listings
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');

        if ($minPrice !== null && $maxPrice !== null) {
            $query->whereBetween('price', [(float)$minPrice, (float)$maxPrice]);
        } elseif ($minPrice !== null) {
            $query->where('price', '>=', (float)$minPrice);
        } elseif ($maxPrice !== null) {
            $query->where('price', '<=', (float)$maxPrice);
        }

        // Filtrer sur la relation motorcycle
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

        // Récupérer les résultats avec les champs nécessaires et les relations
        $motorcycles = $query->select('id', 'title', 'description', 'price', 'created_at', 'seller_type', 'country_id', 'city_id')
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
    public function filterLicensePlates(Request $request)
    {
        $query = Listing::query()
            ->where('category_id', 3)
            ->where('status', 'published');

        // Price filtering
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        // Filtres sur la table listings (seller_type, location)
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

        // Select fields and preload image + licensePlate + fieldValues + listing location
        $results = $query->select('id', 'title', 'description', 'price', 'created_at', 'seller_type', 'country_id', 'city_id')
            ->with([
                'images' => function ($q) {
                    $q->select('listing_id', 'image_url')->limit(1);
                },
                'licensePlate.format',
                'licensePlate.city',
                'licensePlate.country',
                'licensePlate.fieldValues.formatField',
                'country:id,name', // Location du listing
                'city:id,name'     // Location du listing
            ])
            ->get()
            ->map(function ($listing) {
                return [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'description' => $listing->description,
                    'price' => $listing->price,
                    'currency' => config('paytabs.currency', 'MAD'), // Devise ajoutée
                    'listing_date' => $listing->created_at?->format('Y-m-d H:i:s') ?? null, // Date ajoutée
                    'seller_type' => $listing->seller_type,
                    'seller_location' => [ // Location du vendeur
                        'country' => $listing->country?->name ?? null,
                        'city' => $listing->city?->name ?? null,
                    ],
                    'image' => $listing->images->first()?->image_url ?? null,
                    'license_plate' => [
                        'format' => $listing->licensePlate?->format?->name,
                        'plate_location' => [ // Location de la plaque (peut être différente du vendeur)
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

        return response()->json([
            'success' => true,
            'results' => $results,
            'count' => $results->count()
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
