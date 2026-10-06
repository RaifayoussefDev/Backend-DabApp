<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Marketplace - Wishlist",
 *     description="Favorite products. Access: User (auth:api)."
 * )
 */
class WishlistController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/marketplace/wishlist",
     *     summary="My wishlist",
     *     description="Products I favorited, newest first. A product that was deleted or went inactive is silently dropped from the list. Access: User.",
     *     tags={"Marketplace - Wishlist"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, description="1-50", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="Wishlist", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MarketplaceProductCard")),
     *         @OA\Property(property="meta", type="object",
     *             @OA\Property(property="current_page", type="integer"), @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="last_page", type="integer"), @OA\Property(property="total", type="integer"))
     *     )),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);

        $productIds = Wishlist::where('user_id', Auth::id())->orderByDesc('id')->pluck('product_id');

        $query = Product::visible()
            ->with(['vendor:id,slug,shop_name,shop_name_ar', 'category:id,slug,name,name_ar', 'brand:id,name,logo_path', 'coverImage', 'variants'])
            ->whereIn('id', $productIds)
            ->orderByRaw('FIELD(id, ' . ($productIds->implode(',') ?: '0') . ')');

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Product $p) => ProductController::card($p))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/marketplace/wishlist/{productId}",
     *     summary="Add a product to my wishlist",
     *     description="Idempotent: adding a product that is already there just returns it, no duplicate. Access: User.",
     *     tags={"Marketplace - Wishlist"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer", example=21)),
     *     @OA\Response(response=201, description="Added", @OA\JsonContent(@OA\Property(property="message", type="string", example="Added to wishlist"))),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=404, description="Product not found, or not visible", @OA\JsonContent(example={"message": "Product not found"}))
     * )
     */
    public function store(int $productId): JsonResponse
    {
        if (! Product::visible()->whereKey($productId)->exists()) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $existing = Wishlist::withTrashed()->where('user_id', Auth::id())->where('product_id', $productId)->first();

        if ($existing && $existing->trashed()) {
            $existing->restore();
        } elseif (! $existing) {
            Wishlist::create(['user_id' => Auth::id(), 'product_id' => $productId]);
        }

        return response()->json(['message' => 'Added to wishlist'], 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/marketplace/wishlist/{productId}",
     *     summary="Remove a product from my wishlist",
     *     description="Idempotent: removing a product that is not in the wishlist still returns 200. Access: User.",
     *     tags={"Marketplace - Wishlist"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer", example=21)),
     *     @OA\Response(response=200, description="Removed", @OA\JsonContent(@OA\Property(property="message", type="string", example="Removed from wishlist"))),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated")
     * )
     */
    public function destroy(int $productId): JsonResponse
    {
        Wishlist::where('user_id', Auth::id())->where('product_id', $productId)->delete();

        return response()->json(['message' => 'Removed from wishlist']);
    }
}
