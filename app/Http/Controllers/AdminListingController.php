<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Listing;
use App\Models\User;
use App\Models\AuctionHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\NotificationService;
use App\Models\CurrencyExchangeRate;
use App\Traits\CategoryDataTrait; // ✅ Added
use App\Models\ListingImage; // ✅ Added

class AdminListingController extends Controller
{
    use CategoryDataTrait; // ✅ Use Trait

    /**
     * @OA\Get(
     *     path="/api/admin/listings",
     *     summary="Get all listings (Admin)",
     *     tags={"Admin Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page (default 15)", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", description="Filter by status (published, draft, sold, etc)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="category_id", in="query", description="Filter by category", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", description="Search by title or ID", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort_by", in="query", description="Column to sort by", @OA\Schema(type="string", enum={"id", "title", "price", "status", "created_at", "views"})),
     *     @OA\Parameter(name="sort_order", in="query", description="Sort order (asc/desc)", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Response(response=200, description="Listings retrieved")
     * )
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSorts = ['id', 'title', 'price', 'status', 'created_at', 'views', 'updated_at'];

        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }

        if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        $query = Listing::with([
            'seller.pointsOfInterest',
            'images',
            'category',
            'city',
            'country',
            'country.currencyExchangeRate',
            'motorcycle.brand',
            'motorcycle.model',
            'motorcycle.year',
            'sparePart.bikePartBrand',
            'sparePart.bikePartCategory',
            'sparePart.motorcycleAssociations.brand',
            'sparePart.motorcycleAssociations.model',
            'sparePart.motorcycleAssociations.year',
            'licensePlate.format',
            'licensePlate.city',
            'licensePlate.fieldValues.formatField'
        ])
            ->orderBy($sortBy, $sortOrder);

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $listings = $query->paginate($perPage);

        return response()->json($listings);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/listings",
     *     summary="Create listing on behalf of a user",
     *     tags={"Admin Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="<strong>Listing Data & Examples</strong><br>Create a listing in a single step. Select the correct payload for the category.<br><br><strong>🟢 EXAMPLE 1: MOTORCYCLE (With Submission Enabled)</strong><br><pre>{ 'user_id': 2, 'category_id': 1, 'country_id': 1, 'city_id': 50, 'title': 'Ducati Panigale V4', 'allow_submission': true, 'minimum_bid': 50000, 'contacting_channel': 'phone', 'brand_id': 5, 'model_id': 120, 'year_id': 2024, 'engine': '1103cc', 'mileage': 5000, 'body_condition': 'As New', 'images': ['https://url.com/img1.jpg'] }</pre><strong>🟢 EXAMPLE 2: SPARE PART (Category 2)</strong><br><pre>{ 'user_id': 2, 'category_id': 2, 'country_id': 1, 'city_id': 50, 'title': 'Akrapovic Exhaust', 'price': 12000, 'contacting_channel': 'chat', 'bike_part_category_id': 3, 'condition': 'new', 'motorcycles': [{'brand_id': 5, 'model_id': 120, 'year_id': 2023}], 'images': ['https://url.com/part.jpg'] }</pre><strong>🟢 EXAMPLE 3: LICENSE PLATE (Category 3)</strong><br><pre>{ 'user_id': 2, 'category_id': 3, 'country_id': 1, 'city_id': 50, 'title': 'Dubai A 123', 'price': 250000, 'contacting_channel': 'both', 'plate_format_id': 5, 'fields': [{'field_id': 10, 'value': 'A'}, {'field_id': 11, 'value': '123'}] }</pre>",
     *         @OA\JsonContent(
     *             required={"user_id", "category_id"},
     *             @OA\Property(property="user_id", type="integer", example=2),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *      @OA\Property(property="country_id", type="integer", example=1),
     *      @OA\Property(property="city_id", type="integer", example=50),
     *             @OA\Property(property="title", type="string", example="Generic Listing"),
     *             @OA\Property(property="price", type="number", example=1000),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Listing created")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'step' => 'integer|min:1',
            'category_id' => 'required|exists:categories,id',
            'country_id' => 'nullable|exists:countries,id',
            'city_id' => 'nullable|exists:cities,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $user = User::find($request->user_id);
            // Optional: Check role if strictly required "roles id 2"
            // if ($user->role_id != 2) { ... }

            $step = $request->step ?? 1;

            // ✅ Create Listing
            $listing = Listing::create([
                'user_id' => $request->user_id, // Owner of the listing
                'seller_id' => $request->user_id,
                'category_id' => $request->category_id,
                'title' => $request->title,
                'description' => $request->description ?? '',
                'price' => $request->price ?? null,
                'country_id' => $request->country_id,
                'city_id' => $request->city_id,
                'status' => 'published', // ✅ Changed from 'active' to 'published' as requested
                'created_by' => $request->user()->id, // ✅ Track who created it (Admin)
                'minimum_bid' => $request->minimum_bid ?? null,
                'allow_submission' => $request->allow_submission ?? false,
                'contacting_channel' => $request->contacting_channel ?? 'phone',
                'seller_type' => $request->seller_type ?? 'owner',
            ]);

            // Handle Images
            if ($request->has('images')) {
                foreach ($request->images as $imageData) {
                    $imageUrl = is_array($imageData) ? ($imageData['image_url'] ?? null) : $imageData;
                    if ($imageUrl) {
                        $listing->images()->create(['image_url' => $imageUrl]);
                    }
                }
            }

            // Create Auction History if needed
            if ($listing->auction_enabled) {
                AuctionHistory::create([
                    'listing_id' => $listing->id,
                    'seller_id' => $listing->seller_id,
                    'bid_amount' => 0,
                    'bid_date' => now(),
                    'validated' => false,
                ]);
            }

            // ✅ Handle Category Specific Data (using Trait)
            $this->handleCategorySpecificData($listing, $request);

            // Notify User
            // app(NotificationService::class)->sendToUser($user, 'listing_created_by_admin', ...); 

            DB::commit();

            return response()->json([
                'message' => 'Listing created successfully for user ' . $user->name,
                'listing' => $listing
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/admin/listings/{id}",
     *     summary="Update listing (Admin)",
     *     tags={"Admin Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="price", type="number"),
     *             @OA\Property(property="status", type="string", example="active")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Listing updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $listing = Listing::findOrFail($id);

        $listing->update($request->except(['id', 'seller_id', 'created_at', 'images']));

        if ($request->has('images')) {
            $listing->images()->delete();
            foreach ($request->images as $imageData) {
                $imageUrl = is_array($imageData) ? ($imageData['image_url'] ?? null) : $imageData;
                if ($imageUrl) {
                    $listing->images()->create(['image_url' => $imageUrl]);
                }
            }
        }

        // ✅ Handle Category Specific Data (using Trait)
        $this->handleCategorySpecificData($listing, $request);

        return response()->json(['message' => 'Listing updated', 'listing' => $listing->fresh()->load(['motorcycle', 'sparePart', 'licensePlate', 'images'])]);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/listings/{id}/status",
     *     summary="Change listing status (Admin)",
     *     tags={"Admin Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="sold", enum={"draft", "active", "sold", "expired", "deleted"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Status updated")
     * )
     */
    public function changeStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|string']);

