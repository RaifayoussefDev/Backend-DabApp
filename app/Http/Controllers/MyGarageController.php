<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MyGarage;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use App\Models\MotorcycleYear;
use App\Models\MotorcycleType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MyGarageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/my-garage",
     *     tags={"My Garage"},
     *     summary="Get all motorcycles in the authenticated user's garage",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page (default is 10, max is 50)",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, minimum=1, maximum=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful retrieval of garage items",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Garage items retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="brand_id", type="integer", example=2),
     *                     @OA\Property(property="model_id", type="integer", example=5),
     *                     @OA\Property(property="year_id", type="integer", example=10),
     *                     @OA\Property(property="type_id", type="integer", example=3),
     *                     @OA\Property(property="title", type="string", nullable=true, example="My First Bike"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="This is my favorite motorcycle."),
     *                     @OA\Property(property="picture", type="string", nullable=true, example="http://example.com/image.jpg"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T12:00:00Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-02T12:00:00Z"),
     *                     @OA\Property(
     *                         property="brand",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="name", type="string", example="Yamaha")
     *                     ),
     *                     @OA\Property(
     *                         property="model",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=5),
     *                         @OA\Property(property="name", type="string", example="YZF-R3")
     *                     ),
     *                     @OA\Property(
     *                         property="year",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=10),
     *                         @OA\Property(property="year", type="integer", example=2020)
     *                     ),
     *                     @OA\Property(
     *                         property="type",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="Sport")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=3),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=25),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - User not authenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to retrieve garage items"),
     *             @OA\Property(property="details", type="string", example="Error message details")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $perPage = $request->get('per_page', 10);
            $perPage = min($perPage, 50);

            $garageItems = MyGarage::with(['brand', 'model', 'year', 'type'])
                ->where('user_id', $userId)
                ->orderBy('is_default', 'desc') // ✅ Moto par défaut en premier
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'message' => 'Garage items retrieved successfully',
                'data' => $garageItems->items(),
                'pagination' => [
                    'current_page' => $garageItems->currentPage(),
                    'last_page' => $garageItems->lastPage(),
                    'per_page' => $garageItems->perPage(),
                    'total' => $garageItems->total(),
                    'from' => $garageItems->firstItem(),
                    'to' => $garageItems->lastItem()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve garage items',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/my-garage",
     *     tags={"My Garage"},
     *     summary="Add a motorcycle to user's garage",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"brand_id", "model_id", "year_id", "type_id"},
     *             @OA\Property(property="brand_id", type="integer", example=1, description="Motorcycle brand ID"),
     *             @OA\Property(property="model_id", type="integer", example=1, description="Motorcycle model ID"),
     *             @OA\Property(property="year_id", type="integer", example=1, description="Motorcycle year ID"),
     *             @OA\Property(property="type_id", type="integer", example=1, description="Motorcycle type ID"),
     *             @OA\Property(property="title", type="string", nullable=true, example="My Daily Beast", description="Optional custom title for the motorcycle"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Perfect bike for daily commuting", description="Optional description"),
     *             @OA\Property(property="picture", type="string", nullable=true, example="https://example.com/bike.jpg", description="Optional picture URL")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Motorcycle added to garage successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Motorcycle added to garage successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", nullable=true, example="My Daily Beast"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Perfect bike for daily commuting"),
     *                 @OA\Property(property="picture", type="string", nullable=true, example="https://example.com/bike.jpg"),
     *                 @OA\Property(
     *                     property="brand",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Honda")
     *                 ),
     *                 @OA\Property(
     *                     property="model",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="CBR600RR")
     *                 ),
     *                 @OA\Property(
     *                     property="year",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="year", type="integer", example=2020)
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Sport")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="details",
     *                 type="object",
     *                 @OA\Property(
     *                     property="brand_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The brand id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="type_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The type id field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to add motorcycle to garage"),
     *             @OA\Property(property="details", type="string", example="Error message details")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $validated = $request->validate([
                'brand_id' => 'required|integer|exists:motorcycle_brands,id',
                'model_id' => 'required|integer|exists:motorcycle_models,id',
                'year_id' => 'required|integer|exists:motorcycle_years,id',
                'type_id' => 'required|integer|exists:motorcycle_types,id',
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:1000',
                'picture' => 'nullable|string|url',
            ]);

            // Verify that model belongs to the brand
            $model = MotorcycleModel::where('id', $validated['model_id'])
                ->where('brand_id', $validated['brand_id'])
                ->first();

            if (!$model) {
                return response()->json([
                    'error' => 'Validation failed',
                    'message' => 'The selected model does not belong to the selected brand.'
                ], 422);
            }

            // Verify that year belongs to the model
            $year = MotorcycleYear::where('id', $validated['year_id'])
                ->where('model_id', $validated['model_id'])
                ->first();

            if (!$year) {
                return response()->json([
                    'error' => 'Validation failed',
                    'message' => 'The selected year does not belong to the selected model.'
                ], 422);
            }

            $garageItem = MyGarage::create([
                'user_id' => $userId,
                'brand_id' => $validated['brand_id'],
                'model_id' => $validated['model_id'],
                'year_id' => $validated['year_id'],
                'type_id' => $validated['type_id'],
                'title' => $validated['title'] ?? null,
                'description' => $validated['description'] ?? null,
                'picture' => $validated['picture'] ?? null,
            ]);

            $garageItem->load(['brand', 'model', 'year', 'type']);

            return response()->json([
                'message' => 'Motorcycle added to garage successfully',
                'data' => $garageItem
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to add motorcycle to garage',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/my-garage/{id}",
     *     tags={"My Garage"},
     *     summary="Get a specific garage item",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Garage item ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Garage item retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Garage item retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", nullable=true, example="My Daily Beast"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Perfect bike for daily commuting"),
     *                 @OA\Property(property="picture", type="string", nullable=true, example="https://example.com/bike.jpg"),
     *                 @OA\Property(
     *                     property="brand",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Honda")
     *                 ),
     *                 @OA\Property(
     *                     property="model",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="CBR600RR")
     *                 ),
     *                 @OA\Property(
     *                     property="year",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="year", type="integer", example=2020)
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Sport")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Garage item not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Not found"),
     *             @OA\Property(property="message", type="string", example="Garage item not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to retrieve garage item"),
     *             @OA\Property(property="details", type="string", example="Error message details")
     *         )
     *     )
     * )
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $garageItem = MyGarage::with(['brand', 'model', 'year', 'type'])
                ->where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if (!$garageItem) {
                return response()->json([
                    'error' => 'Not found',
                    'message' => 'Garage item not found'
                ], 404);
            }

            return response()->json([
                'message' => 'Garage item retrieved successfully',
                'data' => $garageItem
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve garage item',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/my-garage/{id}",
     *     tags={"My Garage"},
     *     summary="Update a garage item",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Garage item ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="type_id", type="integer", example=2, description="Motorcycle type ID"),
     *             @OA\Property(property="title", type="string", nullable=true, example="Updated Beast Machine", description="Updated title"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Updated description with modifications", description="Updated description"),
     *             @OA\Property(property="picture", type="string", nullable=true, example="https://example.com/updated-bike.jpg", description="Updated picture URL")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Garage item updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Garage item updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", nullable=true, example="Updated Beast Machine"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Updated description"),
     *                 @OA\Property(property="picture", type="string", nullable=true, example="https://example.com/updated-bike.jpg"),
     *                 @OA\Property(
     *                     property="brand",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Honda")
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Cruiser")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Garage item not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Not found"),
     *             @OA\Property(property="message", type="string", example="Garage item not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Validation failed"),
     *             @OA\Property(property="details", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to update garage item"),
     *             @OA\Property(property="details", type="string", example="Error message details")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $garageItem = MyGarage::where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if (!$garageItem) {
                return response()->json([
                    'error' => 'Not found',
                    'message' => 'Garage item not found'
                ], 404);
            }

            $validated = $request->validate([
                'type_id' => 'sometimes|required|integer|exists:motorcycle_types,id',
                'title' => 'sometimes|nullable|string|max:255',
                'description' => 'sometimes|nullable|string|max:1000',
                'picture' => 'sometimes|nullable|string|url',
            ]);

            $garageItem->update($validated);
            $garageItem->load(['brand', 'model', 'year', 'type']);

            return response()->json([
                'message' => 'Garage item updated successfully',
                'data' => $garageItem
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update garage item',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/my-garage/{id}",
     *     tags={"My Garage"},
     *     summary="Delete a garage item",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Garage item ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Motorcycle removed from garage successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Motorcycle removed from garage successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Garage item not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Not found"),
     *             @OA\Property(property="message", type="string", example="Garage item not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to delete garage item"),
     *             @OA\Property(property="details", type="string", example="Error message details")
     *         )
     *     )
     * )
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $garageItem = MyGarage::where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if (!$garageItem) {
                return response()->json([
                    'error' => 'Not found',
                    'message' => 'Garage item not found'
                ], 404);
            }

            $garageItem->delete();

            return response()->json([
                'message' => 'Motorcycle removed from garage successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete garage item',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/motorcycle-data",
     *     tags={"My Garage"},
     *     summary="Get motorcycle data for dropdowns (brands, models, years, types)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Motorcycle data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Motorcycle data retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="brands",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Honda")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="models",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="brand_id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="CBR600RR"),
     *                         @OA\Property(property="brand_name", type="string", example="Honda")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="years",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="model_id", type="integer", example=1),
     *                         @OA\Property(property="year", type="integer", example=2020),
     *                         @OA\Property(property="model_name", type="string", example="CBR600RR")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="types",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Sport")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to retrieve motorcycle data"),
     *             @OA\Property(property="details", type="string", example="Error message details")
     *         )
     *     )
     * )
     */

    /**
     * @OA\Get(
     *     path="/api/my-garage/default",
     *     tags={"My Garage"},
     *     summary="Get user's default motorcycle",
     *     description="Returns the motorcycle marked as default in the user's garage. If no default is set, returns null.",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Default motorcycle retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Default motorcycle retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="brand_id", type="integer", example=2),
     *                 @OA\Property(property="model_id", type="integer", example=5),
     *                 @OA\Property(property="year_id", type="integer", example=10),
     *                 @OA\Property(property="type_id", type="integer", example=3),
     *                 @OA\Property(property="title", type="string", nullable=true, example="My Daily Beast"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Perfect bike for daily commuting"),
     *                 @OA\Property(property="picture", type="string", nullable=true, example="https://example.com/bike.jpg"),
     *                 @OA\Property(property="is_default", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T12:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-02T12:00:00Z"),
     *                 @OA\Property(
     *                     property="brand",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Yamaha")
     *                 ),
     *                 @OA\Property(
     *                     property="model",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="name", type="string", example="YZF-R3")
     *                 ),
     *                 @OA\Property(
     *                     property="year",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=10),
     *                     @OA\Property(property="year", type="integer", example=2020)
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="name", type="string", example="Sport")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No default motorcycle found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No default motorcycle set"),
     *             @OA\Property(property="data", type="null", example=null)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to retrieve default motorcycle"),
     *             @OA\Property(property="details", type="string", example="Error message details")
     *         )
     *     )
     * )
     */
    public function getDefault(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Récupérer la moto par défaut
            $defaultMotorcycle = MyGarage::with(['brand', 'model', 'year', 'type'])
                ->where('user_id', $userId)
                ->where('is_default', true)
                ->first();

            if (!$defaultMotorcycle) {
                return response()->json([
                    'message' => 'No default motorcycle set',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'message' => 'Default motorcycle retrieved successfully',
                'data' => $defaultMotorcycle
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve default motorcycle',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    public function getMotorcycleData(): JsonResponse
    {
        try {
            $brands = MotorcycleBrand::orderBy('name')->get(['id', 'name']);

            $models = MotorcycleModel::with('brand:id,name')
                ->orderBy('name')
                ->get(['id', 'brand_id', 'name']);

            $years = MotorcycleYear::with('model:id,name')
                ->orderBy('year', 'desc')
                ->get(['id', 'model_id', 'year']);

            $types = MotorcycleType::orderBy('name')->get(['id', 'name']);

            return response()->json([
                'message' => 'Motorcycle data retrieved successfully',
                'data' => [
                    'brands' => $brands,
                    'models' => $models->map(function ($model) {
                        return [
                            'id' => $model->id,
                            'brand_id' => $model->brand_id,
                            'name' => $model->name,
                            'brand_name' => $model->brand->name ?? null,
                        ];
                    }),
                    'years' => $years->map(function ($year) {
                        return [
                            'id' => $year->id,
                            'model_id' => $year->model_id,
                            'year' => $year->year,
                            'model_name' => $year->model->name ?? null,
                        ];
                    }),
                    'types' => $types,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve motorcycle data',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/my-garage/{id}/set-default",
     *     tags={"My Garage"},
     *     summary="Set a motorcycle as default in user's garage",
     *     description="When a motorcycle is set as default, all other motorcycles in the garage will be set to non-default",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Garage item ID to set as default",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Default motorcycle updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Default motorcycle set successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", nullable=true, example="My Daily Beast"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Perfect bike for daily commuting"),
     *                 @OA\Property(property="picture", type="string", nullable=true, example="https://example.com/bike.jpg"),
     *                 @OA\Property(property="is_default", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="brand",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Honda")
     *                 ),
     *                 @OA\Property(
     *                     property="model",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="CBR600RR")
     *                 ),
     *                 @OA\Property(
     *                     property="year",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="year", type="integer", example=2020)
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Sport")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Garage item not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Not found"),
     *             @OA\Property(property="message", type="string", example="Garage item not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to set default motorcycle"),
     *             @OA\Property(property="details", type="string", example="Error message details")
     *         )
     *     )
     * )
     */
    public function setDefault(Request $request, $id): JsonResponse
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Vérifier que la moto appartient bien à l'utilisateur
            $garageItem = MyGarage::where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if (!$garageItem) {
                return response()->json([
                    'error' => 'Not found',
                    'message' => 'Garage item not found'
                ], 404);
            }

            // ✅ Mettre toutes les autres motos à is_default = false
            MyGarage::where('user_id', $userId)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);

            // ✅ Mettre cette moto à is_default = true
            $garageItem->update(['is_default' => true]);

            // Recharger les relations
            $garageItem->load(['brand', 'model', 'year', 'type']);

            return response()->json([
                'message' => 'Default motorcycle set successfully',
                'data' => $garageItem
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to set default motorcycle',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
