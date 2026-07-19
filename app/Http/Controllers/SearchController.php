<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Search",
 *     description="Unified free-text search across motorcycles, spare parts and license plates"
 * )
 */
class SearchController extends Controller
{
    /** category_id => machine-readable type slug, used by the frontend to route to the right listing page */
    private const CATEGORY_TYPES = [
        1 => 'motorcycle',
        2 => 'spare_part',
        3 => 'plate',
    ];

    private function hasValue($value): bool
    {
        if (is_null($value)) {
            return false;
        }
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        return true;
    }

    /**
     * Applies the free-text match against title/description plus the type-specific
     * taxonomy (brand/model for motorcycles & spare parts, plate field values for plates).
     */
    private function applySearchTerm($query, string $term): void
    {
        $query->where(function ($q) use ($term) {
            $q->where('title', 'LIKE', "%{$term}%")
                ->orWhere('description', 'LIKE', "%{$term}%")
                ->orWhereHas('motorcycle.brand', fn ($b) => $b->where('name', 'LIKE', "%{$term}%"))
                ->orWhereHas('motorcycle.model', fn ($b) => $b->where('name', 'LIKE', "%{$term}%"))
                ->orWhereHas('sparePart.bikePartBrand', fn ($b) => $b->where('name', 'LIKE', "%{$term}%"))
                ->orWhereHas('sparePart.bikePartCategory', fn ($b) => $b->where('name', 'LIKE', "%{$term}%"))
                ->orWhereHas('licensePlate.fieldValues', fn ($b) => $b->where('field_value', 'LIKE', "%{$term}%"));
        });
    }

    private function baseQuery(Request $request, string $term)
    {
        // Note: Listing::scopePublished() checks a published_at column that doesn't
        // exist in the live schema — every other public listing endpoint in this
        // codebase filters on status directly for the same reason.
        $query = Listing::query()->where('status', 'published');

        if ($this->hasValue($term)) {
            $this->applySearchTerm($query, $term);
        }

        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');
        if ($this->hasValue($minPrice)) {
            $query->where('price', '>=', (float) $minPrice);
        }
        if ($this->hasValue($maxPrice)) {
            $query->where('price', '<=', (float) $maxPrice);
        }

        $countryId = $request->input('country_id');
        if ($this->hasValue($countryId)) {
            $query->where('country_id', (int) $countryId);
        }

        $cityId = $request->input('city_id');
        if ($this->hasValue($cityId)) {
            $query->where('city_id', (int) $cityId);
        }

        return $query;
    }

    /** baseQuery() plus the category_id restriction — the actual listing query. */
    private function categoryQuery(Request $request, string $term)
    {
        $query = $this->baseQuery($request, $term);

        $categoryId = $request->input('category_id');
        if ($this->hasValue($categoryId)) {
            $query->where('category_id', (int) $categoryId);
        } else {
            $query->whereIn('category_id', array_keys(self::CATEGORY_TYPES));
        }

        return $query;
    }

    /**
     * Per-category match counts and a total — always across all 3 categories,
     * ignoring any category_id filter, so the UI can render "switch category" tabs.
     */
    private function computeCounts(Request $request, string $term): array
    {
        $counts = [];
        foreach (self::CATEGORY_TYPES as $categoryId => $type) {
            $counts[$type] = (clone $this->baseQuery($request, $term))
                ->where('category_id', $categoryId)
                ->count();
        }
        $counts['total'] = array_sum($counts);

        return $counts;
    }

    private function loadRelations($collection): void
    {
        $collection->load([
            'images' => fn ($q) => $q->select('listing_id', 'image_url')->orderBy('id', 'asc'),
            'motorcycle' => fn ($q) => $q->select('id', 'listing_id', 'brand_id', 'model_id')
                ->with(['brand:id,name', 'model:id,name']),
            'sparePart' => fn ($q) => $q->select('id', 'listing_id', 'bike_part_brand_id', 'bike_part_category_id')
                ->with(['bikePartBrand:id,name', 'bikePartCategory:id,name']),
            'licensePlate.format',
            'licensePlate.fieldValues.formatField',
            'country:id,name',
            'city:id,name',
            'category:id,name',
            'country.currencyExchangeRate:id,country_id,currency_symbol',
        ]);
    }

