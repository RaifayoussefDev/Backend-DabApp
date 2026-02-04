<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LicensePlate;
use App\Models\LicensePlateValue;
use App\Models\City;
use App\Models\PlateFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="License Plates",
 *     description="API endpoints for managing license plates"
 * )
 */
class LicensePlateController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/license-plates",
     *     tags={"License Plates"},
     *     summary="Create a new license plate",
     *     description="Creates a new license plate with associated field values",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"listing_id", "country_id", "city_id", "plate_format_id", "fields"},
     *             @OA\Property(property="listing_id", type="integer", example=1, description="ID of the listing"),
     *             @OA\Property(property="country_id", type="integer", example=1, description="ID of the country"),
     *             @OA\Property(property="city_id", type="integer", example=1, description="ID of the city"),
     *             @OA\Property(property="plate_format_id", type="integer", example=1, description="ID of the plate format"),
     *             @OA\Property(
     *                 property="fields",
     *                 type="array",
     *                 description="Array of field values",
     *                 @OA\Items(
     *                     @OA\Property(property="field_id", type="integer", example=1, description="ID of the format field"),
     *                     @OA\Property(property="value", type="string", example="ABC123", description="Value for the field")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="License plate created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="License plate created successfully"),
     *             @OA\Property(property="id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object", description="Validation errors")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", description="Error message")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'listing_id' => 'required|exists:listings,id',
            'country_id' => 'required|exists:countries,id',
            'city_id' => 'required|exists:cities,id',
            'plate_format_id' => 'required|exists:plate_formats,id',
            'fields' => 'required|array',
            'fields.*.field_id' => 'required|exists:plate_format_fields,id',
            'fields.*.value' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $plate = LicensePlate::create([
                'listing_id' => $request->listing_id,
                'country_id' => $request->country_id,
                'city_id' => $request->city_id,
                'plate_format_id' => $request->plate_format_id,
            ]);

            foreach ($request->fields as $field) {
                LicensePlateValue::create([
                    'license_plate_id' => $plate->id,
                    'plate_format_field_id' => $field['field_id'],
                    'field_value' => $field['value']
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'License plate created successfully', 'id' => $plate->id], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/license-plates",
     *     tags={"License Plates"},
     *     summary="Get all license plates",
     *     description="Retrieve a list of all license plates with their related data",
     *     @OA\Response(
     *         response=200,
     *         description="List of license plates retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="license_plates",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="listing_id", type="integer", example=1),
     *                     @OA\Property(property="country_id", type="integer", example=1),
     *                     @OA\Property(property="city_id", type="integer", example=1),
     *                     @OA\Property(property="plate_format_id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="city",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Paris")
     *                     ),
     *                     @OA\Property(
     *                         property="format",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Standard Format")
     *                     ),
     *                     @OA\Property(
     *                         property="field_values",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="field_value", type="string", example="ABC123"),
     *                             @OA\Property(
     *                                 property="format_field",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="field_name", type="string", example="Number")
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $plates = LicensePlate::with([
            'city',
            'format',
            'fieldValues.formatField'
        ])->get();

        return response()->json([
            'license_plates' => $plates
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/license-plates/{id}",
     *     tags={"License Plates"},
     *     summary="Get a specific license plate",
     *     description="Retrieve a specific license plate by ID with its related data",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="License plate ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="License plate retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="license_plate",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="listing_id", type="integer", example=1),
     *                 @OA\Property(property="country_id", type="integer", example=1),
     *                 @OA\Property(property="city_id", type="integer", example=1),
     *                 @OA\Property(property="plate_format_id", type="integer", example=1),
     *                 @OA\Property(
     *                     property="city",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Paris")
     *                 ),
     *                 @OA\Property(
     *                     property="format",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Standard Format")
     *                 ),
     *                 @OA\Property(
     *                     property="field_values",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="field_value", type="string", example="ABC123"),
     *                         @OA\Property(
     *                             property="format_field",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="field_name", type="string", example="Number")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="License plate not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        $plate = LicensePlate::with([
            'city',
            'format',
            'fieldValues.formatField'
        ])->findOrFail($id);

        return response()->json([
            'license_plate' => $plate
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/cities/{cityId}/plate-formats",
     *     tags={"License Plates"},
     *     summary="Get plate formats by city",
     *     description="Retrieve plate formats for a specific city",
     *     @OA\Parameter(
     *         name="cityId",
     *         in="path",
     *         required=true,
     *         description="City ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Plate formats retrieved successfully"
     *     )
     * )
     */
    public function getFormatsByCity($cityId)
    {
        $city = City::findOrFail($cityId);
        $countryId = $city->country_id;

        $formats = PlateFormat::with(['country'])
            ->where('is_active', true)
            ->where(function ($query) use ($cityId, $countryId, $city) {
                if ($countryId == 1) {
                    $query->where('country_id', 1)->whereNull('city_id');
                } elseif ($countryId == 2) {
                    $isAbuDhabi = stripos($city->name, 'Abu Dhabi') !== false
                        || stripos($city->name_ar ?? '', 'أبو ظبي') !== false;
                    if ($isAbuDhabi) {
                        $query->where('country_id', 2)->where('city_id', $cityId);
                    } else {
                        $query->where('country_id', 2)->whereNull('city_id');
                    }
                } else {
                    $query->where(function ($q) use ($cityId, $countryId) {
                        $q->where('city_id', $cityId)
                            ->orWhere(function ($subQ) use ($countryId) {
                                $subQ->where('country_id', $countryId)->whereNull('city_id');
                            });
                    });
                }
            })
            ->get();

        return response()->json([
            'city' => ['id' => $city->id, 'name' => $city->name],
            'formats' => $formats
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/countries/{countryId}/plate-formats",
     *     tags={"License Plates"},
     *     summary="Get plate formats by country",
     *     description="Retrieve plate formats for a specific country",
     *     @OA\Parameter(
     *         name="countryId",
     *         in="path",
     *         required=true,
     *         description="Country ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Plate formats retrieved successfully"
     *     )
     * )
     */
    public function getFormatsByCountry($countryId)
    {
        $formats = PlateFormat::with(['country'])
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'country_id' => $countryId,
            'formats' => $formats
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/cities/{cityId}/plate-formats/details",
     *     tags={"License Plates"},
     *     summary="Get plate formats by city with details",
     *     description="Retrieve all plate formats for a specific city with detailed field information",
     *     @OA\Parameter(
     *         name="cityId",
     *         in="path",
     *         required=true,
     *         description="City ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Plate formats retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="city",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Paris")
     *             ),
     *             @OA\Property(
     *                 property="formats",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Standard Format"),
     *                     @OA\Property(property="country", type="string", example="France"),
     *                     @OA\Property(property="background_color", type="string", example="#FFFFFF"),
     *                     @OA\Property(property="text_color", type="string", example="#000000"),
     *                     @OA\Property(property="width_mm", type="number", format="float", example=520.0),
     *                     @OA\Property(property="height_mm", type="number", format="float", example=110.0),
     *                     @OA\Property(property="description", type="string", example="Standard French license plate"),
     *                     @OA\Property(property="fields_count", type="integer", example=3),
     *                     @OA\Property(
     *                         property="fields",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="field_name", type="string", example="Letters"),
     *                             @OA\Property(property="field_name_ar", type="string", example="أحرف"),
     *                             @OA\Property(property="position", type="string", example="left"),
     *                             @OA\Property(property="character_type", type="string", example="alphabetic"),
     *                             @OA\Property(property="writing_system", type="string", example="latin"),
     *                             @OA\Property(property="min_length", type="integer", example=2),
     *                             @OA\Property(property="max_length", type="integer", example=3),
     *                             @OA\Property(property="is_required", type="boolean", example=true),
     *                             @OA\Property(property="validation_pattern", type="string", example="^[A-Z]{2,3}$"),
     *                             @OA\Property(property="font_size", type="integer", example=12),
     *                             @OA\Property(property="is_bold", type="boolean", example=true),
     *                             @OA\Property(property="display_order", type="integer", example=1)
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="City not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model")
     *         )
     *     )
     * )
     */
    public function getFormatsByCityWithDetails($cityId)
    {
        // Récupérer la ville
        $city = City::findOrFail($cityId);
        $countryId = $city->country_id;

        // ✅ LOGIQUE DE FALLBACK SELON LE PAYS ET LA VILLE
        $formats = PlateFormat::with(['country', 'fields'])
            ->where('is_active', true)
            ->where(function ($query) use ($cityId, $countryId, $city) {
                // KSA (country_id = 1): Toujours retourner le format KSA (city_id = null)
                if ($countryId == 1) {
                    $query->where('country_id', 1)
                        ->whereNull('city_id');
                }
                // UAE (country_id = 2)
                elseif ($countryId == 2) {
                    // Vérifier si c'est Abu Dhabi
                    $isAbuDhabi = stripos($city->name, 'Abu Dhabi') !== false
                        || stripos($city->name_ar ?? '', 'أبو ظبي') !== false;

                    if ($isAbuDhabi) {
                        // Abu Dhabi: retourner le format spécifique à Abu Dhabi
                        $query->where('country_id', 2)
                            ->where('city_id', $cityId);
                    } else {
                        // Autres Emirates: retourner le format générique (city_id = null)
                        $query->where('country_id', 2)
                            ->whereNull('city_id');
                    }
                }
                // Autres pays: format spécifique à la ville ou fallback
                else {
                    $query->where(function ($q) use ($cityId, $countryId) {
                        $q->where('city_id', $cityId)
                            ->orWhere(function ($subQ) use ($countryId) {
                                $subQ->where('country_id', $countryId)
                                    ->whereNull('city_id');
                            });
                    });
                }
            })
            ->get();

        // Si aucun format trouvé, essayer de trouver un format générique pour le pays
        if ($formats->isEmpty()) {
            $formats = PlateFormat::with(['country', 'fields'])
                ->where('country_id', $countryId)
                ->whereNull('city_id')
                ->where('is_active', true)
                ->get();
        }

        $result = $formats->map(function ($format) {
            return [
                'id' => $format->id,
                'name' => $format->name,
                'country' => $format->country->name,
                'background_color' => $format->background_color,
                'text_color' => $format->text_color,
                'width_mm' => $format->width_mm,
                'height_mm' => $format->height_mm,
                'description' => $format->description,
                'fields_count' => $format->fields->count(),
                'fields' => $format->fields->sortBy('display_order')->map(function ($field) {
                    return [
                        'id' => $field->id,
                        'field_name' => $field->field_name,
                        'field_name_ar' => $field->field_name_ar,
                        'position' => $field->position, // Directement le champ string
                        'character_type' => $field->character_type,
                        'writing_system' => $field->writing_system,
                        'min_length' => $field->min_length,
                        'max_length' => $field->max_length,
                        'is_required' => $field->is_required,
                        'validation_pattern' => $field->validation_pattern,
                        'font_size' => $field->font_size,
                        'is_bold' => $field->is_bold,
                        'display_order' => $field->display_order,
                    ];
                })->values(),
            ];
        });

        return response()->json([
            'city' => [
                'id' => $city->id,
                'name' => $city->name,
            ],
            'formats' => $result,
        ]);
    }
}
