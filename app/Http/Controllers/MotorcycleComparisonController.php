<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MotorcycleType;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use App\Models\MotorcycleYear;
use App\Models\MotorcycleDetail;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Motorcycles",
 *     description="Motorcycle comparison endpoints"
 * )
 */
class MotorcycleComparisonController extends Controller
{
    /**
     * @OA\Get(
     *     path="/comparison/motorcycles/types",
     *     tags={"Motorcycles Comparison"},
     *     summary="Get all motorcycle types",
     *     description="Returns the list of all available motorcycle types",
     *     @OA\Response(
     *         response=200,
     *         description="Types list retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Sport"),
     *                     @OA\Property(property="description", type="string", example="Sport motorcycles")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getTypes()
    {
        $types = MotorcycleType::select('id', 'name', 'description')->get();

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * @OA\Get(
     *     path="/comparison/motorcycles/brands",
     *     tags={"Motorcycles Comparison"},
     *     summary="Get all brands",
     *     description="Returns the list of all motorcycle brands",
     *     @OA\Response(
     *         response=200,
     *         description="Brands list retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Yamaha")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getBrands()
    {
        $brands = MotorcycleBrand::select('id', 'name')->get();

        return response()->json([
            'success' => true,
            'data' => $brands
        ]);
    }

    /**
     * @OA\Get(
     *     path="/comparison/motorcycles/models",
     *     tags={"Motorcycles Comparison"},
     *     summary="Get all models",
     *     description="Returns the list of all models, filtered by brand and/or type",
     *     @OA\Parameter(
     *         name="brand_id",
     *         in="query",
     *         description="Brand ID to filter models",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="type_id",
     *         in="query",
     *         description="Type ID to filter models",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Models list retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="brand_id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="YZF-R1"),
     *                     @OA\Property(property="type_id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="brand",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Yamaha")
     *                     ),
     *                     @OA\Property(
     *                         property="type",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Sport")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getModels(Request $request)
    {
        $query = MotorcycleModel::with(['brand:id,name', 'type:id,name']);

        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->has('type_id')) {
            $query->where('type_id', $request->type_id);
        }

        $models = $query->select('id', 'brand_id', 'name', 'type_id')->get();

        return response()->json([
            'success' => true,
            'data' => $models
        ]);
    }

    /**
     * @OA\Get(
     *     path="/comparison/motorcycles/years",
     *     tags={"Motorcycles Comparison"},
     *     summary="Get all years for a model",
     *     description="Returns the list of all available years for a specific model",
     *     @OA\Parameter(
     *         name="model_id",
     *         in="query",
     *         description="Model ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Years list retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="model_id", type="integer", example=1),
     *                     @OA\Property(property="year", type="integer", example=2023)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function getYears(Request $request)
    {
        $request->validate([
            'model_id' => 'required|exists:motorcycle_models,id'
        ]);

        $years = MotorcycleYear::where('model_id', $request->model_id)
            ->select('id', 'model_id', 'year')
            ->orderBy('year', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $years
        ]);
    }

    /**
     * @OA\Get(
     *     path="/comparison/motorcycles/details/{yearId}",
     *     tags={"Motorcycles Comparison"},
     *     summary="Get complete motorcycle details",
     *     description="Returns all detailed information about a specific motorcycle",
     *     @OA\Parameter(
     *         name="yearId",
     *         in="path",
     *         description="Motorcycle year ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Motorcycle details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="info",
     *                     type="object",
     *                     @OA\Property(property="type", type="string", example="Sport"),
     *                     @OA\Property(property="brand", type="string", example="Yamaha"),
     *                     @OA\Property(property="model", type="string", example="YZF-R1"),
     *                     @OA\Property(property="year", type="integer", example=2023)
     *                 ),
     *                 @OA\Property(
     *                     property="details",
     *                     type="object",
     *                     @OA\Property(property="displacement", type="string", example="998cc"),
     *                     @OA\Property(property="engine_type", type="string", example="4-cylinder"),
     *                     @OA\Property(property="power", type="string", example="200hp"),
     *                     @OA\Property(property="torque", type="string", example="112.4Nm"),
     *                     @OA\Property(property="top_speed", type="string", example="299 km/h"),
     *                     @OA\Property(property="dry_weight", type="string", example="199kg"),
     *                     @OA\Property(property="price", type="number", format="float", example=17999.99)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Motorcycle not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Motorcycle details not found")
     *         )
     *     )
     * )
     */
    public function getMotorcycleDetails($yearId)
    {
        $details = MotorcycleDetail::with([
            'year.model.brand:id,name',
            'year.model.type:id,name',
            'year.model:id,brand_id,name,type_id',
            'year:id,model_id,year'
        ])
        ->where('year_id', $yearId)
        ->first();

        if (!$details) {
            return response()->json([
                'success' => false,
                'message' => 'Motorcycle details not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'info' => [
                    'type' => $details->year->model->type->name,
                    'brand' => $details->year->model->brand->name,
                    'model' => $details->year->model->name,
                    'year' => $details->year->year,
                ],
                'details' => $details
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/comparison/motorcycles/compare",
     *     tags={"Motorcycles Comparison"},
     *     summary="Compare two motorcycles",
     *     description="Compares the complete details of two motorcycles side by side",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"year_id_1", "year_id_2"},
     *             @OA\Property(property="year_id_1", type="integer", example=1, description="Year ID of the first motorcycle"),
     *             @OA\Property(property="year_id_2", type="integer", example=2, description="Year ID of the second motorcycle")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Comparison performed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="motorcycle_1",
     *                     type="object",
     *                     @OA\Property(
     *                         property="info",
     *                         type="object",
     *                         @OA\Property(property="type", type="string", example="Sport"),
     *                         @OA\Property(property="brand", type="string", example="Yamaha"),
     *                         @OA\Property(property="model", type="string", example="YZF-R1"),
     *                         @OA\Property(property="year", type="integer", example=2023)
     *                     ),
     *                     @OA\Property(property="details", type="object")
     *                 ),
     *                 @OA\Property(
     *                     property="motorcycle_2",
     *                     type="object",
     *                     @OA\Property(
     *                         property="info",
     *                         type="object",
     *                         @OA\Property(property="type", type="string", example="Sport"),
     *                         @OA\Property(property="brand", type="string", example="Honda"),
     *                         @OA\Property(property="model", type="string", example="CBR1000RR"),
     *                         @OA\Property(property="year", type="integer", example=2023)
     *                     ),
     *                     @OA\Property(property="details", type="object")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="One or more motorcycles not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="One or more motorcycles not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function compare(Request $request)
    {
        $request->validate([
            'year_id_1' => 'required|exists:motorcycle_years,id',
            'year_id_2' => 'required|exists:motorcycle_years,id'
        ]);

        $motorcycle1 = MotorcycleDetail::with([
            'year.model.brand:id,name',
            'year.model.type:id,name',
            'year.model:id,brand_id,name,type_id',
            'year:id,model_id,year'
        ])
        ->where('year_id', $request->year_id_1)
        ->first();

        $motorcycle2 = MotorcycleDetail::with([
            'year.model.brand:id,name',
            'year.model.type:id,name',
            'year.model:id,brand_id,name,type_id',
            'year:id,model_id,year'
        ])
        ->where('year_id', $request->year_id_2)
        ->first();

        if (!$motorcycle1 || !$motorcycle2) {
            return response()->json([
                'success' => false,
                'message' => 'One or more motorcycles not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'motorcycle_1' => [
                    'info' => [
                        'type' => $motorcycle1->year->model->type->name,
                        'brand' => $motorcycle1->year->model->brand->name,
                        'model' => $motorcycle1->year->model->name,
                        'year' => $motorcycle1->year->year,
                    ],
                    'details' => $motorcycle1
                ],
                'motorcycle_2' => [
                    'info' => [
                        'type' => $motorcycle2->year->model->type->name,
                        'brand' => $motorcycle2->year->model->brand->name,
                        'model' => $motorcycle2->year->model->name,
                        'year' => $motorcycle2->year->year,
                    ],
                    'details' => $motorcycle2
                ]
            ]
        ]);
    }
}
