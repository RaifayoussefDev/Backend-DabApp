<?php

namespace App\Http\Controllers;

use App\Models\MotorcycleType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(name="Motorcycle Types")
 */
class MotorcycleTypeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/motorcycle-types",
     *     tags={"Motorcycle Types"},
     *     summary="Get all motorcycle types",
     *     @OA\Response(
     *         response=200,
     *         description="List of motorcycle types or no data",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Motorcycle types retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Sport"),
     *                     @OA\Property(property="name_ar", type="string", example="رياضية"),
     *                     @OA\Property(property="icon", type="string", example="https://api.dabapp.co/storage/motorcycle_types/icon.png"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T12:00:00Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T12:00:00Z")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $types = MotorcycleType::all();

        if ($types->isEmpty()) {
            return response()->json([
                'message' => 'No motorcycle types found.',
                'data' => []
            ], 200);
        }

        return response()->json([
            'message' => 'Motorcycle types retrieved successfully.',
            'data' => $types
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/motorcycle-types",
     *     tags={"Motorcycle Types"},
     *     summary="Create a new motorcycle type",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name"},
     *                 @OA\Property(property="name", type="string", example="Sport"),
     *                 @OA\Property(property="name_ar", type="string", example="رياضية"),
     *                 @OA\Property(property="icon", type="string", format="binary", description="Motorcycle type icon image")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Motorcycle type created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Motorcycle type created successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Sport"),
     *                 @OA\Property(property="name_ar", type="string", example="رياضية"),
     *                 @OA\Property(property="icon", type="string", example="https://api.dabapp.co/storage/motorcycle_types/icon.png"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T12:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T12:00:00Z")
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:motorcycle_types',
            'name_ar' => 'nullable|string',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);

        $data = $request->only(['name', 'name_ar']);

        if ($request->hasFile('icon')) {
            $path = $request->file('icon')->store('motorcycle_types', 'public');
            $data['icon'] = config('app.url') . '/storage/' . $path;
        }

        $type = MotorcycleType::create($data);

        return response()->json([
            'message' => 'Motorcycle type created successfully.',
            'data' => $type
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/motorcycle-types/{id}",
     *     tags={"Motorcycle Types"},
     *     summary="Get a motorcycle type by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Motorcycle type data",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Motorcycle type retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Sport"),
     *                 @OA\Property(property="name_ar", type="string", example="رياضية"),
     *                 @OA\Property(property="icon", type="string", example="https://api.dabapp.co/storage/motorcycle_types/icon.png"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T12:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T12:00:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function show($id)
    {
        $type = MotorcycleType::find($id);

        if (!$type) {
            return response()->json(['message' => 'Motorcycle type not found.'], 404);
        }

        return response()->json([
            'message' => 'Motorcycle type retrieved successfully.',
            'data' => $type
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/motorcycle-types/{id}",
     *     tags={"Motorcycle Types"},
     *     summary="Update a motorcycle type (Use POST with _method=PUT for file upload)",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="_method", type="string", example="PUT", description="Required for updating with files"),
     *                 @OA\Property(property="name", type="string", example="Cruiser"),
     *                 @OA\Property(property="name_ar", type="string", example="كروزر"),
     *                 @OA\Property(property="icon", type="string", format="binary", description="Motorcycle type icon image")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Motorcycle type updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Motorcycle type updated successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Cruiser"),
     *                 @OA\Property(property="name_ar", type="string", example="كروزر"),
     *                 @OA\Property(property="icon", type="string", example="https://api.dabapp.co/storage/motorcycle_types/icon.png"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T12:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T12:00:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function update(Request $request, $id)
    {
        $type = MotorcycleType::find($id);

        if (!$type) {
            return response()->json(['message' => 'Motorcycle type not found.'], 404);
        }

        $request->validate([
            'name' => 'required|unique:motorcycle_types,name,' . $id,
            'name_ar' => 'nullable|string',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);

        $data = $request->only(['name', 'name_ar']);

        if ($request->hasFile('icon')) {
            // Delete old icon if exists
            if ($type->icon) {
                $oldPath = str_replace(config('app.url') . '/storage/', '', $type->icon);
                if (\Storage::disk('public')->exists($oldPath)) {
                    \Storage::disk('public')->delete($oldPath);
                }
            }

            $path = $request->file('icon')->store('motorcycle_types', 'public');
            $data['icon'] = config('app.url') . '/storage/' . $path;
        }

        $type->update($data);

        return response()->json([
            'message' => 'Motorcycle type updated successfully.',
            'data' => $type
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/motorcycle-types/{id}",
     *     tags={"Motorcycle Types"},
     *     summary="Delete a motorcycle type",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Motorcycle type deleted successfully"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function destroy($id)
    {
        $type = MotorcycleType::find($id);

        if (!$type) {
            return response()->json(['message' => 'Motorcycle type not found.'], 404);
        }

        $type->delete();

        return response()->json([
            'message' => 'Motorcycle type deleted successfully.'
        ], 200);
    }
}
