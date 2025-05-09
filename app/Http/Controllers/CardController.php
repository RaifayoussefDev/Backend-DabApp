<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BankCard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        return response()->json(BankCard::with('cardType')->where('user_id', $user->id)->get());
    }

    /**
     * @OA\Post(
     *     path="/api/my-cards",
     *     summary="Add a new card for the authenticated user",
     *     tags={"BankCards"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"card_type_id"},
     *             @OA\Property(property="card_type_id", type="integer", example=1),
     *             @OA\Property(property="card_number", type="string", example="1234 5678 9012 3456"),
     *             @OA\Property(property="card_holder_name", type="string", example="John Doe"),
     *             @OA\Property(
     *                 property="expiration_date",
     *                 type="string",
     *                 example="12/25",
     *                 description="Format: MM/YY"
     *             ),
     *             @OA\Property(property="cvv", type="string", example="123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Card added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Bank card successfully added."),
     *             @OA\Property(property="data", ref="#/components/schemas/BankCard")
     *         )
     *     ),
     *     @OA\Response(response=409, description="Card already exists"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function addMyCard(Request $request)
    {
        $user = Auth::user();

        // Validate the request data
        $validated = $request->validate([
            'card_type_id' => 'required|exists:card_types,id',
            'card_number' => 'nullable|string|max:255',
            'card_holder_name' => 'nullable|string|max:255',
            'expiration_date' => [
                'nullable',
                'string',
                'regex:/^(0[1-9]|1[0-2])\/\d{2}$/', // Match MM/YY format
                function ($attribute, $value, $fail) {
                    // Check if expiration month is between 01 and 12
                    $parts = explode('/', $value);
                    $month = (int)$parts[0];
                    $year = (int)$parts[1];

                    // Reject if the month is not valid (greater than 12)
                    if ($month < 1 || $month > 12) {
                        return $fail('The expiration month must be between 01 and 12.');
                    }

                    // Check if the year is realistic (you can change this range based on your requirements)
                    if ($year < 23) { // Accept only years greater than or equal to '23 (2023)'
                        return $fail('The expiration year must be valid and in the future.');
                    }
                }
            ],
            'cvv' => 'nullable|string|max:4',
        ]);

        // Assign the user_id to the validated data
        $validated['user_id'] = $user->id;

        // Check if the card already exists
        if (!empty($validated['card_number'])) {
            $exists = BankCard::where('user_id', $user->id)
                ->where('card_number', $validated['card_number'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'This card already exists for your account.'
                ], 409);
            }

            // Detect the card type
            $cardNumber = preg_replace('/\s+/', '', $validated['card_number']);
            $cardType = null;

            if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $cardNumber)) {
                $cardType = 'visa';
            } elseif (preg_match('/^5[1-5][0-9]{14}$/', $cardNumber)) {
                $cardType = 'mastercard';
            } elseif (preg_match('/^6(?:011|5[0-9]{2})[0-9]{12}$/', $cardNumber)) {
                $cardType = 'applepay';
            }

            if (!in_array($cardType, ['visa', 'mastercard', 'applepay'])) {
                return response()->json([
                    'message' => 'Unsupported card type. Only Visa, MasterCard, and Apple Pay are allowed.'
                ], 422);
            }
        }

        // Encrypt the CVV
        if (!empty($validated['cvv'])) {
            $validated['cvv'] = encrypt($validated['cvv']);
        }

        // Set as default card if this is the first one
        $validated['is_default'] = !BankCard::where('user_id', $user->id)->exists();

        try {
            // Insert the data into the bank_cards table
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
     *              @OA\Property(property="expiration_date", type="string", example="12/25"),
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
            'expiration_date' => [
                'sometimes',
                'string',
                'regex:/^(0[1-9]|1[0-2])\/?([0-9]{2})$/'
            ],
            'cvv' => 'sometimes|string|max:4',
            'is_default' => 'sometimes|boolean',
        ]);

        // Format expiration date consistently (MM/YY)
        if (!empty($validated['expiration_date'])) {
            $validated['expiration_date'] = preg_replace('/[^0-9]/', '', $validated['expiration_date']);
            $validated['expiration_date'] = substr($validated['expiration_date'], 0, 2) . '/' . substr($validated['expiration_date'], 2, 2);
        }

        // Rest of the method remains the same...
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

    /**
     * @OA\Patch(
     *     path="/api/my-cards/{id}/set-default",
     *     summary="Set a card as default for the user",
     *     tags={"BankCards"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Card ID to set as default",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Card set as default successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Card set as default successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BankCard")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Not authorized to update this card"),
     *     @OA\Response(response=404, description="Card not found")
     * )
     */
    public function setAsDefault($id)
    {
        $user = Auth::user();

        // Find the card and verify it belongs to the user
        $card = BankCard::where('user_id', $user->id)->findOrFail($id);

        // Start transaction to ensure data consistency
        DB::transaction(function () use ($user, $card) {
            // Set all other cards to not default
            BankCard::where('user_id', $user->id)
                ->where('id', '!=', $card->id)
                ->update(['is_default' => false]);

            // Set this card as default
            $card->update(['is_default' => true]);
        });

        return response()->json([
            'message' => 'Card set as default successfully',
            'data' => $card->fresh() // Return the updated card
        ]);
    }
}
