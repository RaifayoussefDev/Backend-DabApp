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
        $query->where('category_id', 1);

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

            // Filtre condition (ex: 'new', 'used')
            if ($request->has('condition')) {
                $condition = $request->input('condition');
                $q->where('general_condition', $condition);
            }
        });

        // Récupérer les résultats avec seulement les champs nécessaires et la première image
        $motorcycles = $query->select('id', 'title', 'description', 'price')
            ->with(['images' => function ($query) {
                $query->select('listing_id', 'image_url')->limit(1);
            }])
            ->get()
            ->map(function ($listing) {
                return [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'description' => $listing->description,
                    'price' => $listing->price,
                    'image' => $listing->images->first()->image_url ?? null,
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
        $query = Listing::where('category_id', 2);

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

        // Récupérer les résultats avec seulement les champs nécessaires et la première image
        $spareParts = $query->select('id', 'title', 'description', 'price')
            ->with(['images' => function ($query) {
                $query->select('listing_id', 'image_url')->limit(1);
            }])
            ->get()
            ->map(function ($listing) {
                return [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'description' => $listing->description,
                    'price' => $listing->price,
                    'image' => $listing->images->first()->image_url ?? null,
                ];
            });

        return response()->json($spareParts);
    }

    /**
     * @OA\Get(
     *     path="/api/filter-license-plates",
     *     summary="Filter license plates",
     *     description="Filter license plates by price, country, city, format, and custom fields",
     *     operationId="filterLicensePlates",
     *     tags={"Filters"},
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="Minimum price",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="Maximum price",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="listing_countries[]",
     *         in="query",
     *         description="Filter by listing countries",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer"))
     *     ),
     *     @OA\Parameter(
     *         name="listing_cities[]",
     *         in="query",
     *         description="Filter by listing cities",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer"))
     *     ),
     *     @OA\Parameter(
     *         name="plate_countries[]",
     *         in="query",
     *         description="Filter by plate countries",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer"))
     *     ),
     *     @OA\Parameter(
     *         name="plate_cities[]",
     *         in="query",
     *         description="Filter by plate cities",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer"))
     *     ),
     *     @OA\Parameter(
     *         name="plate_formats[]",
     *         in="query",
     *         description="Filter by plate format IDs",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer"))
     *     ),
     *     @OA\Parameter(
     *         name="digits_counts[]",
     *         in="query",
     *         description="Filter by digit counts",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="integer"))
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Filtered license plates",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="total", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */

    public function filterLicensePlates(Request $request)
    {
        $query = Listing::where('category_id', 3);

        // Filtres de prix
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        // Filtres listing (ville/pays)
        if ($request->filled('listing_countries')) {
            $query->whereIn('country_id', $request->listing_countries);
        }

        if ($request->filled('listing_cities')) {
            $query->whereIn('city_id', $request->listing_cities);
        }

        // Filtres plaque (ville/pays)
        if ($request->filled('plate_countries')) {
            $query->whereHas('licensePlate', fn($q) => $q->whereIn('country_id', $request->plate_countries));
        }

        if ($request->filled('plate_cities')) {
            $query->whereHas('licensePlate', fn($q) => $q->whereIn('city_id', $request->plate_cities));
        }

        // Compatibilité anciens filtres
        if ($request->filled('countries')) {
            $query->whereHas('licensePlate', fn($q) => $q->whereIn('country_id', $request->countries));
        }

        if ($request->filled('cities')) {
            $query->whereHas('licensePlate', fn($q) => $q->whereIn('city_id', $request->cities));
        }

        // Format de plaque
        if ($request->filled('plate_formats')) {
            $query->whereHas('licensePlate', fn($q) => $q->whereIn('plate_format_id', $request->plate_formats));
        }

        // Nombre de chiffres

        // Champs personnalisés
        if ($request->filled('field_filters')) {
            foreach ($request->field_filters as $filter) {
                if (isset($filter['field_id'], $filter['value'])) {
                    $query->whereHas('licensePlate.fieldValues', function ($q) use ($filter) {
                        $q->where('plate_format_field_id', $filter['field_id'])
                            ->where('field_value', 'LIKE', '%' . $filter['value'] . '%');
                    });
                }
            }
        }

        // Charger les relations nécessaires
        $listings = $query->with([
            'images:id,listing_id,image_url',
            'country:id,name,code,created_at,updated_at',
            'city:id,name',
            'licensePlate:id,listing_id,plate_format_id,country_id,city_id',
            'licensePlate.city:id,name',
            'licensePlate.country:id,name,code,created_at,updated_at',
            'licensePlate.format:id,name,country_id',
            'licensePlate.format.country:id,name,code,created_at,updated_at',
            'licensePlate.fieldValues:id,license_plate_id,plate_format_field_id,field_value',
            'licensePlate.fieldValues.formatField:id,field_name,position,is_required,max_length'
        ])
            ->latest()
            ->get()
            ->map(function ($listing) {
                $plate = $listing->licensePlate;

                $fields = [];
                if ($plate && $plate->fieldValues) {
                    foreach ($plate->fieldValues as $fv) {
                        $fields[] = [
                            'field_id' => $fv->formatField->id ?? null,
                            'field_name' => $fv->formatField->field_name ?? null,
                            'field_position' => $fv->formatField->position ?? null,
                            'is_required' => $fv->formatField->is_required ?? false,
                            'max_length' => $fv->formatField->max_length ?? null,
                            'value' => $fv->field_value,
                        ];
                    }
                }

                return [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'description' => $listing->description,
                    'price' => $listing->price,
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
                    'images' => $listing->images->pluck('image_url')->toArray(),
                    'wishlist' => false,
                    'license_plate' => $plate ? [
                        'plate_format' => $plate->format ? [
                            'id' => $plate->format->id,
                            'name' => $plate->format->name,
                            'country' => $plate->format->country ? [
                                'id' => $plate->format->country->id,
                                'name' => $plate->format->country->name,
                                'code' => $plate->format->country->code,
                                'created_at' => $plate->format->country->created_at,
                                'updated_at' => $plate->format->country->updated_at,
                            ] : null,
                        ] : null,
                        'city' => $plate->city?->name,
                        'country_id' => $plate->country_id,
                        'fields' => $fields,
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $listings,
            'total' => $listings->count(),
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
