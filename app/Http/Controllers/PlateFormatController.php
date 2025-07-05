<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\PlateFormat;
use Illuminate\Http\Request;

class PlateFormatController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/plate-formats",
     *     summary="Créer un nouveau format de plaque",
     *     tags={"Plate Formats"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "country_id", "fields"},
     *             @OA\Property(property="name", type="string", example="Ajman Motorcycle Plate"),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="city_id", type="integer", nullable=true, example=13),
     *             @OA\Property(property="background_color", type="string", example="#FFFFFF"),
     *             @OA\Property(property="text_color", type="string", example="#000000"),
     *             @OA\Property(property="width_mm", type="integer", example=250),
     *             @OA\Property(property="height_mm", type="integer", example=130),
     *             @OA\Property(property="description", type="string", example="Plaque moto Ajman réelle : chiffres (1‑5) en haut centre, lettre latine en bas centre"),
     *             @OA\Property(
     *                 property="fields",
     *                 type="array",
     *                 @OA\Items(
     *                     required={"field_name", "position", "character_type", "writing_system"},
     *                     @OA\Property(property="field_name", type="string", example="number"),
     *                     @OA\Property(property="position", type="string", example="top-center"),
     *                     @OA\Property(property="character_type", type="string", example="digit"),
     *                     @OA\Property(property="writing_system", type="string", example="latin"),
     *                     @OA\Property(property="min_length", type="integer", example=1),
     *                     @OA\Property(property="max_length", type="integer", example=5),
     *                     @OA\Property(property="font_size", type="integer", example=14),
     *                     @OA\Property(property="is_bold", type="boolean", example=true),
     *                     @OA\Property(property="is_required", type="boolean", example=true),
     *                     @OA\Property(property="display_order", type="integer", example=1),
     *                     @OA\Property(property="validation_pattern", type="string", example="^[0-9]+$")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Format créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Format créé avec succès"),
     *             @OA\Property(property="format", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation"
     *     )
     * )
     */

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'country_id' => 'required|exists:countries,id',
            'city_id' => 'nullable|exists:cities,id',
            'background_color' => 'nullable|string',
            'text_color' => 'nullable|string',
            'width_mm' => 'nullable|integer',
            'height_mm' => 'nullable|integer',
            'description' => 'nullable|string',

            'fields' => 'required|array|min:1',
            'fields.*.field_name' => 'required|string',
            'fields.*.position' => 'required|string',
            'fields.*.character_type' => 'required|string',
            'fields.*.writing_system' => 'required|string',
            'fields.*.min_length' => 'nullable|integer|min:1',
            'fields.*.max_length' => 'nullable|integer|min:1',
            'fields.*.font_size' => 'nullable|integer|min:8',
            'fields.*.is_bold' => 'nullable|boolean',
            'fields.*.is_required' => 'nullable|boolean',
            'fields.*.display_order' => 'nullable|integer|min:0',
            'fields.*.validation_pattern' => 'nullable|string',
        ]);

        // 🚫 Vérifie si la ville existe
        if ($data['city_id']) {
            $city = City::find($data['city_id']);
            if (!$city) {
                return response()->json([
                    'message' => 'City does not exist.'
                ], 404);
            }
        }

        // 🚫 Vérifie si un format existe déjà pour cette ville (et éventuellement ce nom)
        $existingFormat = PlateFormat::where('city_id', $data['city_id'])
            ->first();

        if ($existingFormat) {
            return response()->json([
                'message' => 'Format already exists for this city.',
                'existing_format' => $existingFormat->load('fields')
            ], 409); // HTTP 409 Conflict
        }

        // ✅ Si country_id = 1 (Arabie Saoudite), créer le format pour toutes les villes saoudiennes
        if ($data['country_id'] == 1) {
            // Récupérer toutes les villes d'Arabie Saoudite
            $saudiCities = City::where('country_id', 1)->get();

            $createdFormats = [];

            foreach ($saudiCities as $city) {
                // Vérifier si un format existe déjà pour cette ville
                $existingCityFormat = PlateFormat::where('city_id', $city->id)
                    ->where('name', $data['name'])
                    ->first();

                if (!$existingCityFormat) {
                    // Créer le format pour cette ville
                    $format = PlateFormat::create([
                        'name' => $data['name'],
                        'country_id' => $data['country_id'],
                        'city_id' => $city->id,
                        'background_color' => $data['background_color'],
                        'text_color' => $data['text_color'],
                        'width_mm' => $data['width_mm'],
                        'height_mm' => $data['height_mm'],
                        'description' => $data['description']
                    ]);

                    // Créer les champs pour ce format
                    foreach ($data['fields'] as $field) {
                        $format->fields()->create([
                            'field_name' => $field['field_name'],
                            'position' => $field['position'],
                            'character_type' => $field['character_type'],
                            'writing_system' => $field['writing_system'],
                            'min_length' => $field['min_length'] ?? 1,
                            'max_length' => $field['max_length'] ?? 1,
                            'font_size' => $field['font_size'] ?? 14,
                            'is_bold' => $field['is_bold'] ?? false,
                            'is_required' => $field['is_required'] ?? true,
                            'display_order' => $field['display_order'] ?? 0,
                            'validation_pattern' => $field['validation_pattern'] ?? null,
                        ]);
                    }

                    $createdFormats[] = $format->load('fields');
                }
            }

            return response()->json([
                'message' => 'Formats created successfully for all Saudi cities',
                'formats' => $createdFormats,
                'total_created' => count($createdFormats)
            ], 201);
        }

        // ✅ Création du format normal pour les autres pays
        $format = PlateFormat::create($request->only([
            'name',
            'country_id',
            'city_id',
            'background_color',
            'text_color',
            'width_mm',
            'height_mm',
            'description'
        ]));

        foreach ($data['fields'] as $field) {
            $format->fields()->create([
                'field_name' => $field['field_name'],
                'position' => $field['position'],
                'character_type' => $field['character_type'],
                'writing_system' => $field['writing_system'],
                'min_length' => $field['min_length'] ?? 1,
                'max_length' => $field['max_length'] ?? 1,
                'font_size' => $field['font_size'] ?? 14,
                'is_bold' => $field['is_bold'] ?? false,
                'is_required' => $field['is_required'] ?? true,
                'display_order' => $field['display_order'] ?? 0,
                'validation_pattern' => $field['validation_pattern'] ?? null,
            ]);
        }

        return response()->json([
            'message' => 'Format created successfully',
            'format' => $format->load('fields')
        ], 201);
    }
}
