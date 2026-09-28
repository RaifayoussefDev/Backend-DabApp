<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Marketplace - Vendor account",
 *     description="A logged-in user applies to become a vendor and manages their own shop profile. Access: User (Bearer JWT). A guest gets the structured 401 (requires_auth=true). Being a vendor = owning a shop whose status is active; an admin approves applications from the Admin Panel. logo_path / cover_image_path are URLs from POST /api/marketplace/upload-image (type=vendor), not files: upload first, then send the strings here."
 * )
 */
class VendorAccountController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/marketplace/vendor/apply",
     *     summary="Apply to become a vendor",
     *     description="Creates the user's shop with status pending, waiting for admin approval. One shop per user: 409 ALREADY_APPLIED (pending), ALREADY_VENDOR (active) or VENDOR_SUSPENDED. A rejected application can be sent again: it reopens the same shop as pending. Status, slug and commission cannot be set by the user. Access: User.
     *
     * For a logo or a cover, call POST /api/marketplace/upload-image (type=vendor) first and send the URL(s) it returns as logo_path / cover_image_path. The city must belong to the country (422 on `city_id` otherwise). Load the pickers from `GET /api/countries-list` and `GET /api/cities?country_id=`.",
     *     tags={"Marketplace - Vendor account"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"shop_name","phone","country_id","city_id"},
     *         @OA\Property(property="shop_name", type="string", example="Moto Parts Riyadh"),
     *         @OA\Property(property="shop_name_ar", type="string"),
     *         @OA\Property(property="description", type="string"),
     *         @OA\Property(property="description_ar", type="string"),
     *         @OA\Property(property="phone", type="string", example="+966500000000"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="country_id", type="integer", example=1),
     *         @OA\Property(property="city_id", type="integer", example=1, description="Must belong to country_id"),
     *         @OA\Property(property="brand_color", type="string", example="#E63946", description="#RRGGBB"),
     *         @OA\Property(property="logo_path", type="string", nullable=true, example="https://api.dabapp.co/storage/marketplace/vendors/aB3dE9fGh1JkLmN0pQrS.jpg", description="URL returned by POST /api/marketplace/upload-image (type=vendor)"),
     *         @OA\Property(property="cover_image_path", type="string", nullable=true, example="https://api.dabapp.co/storage/marketplace/vendors/zY8xW7vU6tS5rQ4pO3nM.jpg"),
     *         @OA\Property(property="bank_name", type="string"),
     *         @OA\Property(property="iban", type="string")
     *     )),
     *     @OA\Response(response=201, description="Application created. Show the 'under review' screen.", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Application sent, waiting for approval"),
     *         @OA\Property(property="data", ref="#/components/schemas/MarketplaceMyVendor"),
     *         example={"message": "Application sent, waiting for approval", "data": {"id": 200, "slug": "moto-parts-riyadh", "shop_name": "Moto Parts Riyadh", "shop_name_ar": "مركز قطع الغيار", "description": "Tyres and brakes", "description_ar": null, "logo_url": null, "cover_image_url": null, "brand_color": "#E63946", "phone": "+966500000000", "email": null, "country": {"id": 1, "name": "Saudi Arabia"}, "city": {"id": 1, "name": "Riyadh"}, "status": "pending", "status_reason": null, "can_sell": false, "approved_at": null, "bank_name": null, "iban": null, "rating_avg": 0, "reviews_count": 0, "products_count": 0, "created_at": "2026-09-22T01:51:36+03:00"}}
     *     )),
     *     @OA\Response(response=200, description="A rejected application was sent again: same shop (same id), back to pending, reason cleared.", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Application sent again, waiting for approval"),
     *         @OA\Property(property="data", ref="#/components/schemas/MarketplaceMyVendor"),
     *         example={"message": "Application sent again, waiting for approval", "data": {"id": 200, "slug": "moto-parts-riyadh", "shop_name": "Moto Parts Riyadh (fixed)", "shop_name_ar": null, "description": "Tyres and brakes", "description_ar": null, "logo_url": "https://api.dabapp.co/storage/marketplace/vendors/logo.png", "cover_image_url": null, "brand_color": "#E63946", "phone": "+966500000000", "email": null, "country": {"id": 1, "name": "Saudi Arabia"}, "city": {"id": 1, "name": "Riyadh"}, "status": "pending", "status_reason": null, "can_sell": false, "approved_at": null, "bank_name": null, "iban": null, "rating_avg": 0, "reviews_count": 0, "products_count": 0, "created_at": "2026-09-22T01:51:36+03:00"}}
     *     )),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=409, description="The user already has a shop. Use `code`, not the message, to choose the screen.", @OA\JsonContent(ref="#/components/schemas/MarketplaceConflict",
     *         @OA\Examples(example="already_applied", summary="Still waiting for the admin", value={"message": "Your application is already waiting for approval", "code": "ALREADY_APPLIED", "status": "pending"}),
     *         @OA\Examples(example="already_vendor", summary="Already approved", value={"message": "You already have a vendor account", "code": "ALREADY_VENDOR", "status": "active"}),
     *         @OA\Examples(example="suspended", summary="Suspended by the admin", value={"message": "Your shop is suspended", "code": "VENDOR_SUSPENDED", "status": "suspended"})
     *     )),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationFailed")
     * )
     */
    public function apply(Request $request): JsonResponse
    {
        $user = Auth::user();
        $existing = Vendor::where('user_id', $user->id)->first();

        if ($existing && $existing->status !== 'rejected') {
            return $this->conflictFor($existing);
        }

        $validator = Validator::make($request->all(), $this->rules() + [
            'shop_name'  => 'required|string|max:255',
            'phone'      => 'required|string|max:30',
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            'city_id'    => ['required', 'integer', Rule::exists('cities', 'id')->where('country_id', (int) $request->input('country_id'))],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'shop_name', 'shop_name_ar', 'description', 'description_ar', 'phone', 'email',
            'country_id', 'city_id', 'brand_color', 'logo_path', 'cover_image_path', 'bank_name', 'iban',
        ]);

        if ($existing) {
            // Rejected before: same shop, back in the queue.
            $existing->update($data + ['status' => 'pending', 'status_reason' => null]);

            return response()->json([
                'message' => 'Application sent again, waiting for approval',
                'data'    => $this->present($existing->fresh()),
            ]);
        }

        $vendor = Vendor::create($data + [
            'user_id' => $user->id,
            'slug'    => $this->uniqueSlug($request->input('shop_name')),
            'status'  => 'pending',
        ]);

        return response()->json([
            'message' => 'Application sent, waiting for approval',
            'data'    => $this->present($vendor),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/marketplace/vendor/me",
     *     summary="My shop",
     *     description="The shop owned by the logged-in user, whatever its status, so the app can show 'under review', 'rejected (with the reason)', 'suspended' or the seller space. 404 NO_VENDOR when the user never applied. Access: User.",
     *     tags={"Marketplace - Vendor account"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="My shop. Pick the screen from `status`: pending = under review, rejected = show `status_reason` and a Resubmit button, suspended = show `status_reason`, active = seller space (`can_sell` is true).", @OA\JsonContent(
     *         @OA\Property(property="data", ref="#/components/schemas/MarketplaceMyVendor"),
     *         @OA\Examples(example="pending", summary="Under review", value={"data": {"id": 200, "slug": "moto-parts-riyadh", "shop_name": "Moto Parts Riyadh", "shop_name_ar": "مركز قطع الغيار", "description": "Tyres and brakes", "description_ar": null, "logo_url": null, "cover_image_url": null, "brand_color": "#E63946", "phone": "+966500000000", "email": null, "country": {"id": 1, "name": "Saudi Arabia"}, "city": {"id": 1, "name": "Riyadh"}, "status": "pending", "status_reason": null, "can_sell": false, "approved_at": null, "bank_name": null, "iban": null, "rating_avg": 0, "reviews_count": 0, "products_count": 0, "created_at": "2026-09-22T01:51:36+03:00"}}),
     *         @OA\Examples(example="rejected", summary="Rejected: show the reason and a Resubmit button", value={"data": {"id": 200, "slug": "moto-parts-riyadh", "shop_name": "Moto Parts Riyadh", "shop_name_ar": "مركز قطع الغيار", "description": "Tyres and brakes", "description_ar": null, "logo_url": null, "cover_image_url": null, "brand_color": "#E63946", "phone": "+966500000000", "email": null, "country": {"id": 1, "name": "Saudi Arabia"}, "city": {"id": 1, "name": "Riyadh"}, "status": "rejected", "status_reason": "Photos are missing", "can_sell": false, "approved_at": null, "bank_name": null, "iban": null, "rating_avg": 0, "reviews_count": 0, "products_count": 0, "created_at": "2026-09-22T01:51:36+03:00"}}),
     *         @OA\Examples(example="active", summary="Approved: the seller space opens", value={"data": {"id": 200, "slug": "moto-parts-riyadh", "shop_name": "Moto Parts Riyadh", "shop_name_ar": "مركز قطع الغيار", "description": "Tyres and brakes", "description_ar": null, "logo_url": "https://api.dabapp.co/storage/marketplace/vendors/logo.png", "cover_image_url": null, "brand_color": "#E63946", "phone": "+966500000000", "email": null, "country": {"id": 1, "name": "Saudi Arabia"}, "city": {"id": 1, "name": "Riyadh"}, "status": "active", "status_reason": null, "can_sell": true, "approved_at": "2026-09-23T10:02:11+03:00", "bank_name": null, "iban": null, "rating_avg": 0, "reviews_count": 0, "products_count": 0, "created_at": "2026-09-22T01:51:36+03:00"}})
     *     )),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=404, description="The user never applied: show the 'Become a seller' screen.", @OA\JsonContent(example={"message": "You do not have a vendor account", "code": "NO_VENDOR"}))
     * )
     */
    public function me(): JsonResponse
    {
        $vendor = Vendor::where('user_id', Auth::id())->first();

        return $vendor
            ? response()->json(['data' => $this->present($vendor)])
            : response()->json(['message' => 'You do not have a vendor account', 'code' => 'NO_VENDOR'], 404);
    }

    /**
     * @OA\Put(
     *     path="/api/marketplace/vendor/me",
     *     summary="Update my shop",
     *     description="Send only the fields to change. Allowed while pending or active (suspended: 403 VENDOR_SUSPENDED, rejected: use apply to send it again). Send logo_path / cover_image_path: null to remove that image, or omit it to leave it untouched. slug, status and commission are not editable here. Access: User.",
     *     tags={"Marketplace - Vendor account"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="shop_name", type="string"),
     *         @OA\Property(property="shop_name_ar", type="string"),
     *         @OA\Property(property="description", type="string"),
     *         @OA\Property(property="description_ar", type="string"),
     *         @OA\Property(property="phone", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="country_id", type="integer"),
     *         @OA\Property(property="city_id", type="integer"),
     *         @OA\Property(property="brand_color", type="string", example="#1D3557"),
     *         @OA\Property(property="logo_path", type="string", nullable=true, description="URL from POST /api/marketplace/upload-image, or null to remove it"),
     *         @OA\Property(property="cover_image_path", type="string", nullable=true, description="URL from POST /api/marketplace/upload-image, or null to remove it"),
     *         @OA\Property(property="bank_name", type="string"),
     *         @OA\Property(property="iban", type="string")
     *     )),
     *     @OA\Response(response=200, description="Updated. The response is the whole shop, ready to replace what the app holds.", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Shop updated successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/MarketplaceMyVendor"),
     *         example={"message": "Shop updated successfully", "data": {"id": 200, "slug": "moto-parts-riyadh", "shop_name": "Moto Parts Riyadh", "shop_name_ar": "مركز قطع الغيار", "description": "New description", "description_ar": null, "logo_url": null, "cover_image_url": null, "brand_color": "#1D3557", "phone": "+966500000000", "email": null, "country": {"id": 1, "name": "Saudi Arabia"}, "city": {"id": 2, "name": "Jeddah"}, "status": "pending", "status_reason": null, "can_sell": false, "approved_at": null, "bank_name": null, "iban": null, "rating_avg": 0, "reviews_count": 0, "products_count": 0, "created_at": "2026-09-22T01:51:36+03:00"}}
     *     )),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, description="The shop is suspended: show the reason, nothing can be edited.", @OA\JsonContent(example={"message": "Your shop is suspended", "code": "VENDOR_SUSPENDED", "reason": "Repeated complaints about fake parts"})),
     *     @OA\Response(response=404, description="The user never applied.", @OA\JsonContent(example={"message": "You do not have a vendor account", "code": "NO_VENDOR"})),
     *     @OA\Response(response=409, description="The application was rejected: it cannot be edited, send it again with POST /vendor/apply.", @OA\JsonContent(example={"message": "Your application was rejected, apply again to reopen it", "code": "APPLICATION_REJECTED", "reason": "Photos are missing"})),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationFailed")
     * )
     */
    public function update(Request $request): JsonResponse
    {
        $vendor = Vendor::where('user_id', Auth::id())->first();

        if (! $vendor) {
            return response()->json(['message' => 'You do not have a vendor account', 'code' => 'NO_VENDOR'], 404);
        }
        if ($vendor->status === 'suspended') {
            return response()->json(['message' => 'Your shop is suspended', 'code' => 'VENDOR_SUSPENDED', 'reason' => $vendor->status_reason], 403);
        }
        if ($vendor->status === 'rejected') {
            return response()->json(['message' => 'Your application was rejected, apply again to reopen it', 'code' => 'APPLICATION_REJECTED', 'reason' => $vendor->status_reason], 409);
        }

        $countryId = $request->filled('country_id') ? (int) $request->input('country_id') : $vendor->country_id;

        $validator = Validator::make($request->all(), $this->rules() + [
            'shop_name'  => 'sometimes|required|string|max:255',
            'phone'      => 'sometimes|required|string|max:30',
            'country_id' => ['sometimes', 'required', 'integer', Rule::exists('countries', 'id')],
            'city_id'    => ['sometimes', 'required', 'integer', Rule::exists('cities', 'id')->where('country_id', $countryId)],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'shop_name', 'shop_name_ar', 'description', 'description_ar', 'phone', 'email',
            'country_id', 'city_id', 'brand_color', 'bank_name', 'iban',
        ]);

        if ($request->has('logo_path')) {
            $data['logo_path'] = $request->input('logo_path');
        }
        if ($request->has('cover_image_path')) {
            $data['cover_image_path'] = $request->input('cover_image_path');
        }

        $vendor->update($data);

        return response()->json([
            'message' => 'Shop updated successfully',
            'data'    => $this->present($vendor->fresh()),
        ]);
    }

    // ------------------------------------------------------------------ internals

    private function rules(): array
    {
        return [
            'shop_name_ar'     => 'nullable|string|max:255',
            'description'      => 'nullable|string|max:5000',
            'description_ar'   => 'nullable|string|max:5000',
            'email'            => 'nullable|email|max:255',
            'brand_color'      => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_path'        => $this->imagePathRule(),
            'cover_image_path' => $this->imagePathRule(),
            'bank_name'        => 'nullable|string|max:255',
            'iban'             => 'nullable|string|max:64',
        ];
    }

    /**
     * Validation rule for an image field: the string returned by POST /api/marketplace/upload-image,
     * never a raw file. Loosely checked (must point at our own marketplace storage) so a user cannot
     * hot-link an arbitrary external image.
     */
    private function imagePathRule(): array
    {
        return ['nullable', 'string', 'max:2048', function ($attribute, $value, $fail) {
            if ($value && ! str_contains($value, '/storage/marketplace/')) {
                $fail('The ' . $attribute . ' must be a URL returned by POST /api/marketplace/upload-image.');
            }
        }];
    }

    private function conflictFor(Vendor $vendor): JsonResponse
    {
        [$message, $code, $http] = match ($vendor->status) {
            'pending'   => ['Your application is already waiting for approval', 'ALREADY_APPLIED', 409],
            'active'    => ['You already have a vendor account', 'ALREADY_VENDOR', 409],
            default     => ['Your shop is suspended', 'VENDOR_SUSPENDED', 409],
        };

        return response()->json(['message' => $message, 'code' => $code, 'status' => $vendor->status], $http);
    }

    /** What the owner sees of their own shop (never the commission rate). */
    private function present(Vendor $vendor): array
    {
        $vendor->loadMissing(['city:id,name', 'country:id,name'])->loadCount('products');

        return [
            'id'              => $vendor->id,
            'slug'            => $vendor->slug,
            'shop_name'       => $vendor->shop_name,
            'shop_name_ar'    => $vendor->shop_name_ar,
            'description'     => $vendor->description,
            'description_ar'  => $vendor->description_ar,
            'logo_url'        => $vendor->logo_url,
            'cover_image_url' => $vendor->cover_image_url,
            'brand_color'     => $vendor->brand_color,
            'phone'           => $vendor->phone,
            'email'           => $vendor->email,
            'country'         => $vendor->country ? ['id' => $vendor->country->id, 'name' => $vendor->country->name] : null,
            'city'            => $vendor->city ? ['id' => $vendor->city->id, 'name' => $vendor->city->name] : null,
            'status'          => $vendor->status,
            'status_reason'   => $vendor->status_reason,
            'can_sell'        => $vendor->status === 'active',
            'approved_at'     => $vendor->approved_at?->toIso8601String(),
            'bank_name'       => $vendor->makeVisible('bank_name')->bank_name,
            'iban'            => $vendor->makeVisible('iban')->iban,
            'rating_avg'      => (float) $vendor->rating_avg,
            'reviews_count'   => (int) $vendor->reviews_count,
            'products_count'  => (int) $vendor->products_count,
            'created_at'      => $vendor->created_at?->toIso8601String(),
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'shop';
        $slug = $base;

        for ($n = 1; Vendor::withTrashed()->where('slug', $slug)->exists(); $n++) {
            $slug = $base . '-' . $n;
        }

        return $slug;
    }
}
