<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BankCard;

class CardController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/BankCards",
     *     summary="Get all BankCards",
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
     * @OA\Post(
     *     path="/api/BankCards",
     *     summary="Créer une nouvelle carte bancaire",
     *     tags={"BankCards"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "card_type_id"},
     *             @OA\Property(property="user_id", type="integer", example=12),
     *             @OA\Property(property="card_type_id", type="integer", example=1),
     *             @OA\Property(property="card_number", type="string", example="1234 5678 9012 3456"),
     *             @OA\Property(property="card_holder_name", type="string", example="John Doe"),
     *             @OA\Property(property="expiration_date", type="string", format="date", example="2026-08-31"),
     *             @OA\Property(property="cvv", type="string", example="123")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Carte bancaire créée avec succès"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */

     public function store(Request $request)
     {
         $validated = $request->validate([
             'user_id' => 'required|exists:users,id',
             'card_number' => 'nullable|string|max:255',
             'card_holder_name' => 'nullable|string|max:255',
             'expiration_date' => 'nullable|date',
             'cvv' => 'nullable|string|max:4',
             'card_type_id' => 'required|exists:card_types,id',
         ]);

         // Check if a card with the same user_id and card_number already exists
         if (!empty($validated['card_number'])) {
             $cardExists = BankCard::where('user_id', $validated['user_id'])
                 ->where('card_number', $validated['card_number'])
                 ->exists();

             if ($cardExists) {
                 return response()->json([
                     'message' => 'This card already exists for the specified user.'
                 ], 409); // 409 = Conflict
             }
         }

         // Determine if this card should be marked as default
         $hasCard = BankCard::where('user_id', $validated['user_id'])->exists();
         $validated['is_default'] = !$hasCard;

         try {
             $bankCard = BankCard::create($validated);
             return response()->json([
                 'message' => 'Bank card successfully created.',
                 'data' => $bankCard
             ], 201);
         } catch (\Exception $e) {
             return response()->json([
                 'message' => 'An error occurred while creating the bank card.',
                 'error' => $e->getMessage()
             ], 500);
         }
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
     *     summary="Update BankCard",
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
     *     summary="Delete BankCard",
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