    /** Builds one unified card shape shared by motorcycles, spare parts and plates. */
    private function formatListing(Listing $listing): array
    {
        $displayPrice = $listing->allow_submission || $listing->auction_enabled
            ? $listing->minimum_bid
            : $listing->price;

        $subtitle = match ($listing->category_id) {
            1 => trim(($listing->motorcycle?->brand?->name ?? '') . ' ' . ($listing->motorcycle?->model?->name ?? '')) ?: null,
            2 => trim(($listing->sparePart?->bikePartBrand?->name ?? '') . ' ' . ($listing->sparePart?->bikePartCategory?->name ?? '')) ?: null,
            3 => $listing->licensePlate?->format?->name,
            default => null,
        };

        return [
            'id'          => $listing->id,
            'category_id' => $listing->category_id,
            'category'    => $listing->category?->name,
            'type'        => self::CATEGORY_TYPES[$listing->category_id] ?? null,
            'title'       => $listing->title,
            'subtitle'    => $subtitle,
            'price'       => (string) ($displayPrice ?? 0),
            'is_auction'  => (bool) $listing->auction_enabled,
            'currency'    => $listing->country?->currencyExchangeRate?->currency_symbol ?? 'MAD',
            'image'       => $listing->images->first()?->image_url ?? null,
            'listing_date' => $listing->created_at?->format('Y-m-d H:i:s'),
            'location'    => [
                'country' => $listing->country?->name,
                'city'    => $listing->city?->name,
            ],
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/search/suggest",
     *     summary="Live search suggestions",
     *     description="Lightweight autocomplete endpoint for the header search bar. Returns a small, capped set of top matches across motorcycles, spare parts and license plates, plus the total match count per category so the UI can show 'See all N results'.",
     *     operationId="searchSuggest",
     *     tags={"Search"},
     *     @OA\Parameter(name="q", in="query", required=true, @OA\Schema(type="string", example="yamaha r1")),
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=8, maximum=20)),
     *     @OA\Response(
     *         response=200,
     *         description="Suggestions retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="type", type="string", example="motorcycle"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="subtitle", type="string", nullable=true),
     *                 @OA\Property(property="price", type="string"),
     *                 @OA\Property(property="image", type="string", nullable=true)
     *             )),
     *             @OA\Property(property="counts", type="object",
     *                 @OA\Property(property="motorcycle", type="integer"),
     *                 @OA\Property(property="spare_part", type="integer"),
     *                 @OA\Property(property="plate", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Missing or too-short query")
     * )
     */
    public function suggest(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);

        $term  = trim($request->input('q'));
        $limit = min((int) $request->input('limit', 8), 20);
        $counts = $this->computeCounts($request, $term);

        $listings = $this->categoryQuery($request, $term)
            ->select(['id', 'title', 'description', 'price', 'auction_enabled', 'minimum_bid', 'allow_submission', 'created_at', 'country_id', 'city_id', 'category_id'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $this->loadRelations($listings);

        return response()->json([
            'success' => true,
            'data'    => $listings->map(fn ($listing) => $this->formatListing($listing))->values(),
            'counts'  => $counts,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/search",
     *     summary="Full search results",
     *     description="Paginated free-text search across motorcycles, spare parts and license plates (or a single category via category_id). Backs the dedicated search results page.",
     *     operationId="searchListings",
     *     tags={"Search"},
     *     @OA\Parameter(name="q", in="query", required=true, @OA\Schema(type="string", example="yamaha r1")),
     *     @OA\Parameter(name="category_id", in="query", required=false, description="1=motorcycles, 2=spare parts, 3=plates. Omit to search all.", @OA\Schema(type="integer", enum={1,2,3})),
     *     @OA\Parameter(name="min_price", in="query", required=false, @OA\Schema(type="number")),
     *     @OA\Parameter(name="max_price", in="query", required=false, @OA\Schema(type="number")),
     *     @OA\Parameter(name="country_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="city_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15, maximum=100)),
     *     @OA\Response(response=200, description="Results retrieved"),
     *     @OA\Response(response=422, description="Missing or too-short query")
     * )
     */
    public function search(Request $request)
    {
        $request->validate([
            'q'           => 'required|string|min:2|max:100',
            'category_id' => 'nullable|integer|in:1,2,3',
        ]);

        $term    = trim($request->input('q'));
        $perPage = min((int) $request->input('per_page', 15), 100);

        $query = $this->categoryQuery($request, $term)
            ->select(['id', 'title', 'description', 'price', 'auction_enabled', 'minimum_bid', 'allow_submission', 'created_at', 'country_id', 'city_id', 'category_id'])
            ->orderByDesc('created_at');

        $results = $query->paginate($perPage);

        $collection = $results->getCollection();
        $this->loadRelations($collection);

        return response()->json([
            'success'      => true,
            'query'        => $term,
            'total'        => $results->total(),
            'current_page' => $results->currentPage(),
            'per_page'     => $results->perPage(),
            'last_page'    => $results->lastPage(),
            'counts'       => $this->computeCounts($request, $term),
            'data'         => $collection->map(fn ($listing) => $this->formatListing($listing))->values(),
        ]);
    }
}
