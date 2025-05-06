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
     * @OA\Get(
     *     path="/api/motorcycle/filter",
     *     tags={"Listings"},
     *     summary="Filter motorcycles by brand, model, year, or type",
     *     description="Returns filtered motorcycle brands, models, years, and types. If no query parameters are provided, returns all.",
     *     @OA\Parameter(
     *         name="brand_id",
     *         in="query",
     *         description="Filter by brand ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="model_id",
     *         in="query",
     *         description="Filter by model ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         description="Filter by year",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="type_id",
     *         in="query",
     *         description="Filter by type ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="brands", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="models", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="years", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="types", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */

    public function filter(Request $request)
    {
        $brandId = $request->input('brand_id');
        $modelId = $request->input('model_id');
        $year = $request->input('year');
        $typeId = $request->input('type_id');

        $brands = MotorcycleBrand::query();
        $models = MotorcycleModel::query();
        $years = MotorcycleYear::query();
        $types = MotorcycleType::query();

        // If any filter is applied
        if ($brandId || $modelId || $year || $typeId) {
            if ($brandId) {
                $models = $models->where('brand_id', $brandId);
            }

            if ($modelId) {
                $models = $models->where('id', $modelId);
            }

            if ($typeId) {
                $models = $models->where('type_id', $typeId);
            }

            $modelIds = $models->pluck('id');

            $brands = $brandId
                ? MotorcycleBrand::where('id', $brandId)
                : MotorcycleBrand::whereIn('id', MotorcycleModel::whereIn('id', $modelIds)->pluck('brand_id'));

            $types = $typeId
                ? MotorcycleType::where('id', $typeId)
                : MotorcycleType::whereIn('id', MotorcycleModel::whereIn('id', $modelIds)->pluck('type_id'));

            $years = $year
                ? MotorcycleYear::where('year', $year)->whereIn('model_id', $modelIds)
                : MotorcycleYear::whereIn('model_id', $modelIds);
        }

        // Return filtered or all
        return response()->json([
            'brands' => $brands->get(),
            'models' => $models->get(),
            'years' => $years->pluck('year')->unique()->values(),
            'types' => $types->get(),
        ]);
    }
}
