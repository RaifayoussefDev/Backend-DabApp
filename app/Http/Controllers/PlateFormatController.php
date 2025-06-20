<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PlateFormat;
use Illuminate\Http\Request;

class PlateFormatController extends Controller
{
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

            // Champs supplémentaires avec valeurs numériques ou booléennes
            'fields.*.min_length' => 'nullable|integer|min:1',
            'fields.*.max_length' => 'nullable|integer|min:1',
            'fields.*.font_size' => 'nullable|integer|min:8',
            'fields.*.is_bold' => 'nullable|boolean',
            'fields.*.is_required' => 'nullable|boolean',
            'fields.*.display_order' => 'nullable|integer|min:0',
            'fields.*.validation_pattern' => 'nullable|string',
        ]);

        $format = PlateFormat::create($request->only([
            'name', 'country_id', 'city_id',
            'background_color', 'text_color',
            'width_mm', 'height_mm', 'description'
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
            'message' => 'Format créé avec succès',
            'format' => $format->load('fields')
        ], 201);
    }
}

