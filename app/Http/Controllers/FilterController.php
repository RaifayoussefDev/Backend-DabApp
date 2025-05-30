<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Listing;
use App\Models\Motorcycle;

class FilterController extends Controller
{
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
            ->with(['images' => function($query) {
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
            ->with(['images' => function($query) {
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

    public function filterLicensePlates(Request $request)
    {
        $query = Listing::where('category_id', 3);

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float)$request->min_price);
        }
        
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float)$request->max_price);
        }

        if ($request->filled('countries')) {
            $query->whereHas('licensePlate', function ($q) use ($request) {
                $q->whereIn('country_id', $request->countries);
            });
        }

        if ($request->filled('digits_counts')) {
            $query->whereHas('licensePlate', function ($q) use ($request) {
                $q->whereIn('digits_count', $request->digits_counts);
            });
        }

        // Récupérer les résultats avec seulement les champs nécessaires et la première image
        $licensePlates = $query->select('id', 'title', 'description', 'price')
            ->with(['images' => function($query) {
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

        return response()->json($licensePlates);
    }
}