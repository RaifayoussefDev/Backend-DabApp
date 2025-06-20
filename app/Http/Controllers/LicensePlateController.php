<?php

namespace App\Http\Controllers;

use App\Models\LicensePlate;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\PlateFormat;
use Illuminate\Http\Request;

class LicensePlateController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'listing_id' => 'required|exists:listings,id',
            'characters' => 'required|string|unique:license_plates,characters',
            'country_id' => 'required|exists:countries,id',
            'city_id' => 'required|exists:cities,id',
            'type_id' => 'required|exists:plate_types,id',
            'digits_count' => 'required|integer',
            'first_letter' => 'nullable|string|max:1',
            'second_letter' => 'nullable|string|max:1',
            'third_letter' => 'nullable|string|max:1',
            'numbers' => 'required|string'
        ]);

        $plate = LicensePlate::create($request->all());

        return response()->json([
            'message' => 'License plate created successfully',
            'data' => $plate
        ], 201);
    }

    public function showFormatted($id)
    {
        // CORRECTION: Retirez .position du with()
        $plate = LicensePlate::with(['format.fields', 'fieldValues'])->findOrFail($id);

        $fields = $plate->format->fields->sortBy('display_order');
        $values = $plate->fieldValues->keyBy('plate_format_field_id');

        $result = [];

        foreach ($fields as $field) {
            $value = $values[$field->id]->field_value ?? '';
            $result[] = [
                'field' => $field->field_name,
                'value' => $value,
                'position' => $field->position, // position est un champ string, pas une relation
            ];
        }

        return response()->json([
            'plate_id' => $plate->id,
            'country' => $plate->country->name,
            'formatted_fields' => $result,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(LicensePlate $licensePlate)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LicensePlate $licensePlate)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LicensePlate $licensePlate)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LicensePlate $licensePlate)
    {
        //
    }

    public function getFormatsByCity($cityId)
    {
        // CORRECTION: Retirez .position du with()
        $formats = PlateFormat::with(['country', 'city', 'fields'])
            ->where('city_id', $cityId)
            ->where('is_active', true)
            ->get();

        if ($formats->isEmpty()) {
            return response()->json([
                'message' => 'Aucun format trouvé pour cette ville'
            ], 404);
        }

        $result = $formats->map(function ($format) {
            return [
                'id' => $format->id,
                'name' => $format->name,
                'country' => $format->country->name,
                'city' => $format->city ? $format->city->name : null,
                'background_color' => $format->background_color,
                'text_color' => $format->text_color,
                'width_mm' => $format->width_mm,
                'height_mm' => $format->height_mm,
                'description' => $format->description,
                'fields' => $format->fields->sortBy('display_order')->map(function ($field) {
                    return [
                        'id' => $field->id,
                        'field_name' => $field->field_name,
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
            'city_id' => $cityId,
            'formats' => $result,
        ]);
    }

    // Alternative : Si vous voulez aussi récupérer les informations de la ville
    public function getFormatsByCityWithDetails($cityId)
    {
        // Récupérer la ville
        $city = City::findOrFail($cityId);

        // Récupérer les formats de cette ville
        $formats = PlateFormat::with(['country', 'fields'])
            ->where('city_id', $cityId)
            ->where('is_active', true)
            ->get();

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
                    // Déduire le input_type en fonction du character_type
                    $input_type = 'text'; // par défaut

                    if ($field->character_type === 'digit') {
                        $input_type = 'number';
                    }

                    return [
                        'id' => $field->id,
                        'field_name' => $field->field_name,
                        'position' => $field->position,
                        'character_type' => $field->character_type,
                        'writing_system' => $field->writing_system,
                        'min_length' => $field->min_length,
                        'max_length' => $field->max_length,
                        'is_required' => $field->is_required,
                        'validation_pattern' => $field->validation_pattern,
                        'font_size' => $field->font_size,
                        'is_bold' => $field->is_bold,
                        'display_order' => $field->display_order,

                        // Nouveaux champs pour le front
                        'input_type' => $input_type,
                        'maxlength' => $field->max_length,
                    ];
                })
                ->values(),
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

    // Méthode pour récupérer tous les formats par pays avec leurs villes
    public function getFormatsByCountry($countryId)
    {
        $formats = PlateFormat::with(['country', 'city', 'fields'])
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->get()
            ->groupBy('city_id');

        $result = [];
        foreach ($formats as $cityId => $cityFormats) {
            $cityName = $cityFormats->first()->city ? $cityFormats->first()->city->name : 'National';

            $result[] = [
                'city_id' => $cityId,
                'city_name' => $cityName,
                'formats' => $cityFormats->map(function ($format) {
                    return [
                        'id' => $format->id,
                        'name' => $format->name,
                        'description' => $format->description,
                        'fields_count' => $format->fields->count(),
                    ];
                })->values(),
            ];
        }

        return response()->json([
            'country_id' => $countryId,
            'cities_with_formats' => $result,
        ]);
    }
}
