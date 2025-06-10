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
            'name' => 'required',
            'country_id' => 'required|exists:countries,id',
            'city_id' => 'nullable|exists:cities,id',
            'fields' => 'required|array|min:1',
            'fields.*.field_name' => 'required|string',
            'fields.*.position' => 'required|string',
            'fields.*.character_type' => 'required|string',
            'fields.*.writing_system' => 'required|string',
        ]);

        $format = PlateFormat::create($request->only([
            'name', 'country_id', 'city_id',
            'background_color', 'text_color',
            'width_mm', 'height_mm', 'description'
        ]));

        foreach ($data['fields'] as $field) {
            $format->fields()->create($field);
        }

        return response()->json([
            'message' => 'Format créé avec succès',
            'format' => $format->load('fields')
        ], 201);
    }
}

