<?php

namespace App\Http\Controllers\Admin\Marketplace;

use App\Models\Marketplace\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Admin Marketplace - Brands",
 *     description="Product brands. logo_path is a URL from POST /api/marketplace/upload-image (type=brand), not a file: upload first, then send the string here."
 * )
 */
class BrandAdminController extends MarketplaceAdminController
{
    /**
     * @OA\Get(
     *     path="/api/admin/marketplace/brands",
     *     summary="List brands (Admin)",
     *     description="Active and inactive brands. per_page empty = everything, no pagination.",
     *     tags={"Admin Marketplace - Brands"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, description="Name / slug contains", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", required=false, @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Parameter(name="is_featured", in="query", required=false, @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Brands", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdminMarketplaceBrand")),
     *         @OA\Property(property="meta", type="object", description="Only when paginated",
     *             @OA\Property(property="current_page", type="integer"), @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="last_page", type="integer"), @OA\Property(property="total", type="integer"))
     *     )),
     *     @OA\Response(response=403, description="Not an admin")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $query = Brand::query()
            ->withCount('products')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->input('search') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('slug', 'like', $term));
            })
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('is_featured'), fn ($q) => $q->where('is_featured', $request->boolean('is_featured')))
            ->orderByDesc('is_featured')
            ->orderBy('name');

        $perPage = $this->perPage($request);

        return response()->json($perPage ? $this->paginated($query->paginate($perPage)) : ['data' => $query->get()]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/marketplace/brands/{id}",
     *     summary="Brand detail (Admin)",
     *     tags={"Admin Marketplace - Brands"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Brand", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceBrand"))),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $brand = Brand::withCount('products')->find($id);

        return $brand
            ? response()->json(['data' => $brand])
            : response()->json(['message' => 'Brand not found'], 404);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/marketplace/brands",
     *     summary="Create a brand",
     *     description="To add a logo, call POST /api/marketplace/upload-image (type=brand) first and send the URL it returns as logo_path.",
     *     tags={"Admin Marketplace - Brands"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"name"},
     *         @OA\Property(property="name", type="string", example="Michelin"),
     *         @OA\Property(property="slug", type="string", example="michelin"),
     *         @OA\Property(property="logo_path", type="string", nullable=true, example="https://api.dabapp.co/storage/marketplace/brands/aB3dE9fGh1JkLmN0pQrS.jpg", description="URL returned by POST /api/marketplace/upload-image (type=brand)"),
     *         @OA\Property(property="is_featured", type="boolean", example=false),
     *         @OA\Property(property="is_active", type="boolean", example=true)
     *     )),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Brand created successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceBrand")
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
            'name'        => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255|alpha_dash|unique:marketplace_brands,slug',
            'logo_path'   => $this->imagePathRule(),
            'is_featured' => 'nullable|boolean',
            'is_active'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $brand = Brand::create([
            'name'        => $request->input('name'),
            'slug'        => $request->filled('slug') ? $request->input('slug') : $this->uniqueSlug(Brand::class, $request->input('name')),
            'logo_path'   => $request->input('logo_path'),
            'is_featured' => $request->boolean('is_featured'),
            'is_active'   => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return response()->json([
            'message' => 'Brand created successfully',
            'data'    => $brand->loadCount('products'),
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/marketplace/brands/{id}",
     *     summary="Update a brand",
     *     description="Send only the fields to change. Send logo_path: null to remove the logo, or omit it to leave it untouched.",
     *     tags={"Admin Marketplace - Brands"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="slug", type="string"),
     *         @OA\Property(property="logo_path", type="string", nullable=true, description="URL from POST /api/marketplace/upload-image, or null to remove it"),
     *         @OA\Property(property="is_featured", type="boolean"),
     *         @OA\Property(property="is_active", type="boolean")
     *     )),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Brand updated successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/AdminMarketplaceBrand")
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

        $brand = Brand::find($id);
        if (! $brand) {
            return response()->json(['message' => 'Brand not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|required|string|max:255',
            'slug'        => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash', Rule::unique('marketplace_brands', 'slug')->ignore($id)],
            'logo_path'   => $this->imagePathRule(),
            'is_featured' => 'nullable|boolean',
            'is_active'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['name', 'slug']);

        foreach (['is_featured', 'is_active'] as $flag) {
            if ($request->has($flag)) {
                $data[$flag] = $request->boolean($flag);
            }
        }
        if ($request->has('logo_path')) {
            $data['logo_path'] = $request->input('logo_path');
        }

        $brand->update($data);

        return response()->json([
            'message' => 'Brand updated successfully',
            'data'    => $brand->fresh()->loadCount('products'),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/marketplace/brands/{id}",
     *     summary="Delete a brand",
     *     description="Soft delete. Products of this brand stay, with no brand shown (deactivate the brand instead if you only want to hide it from filters).",
     *     tags={"Admin Marketplace - Brands"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(@OA\Property(property="message", type="string", example="Brand deleted successfully"))),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        if ($denied = $this->forbidUnlessAdmin()) {
            return $denied;
        }

        $brand = Brand::find($id);
        if (! $brand) {
            return response()->json(['message' => 'Brand not found'], 404);
        }

        $brand->delete();

        return response()->json(['message' => 'Brand deleted successfully']);
    }
}
