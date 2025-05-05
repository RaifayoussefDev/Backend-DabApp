<?php

namespace App\Http\Controllers;

use App\Models\Motorcycle;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use App\Models\MotorcycleType;
use App\Models\MotorcycleYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Tag(name="Motorcycles")
 */
class MotorcycleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/motorcycles",
     *     tags={"Motorcycles"},
     *     summary="Get all motorcycles",
     *     @OA\Response(response=200, description="List of motorcycles")
     * )
     */
    public function index()
    {
        return response()->json(Motorcycle::all());
    }

    /**
     * @OA\Post(
     *     path="/api/motorcycles",
     *     tags={"Motorcycles"},
     *     summary="Create a new motorcycle",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"brand_id", "model_id", "year_id", "color"},
     *             @OA\Property(property="brand_id", type="integer"),
     *             @OA\Property(property="model_id", type="integer"),
     *             @OA\Property(property="year_id", type="integer"),
     *             @OA\Property(property="color", type="string", example="Red")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Motorcycle created")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|exists:motorcycle_brands,id',
            'model_id' => 'required|exists:motorcycle_models,id',
            'year_id' => 'required|exists:motorcycle_years,id',
            'color' => 'required|string|max:255',
        ]);

        $motorcycle = Motorcycle::create($request->only('brand_id', 'model_id', 'year_id', 'color'));

        return response()->json($motorcycle, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/motorcycles/{id}",
     *     tags={"Motorcycles"},
     *     summary="Get a motorcycle by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Motorcycle data"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function show($id)
    {
        $motorcycle = Motorcycle::find($id);
        if (!$motorcycle) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json($motorcycle);
    }

    /**
     * @OA\Put(
     *     path="/api/motorcycles/{id}",
     *     tags={"Motorcycles"},
     *     summary="Update a motorcycle",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"brand_id", "model_id", "year_id", "color"},
     *             @OA\Property(property="brand_id", type="integer"),
     *             @OA\Property(property="model_id", type="integer"),
     *             @OA\Property(property="year_id", type="integer"),
     *             @OA\Property(property="color", type="string", example="Black")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Motorcycle updated"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function update(Request $request, $id)
    {
        $motorcycle = Motorcycle::find($id);
        if (!$motorcycle) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $request->validate([
            'brand_id' => 'required|exists:motorcycle_brands,id',
            'model_id' => 'required|exists:motorcycle_models,id',
            'year_id' => 'required|exists:motorcycle_years,id',
            'color' => 'required|string|max:255',
        ]);

        $motorcycle->update($request->only('brand_id', 'model_id', 'year_id', 'color'));

        return response()->json($motorcycle);
    }

    /**
     * @OA\Delete(
     *     path="/api/motorcycles/{id}",
     *     tags={"Motorcycles"},
     *     summary="Delete a motorcycle",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function destroy($id)
    {
        $motorcycle = Motorcycle::find($id);
        if (!$motorcycle) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $motorcycle->delete();

        return response()->json(null, 204);
    }


    public function importMotorcycles(Request $request)
    {
        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle); // skip header

        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            [$typeName, $brandName, $modelName, $yearValue] = $row;

            // 1. Insert or get type
            $type = MotorcycleType::firstOrCreate(['name' => $typeName]);

            // 2. Insert or get brand
            $brand = MotorcycleBrand::firstOrCreate(['name' => $brandName]);

            // 3. Insert or get model
            $model = MotorcycleModel::firstOrCreate([
                'name' => $modelName,
            ], [
                'brand_id' => $brand->id,
                'type_id' => $type->id
            ]);

            // 4. Insert year if not exists
            MotorcycleYear::firstOrCreate([
                'model_id' => $model->id,
                'year' => (int)$yearValue
            ]);
        }

        fclose($handle);

        return response()->json(['message' => 'Motorcycles imported successfully']);
    }
}
