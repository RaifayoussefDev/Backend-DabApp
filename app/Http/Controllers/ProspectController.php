<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Http\Controllers\ListingController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Traits\CategoryDataTrait;

class ProspectController extends Controller
{
    use CategoryDataTrait;

    /**
     * @OA\Post(
     *     path="/api/prospect-listings",
     *     summary="Create a listing for a prospect (unauthenticated)",
     *     description="Creates a new user (if not exists) and a listing in one go.",
     *     tags={"Prospects"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="phone", type="string", example="+212600000000", description="Required. Used to identify or create user."),
     *             @OA\Property(property="email", type="string", example="prospect@example.com", description="Optional."),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="brand_id", type="integer", example=7),
     *             @OA\Property(property="model_id", type="integer", example=5712),
     *             @OA\Property(property="year_id", type="integer", example=10671),
     *             @OA\Property(property="engine", type="string", example="9500cc"),
     *             @OA\Property(property="mileage", type="integer", example=9000),
     *             @OA\Property(property="body_condition", type="string", example="As New"),
     *             @OA\Property(property="modified", type="boolean", example=false),
     *             @OA\Property(property="insurance", type="boolean", example=true),
     *             @OA\Property(property="general_condition", type="string", example="New"),
     *             @OA\Property(property="vehicle_care", type="string", example="Wakeel"),
     *             @OA\Property(property="transmission", type="string", example="Automatic"),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="title", type="string", example="My Prospect Listing"),
     *             @OA\Property(property="description", type="string", example="Description here"),
     *             @OA\Property(property="price", type="number", example=9000),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="city_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Listing created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Listing created successfully"),
     *             @OA\Property(property="listing_id", type="integer", example=123),
     *             @OA\Property(property="pdf_url", type="string", example="https://api.dabapp.co/api/listings/123/pdf")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function store(Request $request)
    {
        // 1. Validation
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'email' => 'nullable|email',
            // Basic Listing Fields
            'category_id' => 'required|integer|in:1,2,3',
            'country_id' => 'required|integer',
            'city_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'images' => 'required|array|min:1',
            // Add other fields as loosely required depending on category logic, but for now we keep it flexible
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // 2. User Handling
            $phone = $request->input('phone');
            $user = User::where('phone', $phone)->first();

            if (!$user) {
                // Create new user
                $password = Str::random(10);
                $user = User::create([
                    'name' => 'Prospect ' . substr($phone, -4),
                    'phone' => $phone,
                    'email' => $request->input('email'),
                    'password' => Hash::make($password),
                    'role_id' => 2, // Assuming 2 is 'User' role
                    // Add other default fields if necessary
                ]);
            }

            // 3. Create Listing
            // We use ListingController logic by manual instantiation or simulation
            // But since ListingController:store is complex, we will manually create the listing here 
            // keeping it simple and tailored for the prospect flow which is robust.

            // 3. Create Listing
            $listing = Listing::create([
                'seller_id' => $user->id,
                'seller_type' => 'owner',
                'status' => 'published',
                'category_id' => $request->integer('category_id'),
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'price' => $request->input('price'),
                'country_id' => $request->integer('country_id'),
                'city_id' => $request->integer('city_id'),
                'contacting_channel' => 'phone',
                'allow_submission' => false,
                'auction_enabled' => false,
            ]);

            // Save Images
            if ($request->has('images')) {
                foreach ($request->input('images') as $imageUrl) {
                    $listing->images()->create(['image_url' => $imageUrl]);
                }
            }

            // Save Category Specific Details using Trait
            $this->handleCategorySpecificData($listing, $request);

            DB::commit();

            // 4. Generate PDF URL
            $pdfUrl = url("/api/listings/{$listing->id}/pdf");

            return response()->json([
                'message' => 'Listing created successfully',
                'listing_id' => $listing->id,
                'pdf_url' => $pdfUrl
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create listing', 'message' => $e->getMessage()], 500);
        }
    }
}
