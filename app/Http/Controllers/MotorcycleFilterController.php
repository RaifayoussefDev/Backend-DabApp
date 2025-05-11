<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use App\Models\MotorcycleType;
use App\Models\MotorcycleYear;

class MotorcycleFilterController extends Controller
{
    /**
     * Filter motorcycles with combined model/year format
     */
    public function filter(Request $request)
    {
        $request->validate([
            'brand_id' => 'nullable|integer|exists:motorcycle_brands,id',
            'model_id' => 'nullable|integer|exists:motorcycle_models,id',
            'year' => 'nullable|integer',
        ]);
    
        $brandId = $request->input('brand_id');
        $modelId = $request->input('model_id');
        $year = $request->input('year');
    
        // Get base queries
        $brands = MotorcycleBrand::all();
    
        $modelsQuery = MotorcycleModel::with('brand');
        $yearsQuery = MotorcycleYear::with('model.brand');
    
        // Apply filters
        if ($brandId) {
            $modelsQuery->where('brand_id', $brandId);
            $yearsQuery->whereHas('model', fn($q) => $q->where('brand_id', $brandId));
        }
    
        if ($modelId) {
            $modelsQuery->where('id', $modelId);
            $yearsQuery->where('model_id', $modelId);
        }
    
        if ($year) {
            $yearsQuery->where('year', $year);
        }
    
        // Fetch models and remove duplicate combinations
        $models = $modelsQuery->get()->unique('id')->map(function ($model) {
            return [
                'id' => $model->id,
                'name' => $model->name,
                'brand_id' => $model->brand_id,
                'brand_name' => $model->brand->name
            ];
        });
    
        $years = $yearsQuery->get()->unique(fn ($item) => $item->year . '-' . $item->model_id)->map(function ($year) {
            return [
                'id' => $year->id,
                'year' => $year->year,
                'model_id' => $year->model_id,
                'model_name' => $year->model->name,
                'brand_id' => $year->model->brand_id,
                'brand_name' => $year->model->brand->name
            ];
        });
    
        return response()->json([
            'success' => true,
            'data' => [
                'brands' => $brands,
                'models' => $models,
                'years' => $years,
            ]
        ]);
    }
    
    /**
     * Get all models with years by brand
     */
    public function getByBrand($brandId)
    {
        $modelsWithYears = MotorcycleYear::whereHas('model', function ($q) use ($brandId) {
            $q->where('brand_id', $brandId);
        })
            ->with('model.brand', 'model.type')
            ->get()
            ->map(function ($year) {
                return [
                    'id' => $year->id,
                    'display' => $year->model->name . ' / ' . $year->year,
                    'model_id' => $year->model_id,
                    'year_id' => $year->id,
                    'model_name' => $year->model->name,
                    'brand_name' => $year->model->brand->name,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $modelsWithYears
        ]);
    }

    /**
     * Get brand and model details by year ID
     */
    public function getByYear($yearId)
    {
        $year = MotorcycleYear::with('model.brand')
            ->findOrFail($yearId);

        return response()->json([
            'success' => true,
            'data' => [
                'year_id' => $year->id,
                'year' => $year->year,
                'model_id' => $year->model_id,
                'model_name' => $year->model->name,
                'brand_id' => $year->model->brand_id,
                'brand_name' => $year->model->brand->name,
            ]
        ]);
    }
}