        $listing = Listing::findOrFail($id);
        $listing->status = $request->status;
        $listing->save();

        return response()->json(['message' => 'Status updated', 'status' => $listing->status]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/listings/{id}",
     *     summary="Delete listing (Admin)",
     *     tags={"Admin Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Listing deleted")
     * )
     */
    public function destroy($id)
    {
        $listing = Listing::findOrFail($id);

        DB::beginTransaction();
        try {
            // Delete related auction history
            DB::table('auction_histories')->where('listing_id', $listing->id)->delete();

            // Delete related submissions
            DB::table('submissions')->where('listing_id', $listing->id)->delete();

            // Delete related wishlists
            DB::table('wishlists')->where('listing_id', $listing->id)->delete();

            // Delete related images
            $listing->images()->delete();

            // Delete payments related to this listing (Optional: logic depends on business rule. Usually we keep payments but nullify relation or soft delete)
            // For now, we assume we might want to keep payment history or if it blocks, we delete it. 
            // Given the error was specifically about auction_histories, we start there.
            // If checking specifically for foreign key 'auction_histories_listing_id_foreign'

            $listing->delete();

            DB::commit();
            return response()->json(['message' => 'Listing deleted successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete listing: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/listings/{id}/images/reorder",
     *     summary="Reorder listing images (Admin)",
     *     description="Deletes existing images and recreates them in the provided order to reset IDs/sequence.",
     *     tags={"Admin Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"image_ids"},
     *             @OA\Property(
     *                 property="image_ids",
     *                 type="array",
     *                 description="Array of image IDs in desired order",
     *                 @OA\Items(type="integer", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Images reordered")
     * )
     */
    public function reorderImages(Request $request, $id)
    {
        $request->validate([
            'image_ids' => 'required|array',
            'image_ids.*' => 'exists:listing_images,id'
        ]);

        $listing = Listing::findOrFail($id);

        // Ensure all images belong to this listing
        $count = ListingImage::where('listing_id', $listing->id)
            ->whereIn('id', $request->image_ids)
            ->count();

        if ($count != count($request->image_ids)) {
            return response()->json(['error' => 'Some images do not belong to this listing or are duplicates'], 400);
        }

        DB::beginTransaction();
        try {
            // Fetch current images data to preserve URLs
            $currentImages = ListingImage::whereIn('id', $request->image_ids)->get()->keyBy('id');

            // Delete existing images
            ListingImage::whereIn('id', $request->image_ids)->delete();

            // Re-create in order
            foreach ($request->image_ids as $imgId) {
                if (isset($currentImages[$imgId])) {
                    $img = $currentImages[$imgId];
                    ListingImage::create([
                        'listing_id' => $listing->id,
                        'image_url' => $img->image_url,
                        'is_plate_image' => $img->is_plate_image,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Images reordered successfully', 'images' => $listing->images()->get()]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to reorder images'], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/listings/{id}/images/{image_id}",
     *     summary="Delete specific image from listing (Admin)",
     *     tags={"Admin Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="image_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Image deleted")
     * )
     */
    public function deleteImage($id, $imageId)
    {
        $image = ListingImage::where('listing_id', $id)->where('id', $imageId)->firstOrFail();
        $image->delete();

        return response()->json(['message' => 'Image deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/listings/{id}",
     *     summary="Get listing by ID (Admin)",
     *     tags={"Admin Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Listing details",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="price", type="number"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="country", type="string"),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="wishlist", type="boolean"),
     *             @OA\Property(property="is_seller", type="boolean", description="True if authenticated user is the seller"),
     *             @OA\Property(property="category_id", type="integer"),
     *             @OA\Property(property="submission", type="object", nullable=true),
     *             @OA\Property(property="seller", type="object", nullable=true),
     *             @OA\Property(property="motorcycle", type="object", nullable=true),
     *             @OA\Property(property="spare_part", type="object", nullable=true),
     *             @OA\Property(property="license_plate", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Listing not found")
     * )
     */
    public function show($id)
    {
        $listing = Listing::with([
            'seller.pointsOfInterest',
            'images',
            'category',
            'city',
            'country',
            'country.currencyExchangeRate',
            'motorcycle.brand',
            'motorcycle.model',
            'motorcycle.year',
            'sparePart.bikePartBrand',
            'sparePart.bikePartCategory',
            'sparePart.motorcycleAssociations.brand',
            'sparePart.motorcycleAssociations.model',
            'sparePart.motorcycleAssociations.year',
            'licensePlate.format',
            'licensePlate.city',
            'licensePlate.fieldValues.formatField'
        ])->findOrFail($id);

        return response()->json($listing);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/users/autocomplete",
     *     summary="Search users by name or phone",
     *     tags={"Admin Listings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         required=true,
     *         description="Search string (Phone, First Name, or Last Name)",
     *         @OA\Schema(type="string", example="John")
     *     ),
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page (default 10)", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Users found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="first_name", type="string"),
     *                 @OA\Property(property="last_name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="full_name", type="string")
     *             )),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     )
     * )
     */
    public function autocomplete(Request $request)
    {
        $search = $request->input('query');
        $perPage = $request->input('per_page', 10);

        if (empty($search)) {
            return response()->json([]);
        }

        $users = User::where(function ($q) use ($search) {
            $q->where('phone', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%");
        })
            ->select('id', 'first_name', 'last_name', 'email', 'phone')
            ->paginate($perPage);

        // Append full_name attribute
        $users->getCollection()->transform(function ($user) {
            $user->full_name = $user->first_name . ' ' . $user->last_name;
            return $user;
        });

        return response()->json($users);
    }
}
