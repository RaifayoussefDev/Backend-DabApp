<?php

namespace App\Http\Controllers\Admin\Marketplace;

use App\Models\Marketplace\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Admin Marketplace - Categories",
 *     description="Category tree management (unlimited depth). image_path is a URL from POST /api/marketplace/upload-image (type=category), not a file: upload first, then send the string here."
 * )
 */
class CategoryAdminController extends MarketplaceAdminController
{
    /**
     * @OA\Get(
     *     path="/api/admin/marketplace/categories",
     *     summary="List categories (Admin)",
     *     description="Flat list, active and inactive. Filter by parent (parent_id=root for top-level). per_page empty = everything, no pagination.",
     *     tags={"Admin Marketplace - Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, description="Name / name_ar / slug contains", @OA\Schema(type="string")),
     *     @OA\Parameter(name="parent_id", in="query", required=false, description="Parent id, or the word root for top-level categories", @OA\Schema(type="string", example="root")),
     *     @OA\Parameter(name="is_active", in="query", required=false, @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, description="1-100, empty = all", @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Categories", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdminMarketplaceCategory")),
     *         @OA\Property(property="meta", type="object", description="Only when paginated",
     *             @OA\Property(property="current_page", type="integer"), @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="last_page", type="integer"), @OA\Property(property="total", type="integer"))
     *     )),
     *     @OA\Response(response=401, description="Missing or invalid admin token"),
     *     @OA\Response(response=403, description="Not an admin")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $query = Category::query()
            ->with('parent:id,name,name_ar')
            ->withCount(['children', 'products'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->input('search') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('name_ar', 'like', $term)->orWhere('slug', 'like', $term));
            })
            ->when($request->input('parent_id') === 'root', fn ($q) => $q->whereNull('parent_id'))
            ->when($request->filled('parent_id') && $request->input('parent_id') !== 'root', fn ($q) => $q->where('parent_id', (int) $request->input('parent_id')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('parent_id')
            ->orderBy('order_position')
            ->orderBy('name');

        $perPage = $this->perPage($request);

        return response()->json($perPage ? $this->paginated($query->paginate($perPage)) : ['data' => $query->get()]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/marketplace/categories/{id}",
     *     summary="Category detail (Admin)",
     *     tags={"Admin Marketplace - Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Category", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceCategory"))),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $category = Category::with('parent:id,name,name_ar')->withCount(['children', 'products'])->find($id);

        return $category
            ? response()->json(['data' => $category])
            : response()->json(['message' => 'Category not found'], 404);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/marketplace/categories",
     *     summary="Create a category",
     *     description="slug is generated from the name when omitted. order_position defaults to the end of its siblings. To add an image, call POST /api/marketplace/upload-image (type=category) first and send the URL it returns as image_path.",
     *     tags={"Admin Marketplace - Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"name"},
     *         @OA\Property(property="name", type="string", example="Tyres"),
     *         @OA\Property(property="name_ar", type="string", example="إطارات"),
     *         @OA\Property(property="slug", type="string", example="tyres"),
     *         @OA\Property(property="parent_id", type="integer", nullable=true, example=3),
     *         @OA\Property(property="description", type="string"),
     *         @OA\Property(property="image_path", type="string", nullable=true, example="https://api.dabapp.co/storage/marketplace/categories/aB3dE9fGh1JkLmN0pQrS.jpg", description="URL returned by POST /api/marketplace/upload-image (type=category)"),
     *         @OA\Property(property="order_position", type="integer", example=0),
     *         @OA\Property(property="is_active", type="boolean", example=true)
     *     )),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Category created successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceCategory")
     *     )),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:255',
            'name_ar'        => 'nullable|string|max:255',
            'slug'           => 'nullable|string|max:255|alpha_dash|unique:marketplace_categories,slug',
            'parent_id'      => ['nullable', 'integer', Rule::exists('marketplace_categories', 'id')->whereNull('deleted_at')],
            'description'    => 'nullable|string',
            'image_path'     => $this->imagePathRule(),
            'order_position' => 'nullable|integer|min:0',
            'is_active'      => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $parentId = $request->input('parent_id') ?: null;

        $category = Category::create([
            'parent_id'      => $parentId,
            'name'           => $request->input('name'),
            'name_ar'        => $request->input('name_ar'),
            'slug'           => $request->filled('slug') ? $request->input('slug') : $this->uniqueSlug(Category::class, $request->input('name')),
            'description'    => $request->input('description'),
            'image_path'     => $request->input('image_path'),
            'order_position' => $request->filled('order_position')
                ? (int) $request->input('order_position')
                : ((int) Category::where('parent_id', $parentId)->max('order_position')) + 1,
            'is_active'      => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'data'    => $category->loadCount(['children', 'products']),
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/marketplace/categories/{id}",
     *     summary="Update a category",
     *     description="Send only the fields to change. Send image_path: null to remove the image, or omit it to leave it untouched. A category cannot be moved under itself or one of its descendants.",
     *     tags={"Admin Marketplace - Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="name_ar", type="string"),
     *         @OA\Property(property="slug", type="string"),
     *         @OA\Property(property="parent_id", type="integer", nullable=true),
     *         @OA\Property(property="description", type="string"),
     *         @OA\Property(property="image_path", type="string", nullable=true, description="URL from POST /api/marketplace/upload-image, or null to remove it"),
     *         @OA\Property(property="order_position", type="integer"),
     *         @OA\Property(property="is_active", type="boolean")
     *     )),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Category updated successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceCategory")
     *     )),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $category = Category::find($id);
        if (! $category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'           => 'sometimes|required|string|max:255',
            'name_ar'        => 'nullable|string|max:255',
            'slug'           => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash', Rule::unique('marketplace_categories', 'slug')->ignore($id)],
            'parent_id'      => ['nullable', 'integer', Rule::exists('marketplace_categories', 'id')->whereNull('deleted_at')],
            'description'    => 'nullable|string',
            'image_path'     => $this->imagePathRule(),
            'order_position' => 'nullable|integer|min:0',
            'is_active'      => 'nullable|boolean',
        ]);

        $validator->after(function ($v) use ($request, $category) {
            $parentId = $request->input('parent_id');
            if ($request->has('parent_id') && $parentId && in_array((int) $parentId, $category->descendantIds(), true)) {
                $v->errors()->add('parent_id', 'A category cannot be moved under itself or one of its descendants.');
            }
        });

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['name', 'name_ar', 'slug', 'description', 'order_position']);

        if ($request->has('parent_id')) {
            $data['parent_id'] = $request->input('parent_id') ?: null;
        }
        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }
        if ($request->has('image_path')) {
            $data['image_path'] = $request->input('image_path');
        }

        $category->update($data);

        return response()->json([
            'message' => 'Category updated successfully',
            'data'    => $category->fresh()->load('parent:id,name,name_ar')->loadCount(['children', 'products']),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/marketplace/categories/{id}",
     *     summary="Delete a category",
     *     description="Soft delete. Refused with 409 while the category has sub-categories (HAS_CHILDREN) or products (HAS_PRODUCTS).",
     *     tags={"Admin Marketplace - Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(@OA\Property(property="message", type="string", example="Category deleted successfully"))),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=409, description="Has children or products", @OA\JsonContent(ref="#/components/schemas/ConflictError"))
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $category = Category::find($id);
        if (! $category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        if ($category->children()->exists()) {
            return response()->json(['message' => 'Category has sub-categories and cannot be deleted', 'code' => 'HAS_CHILDREN'], 409);
        }
        if ($category->products()->exists()) {
            return response()->json(['message' => 'Category has products and cannot be deleted', 'code' => 'HAS_PRODUCTS'], 409);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/marketplace/categories/reorder",
     *     summary="Reorder categories",
     *     description="Sets order_position for the given ids (same pattern as guide-categories/reorder).",
     *     tags={"Admin Marketplace - Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"items"},
     *         @OA\Property(property="items", type="array", @OA\Items(type="object", required={"id","order_position"},
     *             @OA\Property(property="id", type="integer", example=12),
     *             @OA\Property(property="order_position", type="integer", example=0)))
     *     )),
     *     @OA\Response(response=200, description="Reordered", @OA\JsonContent(@OA\Property(property="message", type="string", example="Categories reordered successfully"))),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function reorder(Request $request): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'items'                  => 'required|array|min:1',
            'items.*.id'             => ['required', 'integer', Rule::exists('marketplace_categories', 'id')->whereNull('deleted_at')],
            'items.*.order_position' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::transaction(function () use ($request) {
            foreach ($request->input('items') as $item) {
                Category::whereKey($item['id'])->update(['order_position' => $item['order_position']]);
            }
        });

        return response()->json(['message' => 'Categories reordered successfully']);
    }
}
