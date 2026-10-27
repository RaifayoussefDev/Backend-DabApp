<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Marketplace - Addresses",
 *     description="A customer's own delivery address book, used at checkout. Access: User (Bearer JWT)."
 * )
 */
class AddressController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/marketplace/addresses",
     *     summary="List my addresses",
     *     tags={"Marketplace - Addresses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Addresses", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MarketplaceAddress"))
     *     )),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated")
     * )
     */
    public function index(): JsonResponse
    {
        $addresses = Address::with('country:id,name', 'city:id,name,name_ar')
            ->where('user_id', Auth::id())
            ->orderByDesc('is_default')->orderByDesc('id')
            ->get();

        return response()->json(['data' => $addresses]);
    }

    /**
     * @OA\Get(
     *     path="/api/marketplace/addresses/{id}",
     *     summary="My address detail",
     *     tags={"Marketplace - Addresses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Address", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/MarketplaceAddress"))),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=404, description="Not your address")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $address = $this->find($id);

        return $address
            ? response()->json(['data' => $address])
            : response()->json(['message' => 'Address not found'], 404);
    }

    /**
     * @OA\Post(
     *     path="/api/marketplace/addresses",
     *     summary="Add an address",
     *     tags={"Marketplace - Addresses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"full_name","phone","country_id","address_line"},
     *         @OA\Property(property="full_name", type="string", example="Khalid Al-Otaibi"),
     *         @OA\Property(property="phone", type="string", example="+966500000000"),
     *         @OA\Property(property="country_id", type="integer", example=1),
     *         @OA\Property(property="city_id", type="integer", nullable=true, example=5),
     *         @OA\Property(property="address_line", type="string", example="King Fahd Road, Building 12"),
     *         @OA\Property(property="address_line_2", type="string", nullable=true),
     *         @OA\Property(property="postal_code", type="string", nullable=true, example="12345"),
     *         @OA\Property(property="is_default", type="boolean", example=true)
     *     )),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Address added successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/MarketplaceAddress")
     *     )),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/MarketplaceValidationError"))
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $address = DB::transaction(function () use ($request) {
            if ($request->boolean('is_default')) {
                Address::where('user_id', Auth::id())->update(['is_default' => false]);
            }

            return Address::create($request->only([
                'full_name', 'phone', 'country_id', 'city_id', 'address_line', 'address_line_2', 'postal_code',
            ]) + ['user_id' => Auth::id(), 'is_default' => $request->boolean('is_default')]);
        });

        return response()->json([
            'message' => 'Address added successfully',
            'data'    => $address->load('country:id,name', 'city:id,name,name_ar'),
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/marketplace/addresses/{id}",
     *     summary="Update an address",
     *     tags={"Marketplace - Addresses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Address updated successfully"),
     *         @OA\Property(property="data", ref="#/components/schemas/MarketplaceAddress")
     *     )),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=404, description="Not your address"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/MarketplaceValidationError"))
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $address = $this->find($id);
        if (! $address) {
            return response()->json(['message' => 'Address not found'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules(sometimes: true));
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::transaction(function () use ($request, $address) {
            if ($request->has('is_default') && $request->boolean('is_default')) {
                Address::where('user_id', Auth::id())->where('id', '!=', $address->id)->update(['is_default' => false]);
            }

            $data = $request->only([
                'full_name', 'phone', 'country_id', 'city_id', 'address_line', 'address_line_2', 'postal_code',
            ]);
            if ($request->has('is_default')) {
                $data['is_default'] = $request->boolean('is_default');
            }

            $address->update($data);
        });

        return response()->json([
            'message' => 'Address updated successfully',
            'data'    => $address->fresh()->load('country:id,name', 'city:id,name,name_ar'),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/marketplace/addresses/{id}",
     *     summary="Delete an address",
     *     description="Soft delete. If it was the default, the most recently added remaining address becomes the new default.",
     *     tags={"Marketplace - Addresses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(@OA\Property(property="message", type="string", example="Address deleted successfully"))),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=404, description="Not your address")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $address = $this->find($id);
        if (! $address) {
            return response()->json(['message' => 'Address not found'], 404);
        }

        DB::transaction(function () use ($address) {
            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                Address::where('user_id', Auth::id())->orderByDesc('id')->first()?->update(['is_default' => true]);
            }
        });

        return response()->json(['message' => 'Address deleted successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/marketplace/addresses/{id}/default",
     *     summary="Set as my default address",
     *     tags={"Marketplace - Addresses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/MarketplaceAddress"))),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=404, description="Not your address")
     * )
     */
    public function setDefault(int $id): JsonResponse
    {
        $address = $this->find($id);
        if (! $address) {
            return response()->json(['message' => 'Address not found'], 404);
        }

        DB::transaction(function () use ($address) {
            Address::where('user_id', Auth::id())->where('id', '!=', $address->id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return response()->json(['data' => $address->fresh()->load('country:id,name', 'city:id,name,name_ar')]);
    }

    // ------------------------------------------------------------------ internals

    private function find(int $id): ?Address
    {
        return Address::with('country:id,name', 'city:id,name,name_ar')
            ->where('user_id', Auth::id())->find($id);
    }

    private function rules(bool $sometimes = false): array
    {
        $req = $sometimes ? 'sometimes|required' : 'required';

        return [
            'full_name'      => "{$req}|string|max:255",
            'phone'          => "{$req}|string|max:30",
            'country_id'     => [$sometimes ? 'sometimes' : 'required', 'integer', Rule::exists('countries', 'id')],
            'city_id'        => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'address_line'   => "{$req}|string|max:255",
            'address_line_2' => 'nullable|string|max:255',
            'postal_code'    => 'nullable|string|max:20',
            'is_default'     => 'nullable|boolean',
        ];
    }
}
