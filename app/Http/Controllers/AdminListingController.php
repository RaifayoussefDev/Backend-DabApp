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
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Listings retrieved")
     * )
     */
    public function index(Request $request)
    {
        $listings = Listing::with(['seller', 'images', 'category'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($listings);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/listings",
     *     summary="Create listing on behalf of a user",
     *     tags={"Admin Listings"},
     *     security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     *     required=true,
     *     description="Listing data with user assignment",
     *     @OA\JsonContent(
     *         required={"user_id", "category_id"},
     *         @OA\Property(property="user_id", type="integer", example=2, description="User ID (e.g., Role ID 2) to assign the listing to"),
     *         @OA\Property(property="step", type="integer", example=3, description="Step to complete listing directly"),
     *         @OA\Property(property="category_id", type="integer", example=1, description="1=Motorcycle, 2=Spare Part, 3=Plate"),
     *         @OA\Property(property="title", type="string", example="Premium Motorcycle for Sale (Admin Created)"),
     *         @OA\Property(property="description", type="string", example="This is a high-value listing created by an admin for a VIP client."),
     *         @OA\Property(property="price", type="number", example=45000),
     *         @OA\Property(property="country_id", type="integer", example=1),
     *         @OA\Property(property="city_id", type="integer", example=1),
     *         @OA\Property(property="auction_enabled", type="boolean", example=false),
     *         @OA\Property(property="minimum_bid", type="number", example=null),
     *         @OA\Property(property="allow_submission", type="boolean", example=true),
     *         @OA\Property(property="contacting_channel", type="string", example="phone"),
     *         @OA\Property(property="seller_type", type="string", example="dealer"),
     *         @OA\Property(
     *             property="images",
     *             type="array",
     *             @OA\Items(type="string", example="https://be.dabapp.co/storage/listings/example.jpg")
     *         )
     *     )
     * ),
     * @OA\Response(response=201, description="Listing created")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'step' => 'integer|min:1',
            'category_id' => 'required|exists:categories,id',
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

            $listing = Listing::create([
                'seller_id' => $user->id,
                'status' => 'active', // Admin created listings might default to active
                'step' => $step,
                'created_at' => now(),
                'category_id' => $request->category_id,
                'title' => $request->title,
                'description' => $request->description,
                'price' => $request->price,
                'country_id' => $request->country_id,
                'city_id' => $request->city_id,
                'auction_enabled' => $request->auction_enabled ?? false,
                'minimum_bid' => $request->minimum_bid,
                'allow_submission' => $request->allow_submission ?? false,
                'contacting_channel' => $request->contacting_channel ?? 'phone',
                'seller_type' => $request->seller_type ?? 'owner',
            ]);
            
            // Handle Images
            if ($request->has('images')) {
                foreach ($request->images as $imageUrl) {
                    $listing->images()->create(['image_url' => $imageUrl]);
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
             foreach ($request->images as $imageUrl) {
                $listing->images()->create(['image_url' => $imageUrl]);
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
        $listing->delete();
        return response()->json(['message' => 'Listing deleted']);
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
}
