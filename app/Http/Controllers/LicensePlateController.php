<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LicensePlate;
use App\Models\LicensePlateValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LicensePlateController extends Controller
{
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
}
