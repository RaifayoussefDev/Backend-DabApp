<?php

namespace App\Http\Controllers;

use App\Models\CardType;
use Illuminate\Http\Request;

class CardTypeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/card-types",
     *     summary="Get all card types",
     *     tags={"Card Types"},
     *     @OA\Response(
     *         response=200,
     *         description="List of card types",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Visa")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $cardTypes = CardType::all();
        return response()->json($cardTypes);
    }

    /**
     * @OA\Post(
     *     path="/api/card-types",
     *     summary="Create a new card type",
     *     tags={"Card Types"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Visa")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Card type created",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Visa")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $cardType = CardType::create([
            'name' => $request->name,
        ]);

        return response()->json($cardType, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/card-types/{id}",
     *     summary="Get a card type by ID",
     *     tags={"Card Types"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Card type found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Visa")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card type not found"
     *     )
     * )
     */
    public function show($id)
    {
        $cardType = CardType::findOrFail($id);
        return response()->json($cardType);
    }

    /**
     * @OA\Put(
     *     path="/api/card-types/{id}",
     *     summary="Update an existing card type",
     *     tags={"Card Types"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="MasterCard")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Card type updated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="MasterCard")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card type not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $cardType = CardType::findOrFail($id);
        $cardType->update([
            'name' => $request->name,
        ]);

        return response()->json($cardType);
    }

    /**
     * @OA\Delete(
     *     path="/api/card-types/{id}",
     *     summary="Delete a card type",
     *     tags={"Card Types"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Card type deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card type not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $cardType = CardType::findOrFail($id);
        $cardType->delete();

        return response()->json(null, 204);
    }
}
