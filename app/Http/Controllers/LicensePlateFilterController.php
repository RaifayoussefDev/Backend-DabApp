<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;

class LicensePlateFilterController extends Controller
{
    public function filter(Request $request)
    {
        $query = Listing::where('category_id', 3);

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->filled('countries')) {
            $query->whereHas('licensePlate', fn($q) => $q->whereIn('country_id', $request->countries));
        }

        if ($request->filled('cities')) {
            $query->whereHas('licensePlate', fn($q) => $q->whereIn('city_id', $request->cities));
        }

        if ($request->filled('plate_formats')) {
            $query->whereHas('licensePlate', fn($q) => $q->whereIn('plate_format_id', $request->plate_formats));
        }

        if ($request->filled('field_filters')) {
            foreach ($request->field_filters as $filter) {
                if (!empty($filter['field_id']) && isset($filter['value'])) {
                    $query->whereHas('licensePlate.fieldValues', function ($q) use ($filter) {
                        $q->where('plate_format_field_id', $filter['field_id'])
                          ->where('field_value', 'LIKE', '%' . $filter['value'] . '%');
                    });
                }
            }
        }

        if ($request->filled('digits_counts')) {
            $query->whereHas('licensePlate', fn($q) => $q->whereIn('digits_count', $request->digits_counts));
        }

        $results = $query->with([
            'images:id,listing_id,image_url',
            'licensePlate:id,listing_id,plate_format_id,country_id,city_id',
            'licensePlate.format:id,name,pattern,description',
            'licensePlate.country:id,name,code',
            'licensePlate.city:id,name',
            'licensePlate.fieldValues:id,license_plate_id,plate_format_field_id,field_value',
            'licensePlate.fieldValues.formatField:id,field_name,field_type,is_required'
        ])->select('id', 'title', 'description', 'price', 'created_at', 'status')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $results,
            'total' => $results->count()
        ]);
    }

    public function options()
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
                ->select('id', 'name', 'pattern', 'description')
                ->orderBy('name')
                ->get();

            $fields = DB::table('plate_format_fields')
                ->join('plate_formats', 'plate_format_fields.plate_format_id', '=', 'plate_formats.id')
                ->select(
                    'plate_format_fields.id',
                    'plate_format_fields.field_name',
                    'plate_format_fields.field_type',
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
                        'max' => $priceRange->max ?? 0
                    ]
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
