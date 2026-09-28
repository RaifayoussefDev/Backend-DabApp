<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\Brand;
use App\Models\Marketplace\Category;
use App\Models\Marketplace\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Marketplace - Brands",
 *     description="Product brands (All Brands screen). Public, no token needed."
 * )
 */
class BrandController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/marketplace/brands",
     *     summary="List brands",
     *     description="Active brands with their number of visible products. With category_id (sub-categories included) only brands that have products there are returned. Access: Public.",
     *     tags={"Marketplace - Brands"},
     *     @OA\Parameter(name="category_id", in="query", required=false, description="Only brands with products in this category or its descendants", @OA\Schema(type="integer", example=12)),
     *     @OA\Parameter(name="search", in="query", required=false, description="Name contains", @OA\Schema(type="string", example="mich")),
     *     @OA\Parameter(name="featured", in="query", required=false, description="1 = featured brands only", @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Parameter(name="limit", in="query", required=false, description="Max rows (1-100)", @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Brands, featured first then A-Z. products_count counts visible products (with category_id: only those inside that category).", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MarketplaceBrand")),
     *         @OA\Examples(example="all", summary="All Brands screen", value={"data": {
     *             {"id": 23, "slug": "akrapovic", "name": "Akrapovic", "logo_url": "https://api.dabapp.co/storage/marketplace/brands/akrapovic.png", "is_featured": true, "products_count": 1},
     *             {"id": 22, "slug": "brembo", "name": "Brembo", "logo_url": "https://api.dabapp.co/storage/marketplace/brands/brembo.png", "is_featured": true, "products_count": 2},
     *             {"id": 30, "slug": "oxford", "name": "Oxford", "logo_url": null, "is_featured": false, "products_count": 1}
     *         }}),
     *         @OA\Examples(example="empty", summary="Nothing matches (search=zzz): an empty list, never a 404", value={"data": {}})
     *     ))
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $categoryIds = null;
        if ($request->filled('category_id')) {
            $category = Category::active()->find((int) $request->input('category_id'));
            $categoryIds = $category ? $category->descendantIds() : [0];
        }

        $counts = Product::visible()
            ->when($categoryIds, fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->whereNotNull('brand_id')
            ->selectRaw('brand_id, COUNT(*) as total')
            ->groupBy('brand_id')
            ->pluck('total', 'brand_id');

        $brands = Brand::active()
            ->when($categoryIds !== null, fn ($q) => $q->whereIn('id', $counts->keys()))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%' . $request->input('search') . '%'))
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->when($request->filled('limit'), fn ($q) => $q->limit(min(max((int) $request->input('limit'), 1), 100)))
            ->get();

        return response()->json([
            'data' => $brands->map(fn (Brand $b) => [
                'id'             => $b->id,
                'slug'           => $b->slug,
                'name'           => $b->name,
                'logo_url'       => $b->logo_url,
                'is_featured'    => $b->is_featured,
                'products_count' => (int) ($counts[$b->id] ?? 0),
            ])->values(),
        ]);
    }
}
