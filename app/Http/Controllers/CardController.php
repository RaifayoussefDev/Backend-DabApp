<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BankCard;
use Illuminate\Support\Facades\Auth;
/**
 * @OA\Schema(
 *     schema="BankCard",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="card_number", type="string", example="**** **** **** 1234"),
 *     @OA\Property(property="card_holder_name", type="string", example="John Doe"),
 *     @OA\Property(property="expiration_date", type="string", format="date", example="2025-12-31"),
 *     @OA\Property(property="cvv", type="string", example="***"),
 *     @OA\Property(property="card_type_id", type="integer", example=1),
 *     @OA\Property(property="is_default", type="boolean", example=true),
 *     @OA\Property(
 *         property="BankCardType",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Visa"),
 *         @OA\Property(property="description", type="string", example="Standard Visa Card")
 *     )
 * )
 */
class CardController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/BankCards",
     *     summary="Get all BankCards (Admin only)",
     *     tags={"BankCards"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="List of BankCards")
     * )
     */
    public function index()
    {
        return response()->json(BankCard::with('BankCardType')->get());
    }

    /**
     * @OA\Get(
     *     path="/api/my-cards",
     *     summary="Get authenticated user's cards",
     *     tags={"BankCards"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of user's bank cards",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/BankCard")
     *         )
     *     )
     * )
     */
    public function myCards()
    {
        $user = Auth::user();
        return response()->json(BankCard::with('BankCardType')->where('user_id', $user->id)->get());
    }

    /**
     * @OA\Post(
     *     path="/api/my-cards",
     *     summary="Add a new card for authenticated user",
     *     tags={"BankCards"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"card_type_id"},
     *             @OA\Property(property="card_type_id", type="integer", example=1),
     *             @OA\Property(property="card_number", type="string", example="1234 5678 9012 3456"),
     *             @OA\Property(property="card_holder_name", type="string", example="John Doe"),
     *             @OA\Property(property="expiration_date", type="string", format="date", example="2026-08-31"),
     *             @OA\Property(property="cvv", type="string", example="123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Card added successfully",
     *         @OA\JsonContent(ref="#/components/schemas/BankCard")
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=409, description="Card already exists")
     * )
     */
    public function addMyCard(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'card_number' => 'nullable|string|max:255',
            'card_holder_name' => 'nullable|string|max:255',
            'expiration_date' => 'nullable|date',
            'cvv' => 'nullable|string|max:4',
            'card_type_id' => 'required|exists:card_types,id',
        ]);

        // Add user_id to validated data
        $validated['user_id'] = $user->id;

        // Check if card already exists
        if (!empty($validated['card_number'])) {
            $cardExists = BankCard::where('user_id', $user->id)
                ->where('card_number', $validated['card_number'])
                ->exists();

            if ($cardExists) {
                return response()->json([
                    'message' => 'This card already exists for your account.'
                ], 409);
            }
        }

        // Encrypt CVV if present
        if (!empty($validated['cvv'])) {
            $validated['cvv'] = encrypt($validated['cvv']);
        }

        // Set as default if first card
        $hasCard = BankCard::where('user_id', $user->id)->exists();
        $validated['is_default'] = !$hasCard;

        try {
            $bankCard = BankCard::create($validated);
            return response()->json([
                'message' => 'Bank card successfully added.',
                'data' => $bankCard
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while adding the bank card.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/my-cards/{id}",
     *     summary="Update user's card",
     *     tags={"BankCards"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Card ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="card_holder_name", type="string", example="John Doe Updated"),
     *             @OA\Property(property="expiration_date", type="string", format="date", example="2027-08-31"),
     *             @OA\Property(property="cvv", type="string", example="456"),
     *             @OA\Property(property="is_default", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Card updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/BankCard")
     *     ),
     *     @OA\Response(response=403, description="Not authorized to update this card"),
     *     @OA\Response(response=404, description="Card not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function editMyCard(Request $request, $id)
    {
        $user = Auth::user();

        $bankCard = BankCard::findOrFail($id);

        // Verify the card belongs to the user
        if ($bankCard->user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not authorized to update this card.'
            ], 403);
        }

        $validated = $request->validate([
            'card_holder_name' => 'sometimes|string|max:255',
            'expiration_date' => 'sometimes|date',
            'cvv' => 'sometimes|string|max:4',
            'is_default' => 'sometimes|boolean',
        ]);

        // Encrypt CVV if present
        if (!empty($validated['cvv'])) {
            $validated['cvv'] = encrypt($validated['cvv']);
        }

        // If setting as default, unset other defaults
        if (isset($validated['is_default']) && $validated['is_default']) {
            BankCard::where('user_id', $user->id)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);
        }

        $bankCard->update($validated);

        return response()->json([
            'message' => 'Card updated successfully.',
            'data' => $bankCard
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/my-cards/{id}",
     *     summary="Delete user's card",
     *     tags={"BankCards"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Card ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Card deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Card deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not authorized to delete this card"),
     *     @OA\Response(response=404, description="Card not found")
     * )
     */
    public function deleteMyCard($id)
    {
        $user = Auth::user();

        $bankCard = BankCard::findOrFail($id);

        // Verify the card belongs to the user
        if ($bankCard->user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not authorized to delete this card.'
            ], 403);
        }

        // If deleting default card, set another card as default if available
        if ($bankCard->is_default) {
            $otherCard = BankCard::where('user_id', $user->id)
                ->where('id', '!=', $id)
                ->first();

            if ($otherCard) {
                $otherCard->update(['is_default' => true]);
            }
        }

        $bankCard->delete();

        return response()->json([
            'message' => 'Card deleted successfully.'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/BankCards/{id}",
     *     summary="Get BankCard by ID",
     *     tags={"BankCards"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="BankCard details"),
     *     @OA\Response(response=404, description="BankCard not found")
     * )
     */
    public function show($id)
    {
        return response()->json(BankCard::with('BankCardType')->findOrFail($id));
    }

    /**
     * @OA\Put(
     *     path="/api/BankCards/{id}",
     *     summary="Update BankCard (Admin only)",
     *     tags={"BankCards"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="New VIP BankCard"),
     *             @OA\Property(property="BankCard_type_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=200, description="BankCard updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $BankCard = BankCard::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'BankCard_type_id' => 'sometimes|exists:BankCard_types,id'
        ]);
        $BankCard->update($validated);
        return response()->json($BankCard);
    }

    /**
     * @OA\Delete(
     *     path="/api/BankCards/{id}",
     *     summary="Delete BankCard (Admin only)",
     *     tags={"BankCards"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="BankCard deleted")
     * )
     */
    public function destroy($id)
    {
        BankCard::findOrFail($id)->delete();
        return response()->json(['message' => 'BankCard deleted']);
    }
}
