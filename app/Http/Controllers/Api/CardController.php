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
     *     summary="Create a new BankCard",
     *     tags={"BankCards"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "BankCard_type_id"},
     *             @OA\Property(property="name", type="string", example="VIP BankCard"),
     *             @OA\Property(property="BankCard_type_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=201, description="BankCard created")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'BankCard_type_id' => 'required|exists:BankCard_types,id'
        ]);

        $BankCard = BankCard::create($validated);
        return response()->json($BankCard, 201);
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
