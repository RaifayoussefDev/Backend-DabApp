<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use App\Models\MotorcycleType;
use App\Models\MotorcycleYear;
use Illuminate\Support\Facades\Cache;

class MotorcycleFilterController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/motorcycle/brands",
     *     summary="Get all active motorcycle brands",
     *     tags={"Motorcycle"},
     *     @OA\Response(
     *         response=200,
     *         description="List of active brands",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Honda"),
     *                     @OA\Property(property="is_displayed", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getBrands()
    {
        // Cache pendant 1 heure car les marques changent rarement
        $brands = Cache::remember('motorcycle_brands_displayed', 3600, function () {
            return MotorcycleBrand::select('id', 'name', 'is_displayed')
                ->where('is_displayed', true) // Seulement les marques à afficher
                ->orderBy('name')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $brands
        ]);
    }

    /**
     * Get all brands including hidden ones (for admin)
     */
    /**
     *
     * @OA\Get(
     *     path="/api/motorcycle/brands/all",
     *     summary="Get all motorcycle brands (including hidden)",
     *     tags={"Motorcycle"},
     *     @OA\Response(
     *         response=200,
     *         description="List of all brands",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Honda"),
     *                     @OA\Property(property="is_displayed", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getAllBrands()
    {
        $brands = Cache::remember('motorcycle_brands_all', 3600, function () {
            return MotorcycleBrand::select('id', 'name', 'is_displayed')
                ->orderBy('name')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $brands
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/motorcycle/models/{brandId}",
     *     summary="Get models by brand ID",
     *     tags={"Motorcycle"},
     *     @OA\Parameter(
     *         name="brandId",
     *         in="path",
     *         required=true,
     *         description="Brand ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of models for the brand",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="CBR500R"),
     *                     @OA\Property(property="brand_id", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getModelsByBrand($brandId)
    {
        // Validation rapide
        if (!MotorcycleBrand::where('id', $brandId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        // Cache pendant 30 minutes par marque
        $models = Cache::remember("motorcycle_models_brand_{$brandId}", 1800, function () use ($brandId) {
            return MotorcycleModel::select('id', 'name', 'brand_id')
                ->where('brand_id', $brandId)
                ->orderBy('name')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $models
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/motorcycle/years/{modelId}",
     *     summary="Get years by model ID",
     *     tags={"Motorcycle"},
     *     @OA\Parameter(
     *         name="modelId",
     *         in="path",
     *         required=true,
     *         description="Model ID",
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of years for the model",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="year", type="integer", example=2022),
     *                     @OA\Property(property="model_id", type="integer", example=2)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getYearsByModel($modelId)
    {
        // Validation rapide
        if (!MotorcycleModel::where('id', $modelId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Model not found'
            ], 404);
        }

        // Cache pendant 15 minutes par modèle
        $years = Cache::remember("motorcycle_years_model_{$modelId}", 900, function () use ($modelId) {
            return MotorcycleYear::select('id', 'year', 'model_id')
                ->where('model_id', $modelId)
                ->orderBy('year', 'desc') // Années récentes en premier
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $years
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/motorcycle/years-by-brand/{brandId}",
     *     summary="Get distinct years for a brand",
     *     tags={"Motorcycle"},
     *     @OA\Parameter(
     *         name="brandId",
     *         in="path",
     *         required=true,
     *         description="Brand ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of available years for the brand",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="integer", example=2024)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Brand not found")
     * )
     */
    public function getYearsByBrand($brandId)
    {
        // Validation rapide
        if (!MotorcycleBrand::where('id', $brandId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        // Cache pendant 15 minutes
        $years = Cache::remember("motorcycle_years_brand_{$brandId}", 900, function () use ($brandId) {
            return MotorcycleYear::join('motorcycle_models', 'motorcycle_years.model_id', '=', 'motorcycle_models.id')
                ->where('motorcycle_models.brand_id', $brandId)
                ->select('motorcycle_years.year')
                ->distinct()
                ->orderBy('motorcycle_years.year', 'desc')
                ->pluck('motorcycle_years.year');
        });

        return response()->json([
            'success' => true,
            'data' => $years
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/motorcycle/years-by-brand-mbl/{brandId}",
     *     summary="Get distinct years for a brand (formatted for Mobile)",
     *     tags={"Motorcycle"},
     *     @OA\Parameter(
     *         name="brandId",
     *         in="path",
     *         required=true,
     *         description="Brand ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of available years for the brand with year_ids",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="year", type="integer", example=2024),
     *                     @OA\Property(property="year_id", type="integer", example=2024),
     *                     @OA\Property(property="year_ids", type="array", @OA\Items(type="integer"))
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Brand not found")
     * )
     */
    public function getYearsByBrandMbl($brandId)
    {
        // Validation rapide
        if (!MotorcycleBrand::where('id', $brandId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        // Cache pendant 15 minutes
        $years = Cache::remember("motorcycle_years_brand_{$brandId}_mbl", 900, function () use ($brandId) {
            $yearsData = MotorcycleYear::join('motorcycle_models', 'motorcycle_years.model_id', '=', 'motorcycle_models.id')
                ->where('motorcycle_models.brand_id', $brandId)
                ->select('motorcycle_years.id', 'motorcycle_years.year')
                ->orderBy('motorcycle_years.year', 'desc')
                ->get();

            return $yearsData->groupBy('year')->map(function ($items, $year) {
                return [
                    'year' => (int) $year,
                    'year_id' => (int) $year, // Keep for compatibility if needed, though mostly symbolic now
                    'year_ids' => $items->pluck('id')->values()->all()
                ];
            })->values();
        });

        return response()->json([
            'success' => true,
            'data' => $years
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/motorcycle/models-by-year/{brandId}/{year}",
     *     summary="Get models by brand and year",
     *     tags={"Motorcycle"},
     *     @OA\Parameter(
     *         name="brandId",
     *         in="path",
     *         required=true,
     *         description="Brand ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="year",
     *         in="path",
     *         required=true,
     *         description="Year (e.g. 2024)",
     *         @OA\Schema(type="integer", example=2024)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of models for the brand and year with specific year_id",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=142),
     *                     @OA\Property(property="name", type="string", example="CBR1000RR"),
     *                     @OA\Property(property="brand_id", type="integer", example=1),
     *                     @OA\Property(property="year_id", type="integer", example=501)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Brand not found")
     * )
     */
    public function getModelsByBrandAndYear($brandId, $year)
    {
        // Validation rapide
        if (!MotorcycleBrand::where('id', $brandId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        // Cache pendant 15 minutes
        $models = Cache::remember("motorcycle_models_brand_{$brandId}_year_{$year}", 900, function () use ($brandId, $year) {
            return MotorcycleModel::join('motorcycle_years', 'motorcycle_models.id', '=', 'motorcycle_years.model_id')
                ->where('motorcycle_models.brand_id', $brandId)
                ->where('motorcycle_years.year', $year)
                ->select(
                    'motorcycle_models.id',
                    'motorcycle_models.name',
                    'motorcycle_models.brand_id',
                    'motorcycle_years.id as year_id'
                )
                ->orderBy('motorcycle_models.name')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $models
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/motorcycle/details/{yearId}",
     *     summary="Get complete details by year ID",
     *     tags={"Motorcycle"},
     *     @OA\Parameter(
     *         name="yearId",
     *         in="path",
     *         required=true,
     *         description="Year ID",
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Complete motorcycle details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="year_id", type="integer", example=3),
     *                 @OA\Property(property="year", type="integer", example=2022),
     *                 @OA\Property(property="model_id", type="integer", example=2),
     *                 @OA\Property(property="model_name", type="string", example="CBR500R"),
     *                 @OA\Property(property="brand_id", type="integer", example=1),
     *                 @OA\Property(property="brand_name", type="string", example="Honda")
     *             )
     *         )
     *     )
     * )
     */
    public function getDetailsByYear($yearId)
    {
        // Cache pendant 15 minutes
        $details = Cache::remember("motorcycle_details_year_{$yearId}", 900, function () use ($yearId) {
            return MotorcycleYear::select('id', 'year', 'model_id')
                ->with([
                    'model:id,name,brand_id',
                    'model.brand:id,name'
                ])
                ->where('id', $yearId)
                ->first();
        });

        if (!$details) {
            return response()->json([
                'success' => false,
                'message' => 'Year not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'year_id' => $details->id,
                'year' => $details->year,
                'model_id' => $details->model_id,
                'model_name' => $details->model->name,
                'brand_id' => $details->model->brand_id,
                'brand_name' => $details->model->brand->name,
            ]
        ]);
    }

    // Méthode utilitaire pour vider le cache si nécessaire
    public function clearCache()
    {
        Cache::forget('motorcycle_brands');
        // Pattern pour effacer tous les caches de modèles et années
        $keys = Cache::getRedis()->keys('laravel_cache:motorcycle_*');
        foreach ($keys as $key) {
            Cache::getRedis()->del($key);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cache cleared'
        ]);
    }
}
