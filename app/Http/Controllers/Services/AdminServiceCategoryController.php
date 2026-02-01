<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Admin Service Categories",
 *     description="API endpoints for managing service categories (Admin)"
 * )
 */
class AdminServiceCategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/service-categories",
     *     summary="List service categories (Admin)",
     *     operationId="adminGetServiceCategories",
     *     tags={"Admin Service Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_active", in="query", description="Filter by active status", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="Categories retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=5),
     *                         @OA\Property(property="name", type="string", example="Plumbing"),
     *                         @OA\Property(property="slug", type="string", example="plumbing"),
     *                         @OA\Property(property="is_active", type="boolean", example=true)
     *                     )
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=20)
     *             ),
     *             @OA\Property(property="message", type="string", example="Service categories retrieved successfully")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = ServiceCategory::orderBy('order_position', 'asc');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->input('per_page');

        if ($perPage) {
            $categories = $query->paginate($perPage);
        } else {
            $categories = $query->get();
        }

        return response()->json([
            'success' => true,
            'data' => $categories,
            'message' => 'Service categories retrieved successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/service-categories",
     *     summary="Create service category (Admin)",
     *     operationId="adminCreateServiceCategory",
     *     tags={"Admin Service Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "name_ar", "slug"},
     *             @OA\Property(property="name", type="string", example="Home Cleaning"),
     *             @OA\Property(property="name_ar", type="string", example="تنظيف منازل"),
     *             @OA\Property(property="slug", type="string", example="home-cleaning"),
     *             @OA\Property(property="icon", type="string", example="cleaning_icon.png"),
     *             @OA\Property(property="color", type="string", example="#3498db"),
     *             @OA\Property(property="description", type="string", example="Professional home cleaning services."),
     *             @OA\Property(property="description_ar", type="string", example="خدمات تنظيف منازل احترافية."),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Category created")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'slug' => 'required|string|unique:service_categories,slug|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'icon' => 'nullable|string',
            'image' => 'nullable|string',
            'color' => 'nullable|string',
            'is_active' => 'boolean',
            'order_position' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $category = ServiceCategory::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $category,
            'message' => 'Service category created successfully'
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/service-categories/{id}",
     *     summary="Get category details (Admin)",
     *     operationId="adminGetServiceCategory",
     *     tags={"Admin Service Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Category details retrieved")
     * )
     */
    public function show($id)
    {
        $category = ServiceCategory::findOrFail($id);
        return response()->json(['success' => true, 'data' => $category]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/service-categories/{id}",
     *     summary="Update service category (Admin)",
     *     operationId="adminUpdateServiceCategory",
     *     tags={"Admin Service Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ServiceCategory")),
     *     @OA\Response(response=200, description="Category updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $category = ServiceCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'name_ar' => 'string|max:255',
            'slug' => 'string|max:255|unique:service_categories,slug,' . $id,
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'icon' => 'nullable|string',
            'image' => 'nullable|string',
            'color' => 'nullable|string',
            'is_active' => 'boolean',
            'order_position' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $category->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $category,
            'message' => 'Service category updated successfully'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/service-categories/{id}",
     *     summary="Delete service category (Admin)",
     *     operationId="adminDeleteServiceCategory",
     *     tags={"Admin Service Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Category deleted")
     * )
     */
    public function destroy($id)
    {
        $category = ServiceCategory::findOrFail($id);

        if ($category->services()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category containing services'
            ], 400);
        }

        $category->delete();

        return response()->json(['success' => true, 'message' => 'Category deleted successfully']);
    }
}
