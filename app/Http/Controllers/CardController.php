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
    /**
     * @OA\Get(
     *     path="/api/BankCards",
     *     summary="Get all BankCards (Admin only)",
     *     tags={"BankCards"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200, 
     *         description="List of BankCards with User details",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 allOf={
     *                     @OA\Schema(ref="#/components/schemas/BankCard"),
     *                     @OA\Schema(
     *                         @OA\Property(
     *                             property="user",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="User Name"),
     *                             @OA\Property(property="email", type="string", example="user@example.com")
     *                         )
     *                     )
     *                 }
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        // Admin should see who owns the card
        return response()->json(BankCard::with(['BankCardType', 'user:id,name,email'])->get());
    }

    // ... (lines 48-297 remain unchanged, they are user methods) ...

    /**
     * @OA\Put(
     *     path="/api/BankCards/{id}",
     *     summary="Update BankCard (Admin only)",
     *     tags={"BankCards"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="card_holder_name", type="string", example="John Doe"),
     *             @OA\Property(property="card_type_id", type="integer", example=1),
     *             @OA\Property(property="is_default", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="BankCard updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $bankCard = BankCard::findOrFail($id);

        $validated = $request->validate([
            'card_holder_name' => 'sometimes|string|max:255',
            'card_type_id' => 'sometimes|exists:card_types,id',
            'is_default' => 'sometimes|boolean',
            // Admin shouldn't really update token fields manually unless necessary, 
            // but we can allow non-sensitive updates.
        ]);

        if (isset($validated['is_default']) && $validated['is_default']) {
            // Unset other default cards for this user
            BankCard::where('user_id', $bankCard->user_id)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);
        }

        $bankCard->update($validated);

        return response()->json($bankCard);
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
