<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * @OA\Tag(
 *     name="Marketplace - Categories",
 *     description="Category tree of the marketplace (unlimited depth). Public, no token needed."
 * )
 */
class CategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/marketplace/categories",
     *     summary="List categories",
     *     description="Without parent_id: top-level categories. With parent_id: its direct children. With tree=1 every level is nested under 'children'. Only active categories. Access: Public.",
     *     tags={"Marketplace - Categories"},
     *     @OA\Parameter(name="parent_id", in="query", required=false, description="Return the children of this category instead of the top level", @OA\Schema(type="integer", example=3)),
     *     @OA\Parameter(name="tree", in="query", required=false, description="1 = nested tree of every descendant", @OA\Schema(type="integer", enum={0,1}, default=0)),
     *     @OA\Response(response=200, description="Categories, ordered as the admin arranged them.", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MarketplaceCategory")),
     *         @OA\Examples(example="top_level", summary="No parameter: top-level categories (home screen)", value={"data": {
     *             {"id": 1, "parent_id": null, "slug": "parts", "name": "Parts", "name_ar": "قطع الغيار", "image_url": null, "children_count": 5, "products_count": 11},
     *             {"id": 15, "parent_id": null, "slug": "oils-care", "name": "Oils & Care", "name_ar": "الزيوت والعناية", "image_url": null, "children_count": 2, "products_count": 2}
     *         }}),
     *         @OA\Examples(example="children", summary="parent_id=2: the sub-categories of Tyres", value={"data": {
     *             {"id": 3, "parent_id": 2, "slug": "tyres-front", "name": "Front tyres", "name_ar": "إطارات أمامية", "image_url": null, "children_count": 0, "products_count": 2},
     *             {"id": 4, "parent_id": 2, "slug": "tyres-rear", "name": "Rear tyres", "name_ar": "إطارات خلفية", "image_url": null, "children_count": 0, "products_count": 3}
     *         }}),
     *         @OA\Examples(example="tree", summary="tree=1: every level nested under children (leaves have an empty children list)", value={"data": {
     *             {"id": 1, "parent_id": null, "slug": "parts", "name": "Parts", "name_ar": "قطع الغيار", "image_url": null, "children_count": 1, "products_count": 5, "children": {
     *                 {"id": 2, "parent_id": 1, "slug": "tyres", "name": "Tyres", "name_ar": "إطارات", "image_url": null, "children_count": 1, "products_count": 5, "children": {
     *                     {"id": 3, "parent_id": 2, "slug": "tyres-front", "name": "Front tyres", "name_ar": "إطارات أمامية", "image_url": null, "children_count": 0, "products_count": 2, "children": {}}
     *                 }}
     *             }}
     *         }})
     *     )),
     *     @OA\Response(response=404, description="parent_id does not exist or is inactive", @OA\JsonContent(example={"message": "Category not found"}))
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $parentId = $request->filled('parent_id') ? (int) $request->input('parent_id') : null;

        if ($parentId !== null && ! Category::active()->whereKey($parentId)->exists()) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $byParent = Category::active()->orderBy('order_position')->orderBy('name')->get()
            ->groupBy(fn (Category $c) => $c->parent_id ?? 0);
        $counts = Category::productCounts();

        return response()->json([
            'data' => $this->level($byParent, $counts, $parentId, $request->boolean('tree')),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/marketplace/categories/{idOrSlug}",
     *     summary="Category detail",
     *     description="Category with its breadcrumb (root to parent) and direct children. Access: Public.",
     *     tags={"Marketplace - Categories"},
     *     @OA\Parameter(name="idOrSlug", in="path", required=true, description="Numeric id or slug", @OA\Schema(type="string", example="tyres")),
     *     @OA\Response(response=200, description="Category", @OA\JsonContent(
     *         example={"data": {"id": 2, "parent_id": 1, "slug": "tyres", "name": "Tyres", "name_ar": "إطارات", "image_url": null, "children_count": 3, "products_count": 5, "description": null,
     *             "breadcrumb": {{"id": 1, "slug": "parts", "name": "Parts", "name_ar": "قطع الغيار"}},
     *             "children": {
     *                 {"id": 3, "parent_id": 2, "slug": "tyres-front", "name": "Front tyres", "name_ar": "إطارات أمامية", "image_url": null, "children_count": 0, "products_count": 2},
     *                 {"id": 4, "parent_id": 2, "slug": "tyres-rear", "name": "Rear tyres", "name_ar": "إطارات خلفية", "image_url": null, "children_count": 0, "products_count": 3}
     *             }}},
     *         @OA\Property(property="data", allOf={
     *             @OA\Schema(ref="#/components/schemas/MarketplaceCategory"),
     *             @OA\Schema(
     *                 @OA\Property(property="description", type="string", nullable=true),
     *                 @OA\Property(property="breadcrumb", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="slug", type="string"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="name_ar", type="string", nullable=true)
     *                 ))
     *             )
     *         })
     *     )),
     *     @OA\Response(response=404, description="Not found or inactive", @OA\JsonContent(example={"message": "Category not found"}))
     * )
     */
    public function show(string $idOrSlug): JsonResponse
    {
        $category = Category::active()
            ->where(ctype_digit($idOrSlug) ? 'id' : 'slug', $idOrSlug)
            ->first();

        if (! $category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $byParent = Category::active()->orderBy('order_position')->orderBy('name')->get()
            ->groupBy(fn (Category $c) => $c->parent_id ?? 0);
        $counts = Category::productCounts();

        $data = $this->format($category, $byParent, $counts) + [
            'description' => $category->description,
            'breadcrumb'  => collect($category->breadcrumb())->map(fn (Category $c) => [
                'id' => $c->id, 'slug' => $c->slug, 'name' => $c->name, 'name_ar' => $c->name_ar,
            ])->values(),
            'children'    => $this->level($byParent, $counts, $category->id, false),
        ];

        return response()->json(['data' => $data]);
    }

    /** @param Collection<int|string,Collection<int,Category>> $byParent */
    private function level(Collection $byParent, array $counts, ?int $parentId, bool $tree): Collection
    {
        return $byParent->get($parentId ?? 0, collect())->map(function (Category $category) use ($byParent, $counts, $tree) {
            $row = $this->format($category, $byParent, $counts);

            if ($tree) {
                $row['children'] = $this->level($byParent, $counts, $category->id, true);
            }

            return $row;
        })->values();
    }

    private function format(Category $category, Collection $byParent, array $counts): array
    {
        return [
            'id'             => $category->id,
            'parent_id'      => $category->parent_id,
            'slug'           => $category->slug,
            'name'           => $category->name,
            'name_ar'        => $category->name_ar,
            'image_url'      => $category->image_url,
            'children_count' => $byParent->get($category->id, collect())->count(),
            'products_count' => $counts[$category->id] ?? 0,
        ];
    }
}
