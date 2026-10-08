<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Marketplace - Reviews",
 *     description="Product reviews: rating, a title/comment, 'what did you like' tags, photos. List is public; writing one needs a token. verified_purchase is always false for now - Orders/Cart (Step 5) aren't built yet, so there is nothing to verify a purchase against."
 * )
 */
class ProductReviewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/marketplace/products/{idOrSlug}/reviews",
     *     summary="List a product's reviews",
     *     description="Approved reviews only, newest first by default. summary.breakdown is the full count per star (1-5) across every approved review, independent of pagination and sort - build the 'Excellent/Very Good/Good/Fair/Poor' bars from it (5=Excellent ... 1=Poor). Access: Public.",
     *     tags={"Marketplace - Reviews"},
     *     @OA\Parameter(name="idOrSlug", in="path", required=true, @OA\Schema(type="string", example="bilt-sprint-gloves")),
     *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(type="string", enum={"newest","highest","lowest"}, default="newest")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, description="1-50", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="Reviews", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MarketplaceProductReview")),
     *         @OA\Property(property="summary", type="object",
     *             @OA\Property(property="rating_avg", type="number", example=4.8), @OA\Property(property="reviews_count", type="integer", example=108),
     *             @OA\Property(property="breakdown", type="object", example={"5": 90, "4": 14, "3": 3, "2": 1, "1": 0})),
     *         @OA\Property(property="meta", type="object",
     *             @OA\Property(property="current_page", type="integer"), @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="last_page", type="integer"), @OA\Property(property="total", type="integer")),
     *         example={"data": {
     *             {"id": 301, "rating": 5, "title": "Great Product!!", "comment": "Better than expected! Works fine and everything is working as great as it should be", "tags": {"Build Quality","Delivery Time"}, "image_urls": {}, "verified_purchase": false, "author": {"id": 9, "name": "User Name"}, "created_at": "2026-08-12T05:30:00.000000Z"}
     *         }, "summary": {"rating_avg": 4.8, "reviews_count": 108, "breakdown": {"5": 90, "4": 14, "3": 3, "2": 1, "1": 0}}, "meta": {"current_page": 1, "per_page": 15, "last_page": 8, "total": 108}}
     *     )),
     *     @OA\Response(response=404, description="Product not found", @OA\JsonContent(example={"message": "Product not found"}))
     * )
     */
    public function index(Request $request, string $idOrSlug): JsonResponse
    {
        $product = Product::visible()->where(ctype_digit($idOrSlug) ? 'id' : 'slug', $idOrSlug)->first();
        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);

        $query = ProductReview::approved()->where('product_id', $product->id)->with('user:id,first_name,last_name');

        match ($request->input('sort')) {
            'highest' => $query->orderByDesc('rating')->orderByDesc('id'),
            'lowest'  => $query->orderBy('rating')->orderByDesc('id'),
            default   => $query->orderByDesc('id'),
        };

        $page = $query->paginate($perPage);

        $breakdown = ProductReview::approved()->where('product_id', $product->id)
            ->selectRaw('rating, COUNT(*) as total')->groupBy('rating')->pluck('total', 'rating');

        return response()->json([
            'data'    => $page->getCollection()->map(fn (ProductReview $r) => self::row($r))->values(),
            'summary' => [
                'rating_avg'    => (float) $product->rating_avg,
                'reviews_count' => (int) $product->reviews_count,
                'breakdown'     => collect([5, 4, 3, 2, 1])->mapWithKeys(fn ($star) => [(string) $star => (int) ($breakdown[$star] ?? 0)]),
            ],
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
     *     path="/api/marketplace/products/{idOrSlug}/reviews",
     *     summary="Write a review",
     *     description="One review per user per product (409 ALREADY_REVIEWED on a second attempt - edit isn't supported yet, delete isn't exposed). Updates the product's rating_avg / reviews_count right away. Photos are URLs from POST /api/marketplace/upload-image (type=product), sent as plain strings in images[]. is_anonymous hides your name from the public list (the API still knows who wrote it). Access: User.",
     *     tags={"Marketplace - Reviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idOrSlug", in="path", required=true, @OA\Schema(type="string", example="bilt-sprint-gloves")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"rating"},
     *         @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=5),
     *         @OA\Property(property="title", type="string", nullable=true, example="Great Product!!"),
     *         @OA\Property(property="comment", type="string", nullable=true, maxLength=500, example="Great quality, I liked how fast it was shipped!"),
     *         @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"Build Quality","Delivery Time"}, description="Freeform, e.g. the app's fixed chip list: Build Quality, Delivery Time, Versatility, Availability, Value For Money, Packaging"),
     *         @OA\Property(property="images", type="array", @OA\Items(type="string"), example={}, description="URLs from POST /api/marketplace/upload-image (type=product)"),
     *         @OA\Property(property="is_anonymous", type="boolean", example=false, description="'Hide my name from the review'")
     *     )),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Review submitted successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/MarketplaceProductReview")
     *     )),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=404, description="Product not found", @OA\JsonContent(example={"message": "Product not found"})),
     *     @OA\Response(response=409, description="Already reviewed", @OA\JsonContent(example={"message": "You already reviewed this product", "code": "ALREADY_REVIEWED"})),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationFailed")
     * )
     */
    public function store(Request $request, string $idOrSlug): JsonResponse
    {
        $product = Product::visible()->where(ctype_digit($idOrSlug) ? 'id' : 'slug', $idOrSlug)->first();
        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'rating'       => 'required|integer|min:1|max:5',
            'title'        => 'nullable|string|max:255',
            'comment'      => 'nullable|string|max:2000',
            'tags'         => 'nullable|array',
            'tags.*'       => 'string|max:100',
            'images'       => 'nullable|array|max:10',
            'images.*'     => 'string|max:2048',
            'is_anonymous' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (ProductReview::where('product_id', $product->id)->where('user_id', Auth::id())->exists()) {
            return response()->json(['message' => 'You already reviewed this product', 'code' => 'ALREADY_REVIEWED'], 409);
        }

        $review = ProductReview::create([
            'product_id'   => $product->id,
            'user_id'      => Auth::id(),
            'rating'       => (int) $request->input('rating'),
            'title'        => $request->input('title'),
            'comment'      => $request->input('comment'),
            'tags'         => $request->input('tags', []),
            'images'       => $request->input('images', []),
            'is_anonymous' => $request->boolean('is_anonymous'),
        ]);

        $this->refreshProductRating($product);

        return response()->json([
            'message' => 'Review submitted successfully',
            'data'    => self::row($review->load('user:id,first_name,last_name')),
        ], 201);
    }

    /** Recomputes the denormalised counters from approved reviews, kept in sync on every write. */
    private function refreshProductRating(Product $product): void
    {
        $agg = ProductReview::approved()->where('product_id', $product->id)->selectRaw('AVG(rating) as avg, COUNT(*) as total')->first();

        $product->update([
            'rating_avg'    => round((float) $agg->avg, 2),
            'reviews_count' => (int) $agg->total,
        ]);
    }

    private static function row(ProductReview $review): array
    {
        return [
            'id'                => $review->id,
            'rating'            => $review->rating,
            'title'             => $review->title,
            'comment'           => $review->comment,
            'tags'              => $review->tags ?? [],
            'image_urls'        => collect($review->images ?? [])->map(fn ($path) => self::imageUrl($path))->values(),
            'verified_purchase' => (bool) $review->verified_purchase,
            'author'            => $review->is_anonymous ? null : ($review->user ? [
                'id' => $review->user->id, 'name' => trim($review->user->first_name . ' ' . $review->user->last_name),
            ] : null),
            'created_at' => $review->created_at,
        ];
    }

    /** Review photos are stored as plain paths/URLs in a JSON column, same resolution rule as every other image field. */
    private static function imageUrl(string $path): ?string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/' . $path);
    }
}
